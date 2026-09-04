<?php

declare(strict_types=1);

namespace Songwunsch;

/**
 * Users and roles in the `users` table.
 *
 * Roles:
 *  - Admin      creates and manages users -- and hands out every role, this
 *               one included; may do everything. Admin includes the other
 *               two roles, and they are stored along with it (validate), so
 *               an admin who gives the role up keeps them. Any number of
 *               admins; the only rule is that the last active admin cannot
 *               give the role up (activeAdmins), so there is always somebody
 *               who can manage users. Nobody locks or deletes themselves.
 *  - Editor     maintains the song list.
 *  - Moderator  edits the wish list.
 * Editor and moderator can be combined freely.
 *
 * Only username and password hash are stored -- no e-mail, no real name, no
 * login timestamps (data minimisation).
 */
final class UserRepository
{
    private const TABLE = '`' . Schema::USERS . '`';

    public const MIN_NAME     = 2;
    public const MAX_NAME     = 64;
    public const MIN_PASSWORD = 8;

    private const SELECT = 'SELECT id, username, password_hash, role_admin, role_moderator, role_editor, active FROM ' . self::TABLE;

    public function __construct(private readonly Database $db)
    {
    }

    /** @return array<int,array<string,mixed>> alphabetically by username */
    /**
     * Every user, by name -- or only those whose name contains $query.
     *
     * @return array<int,array<string,mixed>>
     */
    public function all(string $query = ''): array
    {
        $query = trim($query);
        if ($query === '') {
            return $this->db->all(self::SELECT . ' ORDER BY username ASC');
        }
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $query) . '%';

