<?php

declare(strict_types=1);

namespace Songwunsch;

/**
 * Small key/value store in the `settings` table for state that must outlive
 * sessions and requests: the moderator's pause switch and the rotating
 * secrets of the wish guard.
 */
final class Settings
{
    private const TABLE = '`' . Schema::SETTINGS . '`';

    /** What the admin can switch the delete confirmation off for. */
    public const CONFIRM_DELETE = ['songs', 'wishes', 'rooms'];

    public function __construct(private readonly Database $db)
    {
    }

    public function get(string $name, ?string $default = null): ?string
    {
        $row = $this->db->one('SELECT value FROM ' . self::TABLE . ' WHERE name = ?', [$name]);

        return $row === null ? $default : (string) $row['value'];
    }

    public function set(string $name, string $value): void
    {
        $this->db->exec(
            'INSERT INTO ' . self::TABLE . ' (name, value, updated_at) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = VALUES(updated_at)',
            [$name, $value, date('Y-m-d H:i:s')],
        );
    }

    /**
     * Create a value only if it does not exist yet. Two concurrent calls thus
     * agree on the same value -- important for secrets.
     */
    public function setIfMissing(string $name, string $value): string
    {
        $this->db->exec(
            'INSERT IGNORE INTO ' . self::TABLE . ' (name, value, updated_at) VALUES (?, ?, ?)',
            [$name, $value, date('Y-m-d H:i:s')],
        );

        return (string) $this->get($name, $value);
    }

    /**
     * Does deleting a single song, wish or room ask this user for
     * confirmation? On by default; every user switches it off per kind under
     * Settings, for their own account only. Bulk actions (clear the wish
     * list) always ask. Without a user (id 0) the answer is always yes.
     */
    public function confirmsDelete(int $userId, string $what): bool
    {
        return $userId <= 0 || $this->get(self::userKey($userId, 'confirm_delete_' . $what), '1') !== '0';
    }

    public function setConfirmDelete(int $userId, string $what, bool $on): void
    {
        $this->set(self::userKey($userId, 'confirm_delete_' . $what), $on ? '1' : '0');
    }

    /** Drop everything stored for a user -- when the account is deleted. */
    public function forgetUser(int $userId): void
    {
        $this->deleteByPrefixExcept(self::userKey($userId, ''), []);
    }

    /** Per-user values live under `user.<id>.<name>`. */
    private static function userKey(int $userId, string $name): string
    {
        return 'user.' . $userId . '.' . $name;
    }

    public function delete(string $name): void
    {
        $this->db->exec('DELETE FROM ' . self::TABLE . ' WHERE name = ?', [$name]);
    }

    /** Delete every entry with this prefix except the ones listed. */
    public function deleteByPrefixExcept(string $prefix, array $keep): void
    {
        $sql    = 'DELETE FROM ' . self::TABLE . ' WHERE name LIKE ?';
        $params = [str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $prefix) . '%'];

        if ($keep !== []) {
            $sql .= ' AND name NOT IN (' . implode(', ', array_fill(0, count($keep), '?')) . ')';
            $params = array_merge($params, array_values($keep));
        }

        $this->db->exec($sql, $params);
    }
}