        return $this->db->all(self::SELECT . ' WHERE username LIKE ? ORDER BY username ASC', [$like]);
    }

    public function count(): int
    {
        return (int) ($this->db->one('SELECT COUNT(*) AS c FROM ' . self::TABLE)['c'] ?? 0);
    }

    public function find(int $id): ?array
    {
        return $id > 0 ? $this->db->one(self::SELECT . ' WHERE id = ? LIMIT 1', [$id]) : null;
    }

    public function findByName(string $username): ?array
    {
        return $this->db->one(self::SELECT . ' WHERE username = ? LIMIT 1', [$username]);
    }

    /**
     * How many active admins there are, not counting $exceptId. Zero means
     * the user in question is the only one who can manage users -- then
     * they must keep the role.
     */
    public function activeAdmins(int $exceptId = 0): int
    {
        $row = $this->db->one(
            'SELECT COUNT(*) AS c FROM ' . self::TABLE . ' WHERE role_admin = 1 AND active = 1 AND id <> ?',
            [$exceptId],
        );

        return (int) ($row['c'] ?? 0);
    }

    /**
     * Create the first admin from config.php as long as there is no user yet.
     * Afterwards config.php plays no role for logging in any more.
     *
     * @param array{user:string,hash:string} $auth
     */
    public function ensureAdmin(array $auth): bool
    {
        if ($this->count() > 0) {
            return false;
        }

        $now = date('Y-m-d H:i:s');
        $this->db->exec(
            'INSERT INTO ' . self::TABLE
            . ' (username, password_hash, role_admin, role_moderator, role_editor, active, created_at, updated_at)
               VALUES (?, ?, 1, 1, 1, 1, ?, ?)',
            [mb_substr(trim((string) $auth['user']), 0, self::MAX_NAME), (string) $auth['hash'], $now, $now],
        );

        return true;
    }

    /**
     * Validate form input. $existing is the user being edited, or null when
     * creating -- then the password is mandatory.
     *
     * @param  array<string,string> $input
     * @return array{values: array<string,mixed>, errors: array<string,string>}
     */
    public function validate(array $input, ?array $existing): array
    {
        $errors = [];
        $values = [];

        $name = trim((string) ($input['username'] ?? ''));
        if ($name === '') {
            $errors['username'] = t('{field} is required.', ['field' => t('Username')]);
        } elseif (mb_strlen($name) < self::MIN_NAME || mb_strlen($name) > self::MAX_NAME) {
            $errors['username'] = t('Username: {min} to {max} characters.', ['min' => self::MIN_NAME, 'max' => self::MAX_NAME]);
        } elseif (preg_match('/^[\p{L}\p{N}._@-]+$/u', $name) !== 1) {
            $errors['username'] = t('Username: letters, digits, dot, underscore, hyphen or @.');
        } else {
            $other = $this->findByName($name);
            if ($other !== null && ($existing === null || (int) $other['id'] !== (int) $existing['id'])) {
                $errors['username'] = t('This username is already taken.');
            } else {
                $values['username'] = $name;
            }
        }

        $password = (string) ($input['password'] ?? '');
        if ($password === '') {
            if ($existing === null) {
                $errors['password'] = t('{field} is required.', ['field' => t('Password')]);
            }
        } else {
            $check = $this->checkPassword($password, (string) ($input['password2'] ?? ''));
            $errors += $check['errors'];
            if ($check['hash'] !== null) {
                $values['password_hash'] = $check['hash'];
            }
        }

        // Admin includes editor and moderator -- the form locks those boxes
        // (locked boxes are not posted), so the roles are derived here.
        $values['role_admin']     = (($input['role_admin'] ?? '') === '1') ? 1 : 0;
        $values['role_moderator'] = $values['role_admin'] === 1 || (($input['role_moderator'] ?? '') === '1') ? 1 : 0;
        $values['role_editor']    = $values['role_admin'] === 1 || (($input['role_editor'] ?? '') === '1') ? 1 : 0;
        $values['active']         = (($input['active'] ?? '') === '1') ? 1 : 0;

        return ['values' => $values, 'errors' => $errors];
    }

    /**
     * The rules for a new password: long enough and typed twice the same.
     * Shared by the admin's user form and the own-password form.
     *
     * @return array{errors:array<string,string>,hash:?string} hash only without errors
     */
    public function checkPassword(string $password, string $repeat): array
    {
        if (mb_strlen($password) < self::MIN_PASSWORD) {
            return ['errors' => ['password' => t('Password: at least {min} characters.', ['min' => self::MIN_PASSWORD])], 'hash' => null];
        }
        if ($password !== $repeat) {
            return ['errors' => ['password2' => t('The two passwords do not match.')], 'hash' => null];
        }

        return ['errors' => [], 'hash' => password_hash($password, PASSWORD_DEFAULT)];
    }

    /** A user changes their own password; roles and status stay as they are. */
    public function setPassword(int $id, string $hash): void
    {
        $this->db->exec(
            'UPDATE ' . self::TABLE . ' SET password_hash = ?, updated_at = ? WHERE id = ? LIMIT 1',
            [$hash, date('Y-m-d H:i:s'), $id],
        );
    }

    /**
     * @param array<string,mixed> $values result of validate()
     * @return int id of the new user
     */
    public function create(array $values): int
    {
        $now = date('Y-m-d H:i:s');
        $this->db->exec(
            'INSERT INTO ' . self::TABLE
            . ' (username, password_hash, role_admin, role_moderator, role_editor, active, created_at, updated_at)
               VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $values['username'],
                $values['password_hash'],
                $values['role_admin'],
                $values['role_moderator'],
                $values['role_editor'],
                $values['active'],
                $now,
                $now,
            ],
        );

        return (int) $this->db->pdo()->lastInsertId();
    }

    /** @param array<string,mixed> $values result of validate(); without password_hash the password is kept */
    public function update(int $id, array $values): void
    {
        $set    = ['username = ?', 'role_admin = ?', 'role_moderator = ?', 'role_editor = ?', 'active = ?', 'updated_at = ?'];
        $params = [
            $values['username'],
            $values['role_admin'],
            $values['role_moderator'],
            $values['role_editor'],
            $values['active'],
            date('Y-m-d H:i:s'),
        ];

        if (isset($values['password_hash'])) {
            $set[]    = 'password_hash = ?';
            $params[] = $values['password_hash'];
        }

        $params[] = $id;
        $this->db->exec('UPDATE ' . self::TABLE . ' SET ' . implode(', ', $set) . ' WHERE id = ? LIMIT 1', $params);
    }

    /** Delete a user. That it is not oneself is the caller's business. */
    public function delete(int $id): bool
    {
        return $this->db->exec('DELETE FROM ' . self::TABLE . ' WHERE id = ? LIMIT 1', [$id]) > 0;
    }

    /**
     * Readable role list for tables and messages. The first entry, if
     * present, is the admin label -- templates highlight it.
     *
     * @return array<int,string>
     */
    public static function roleLabels(array $user): array
    {
        $labels = [];
        if ((int) ($user['role_admin'] ?? 0) === 1) {
            $labels[] = t('Admin', [], 'role');
        }
        if ((int) ($user['role_editor'] ?? 0) === 1) {
            $labels[] = t('Editor', [], 'role');
        }
        if ((int) ($user['role_moderator'] ?? 0) === 1) {
            $labels[] = t('Moderator', [], 'role');
        }

        return $labels;
    }
}
