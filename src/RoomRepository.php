<?php

declare(strict_types=1);

namespace Songwunsch;

use RuntimeException;

/**
 * Rooms: a capsule of song list and wish list. The `rooms` table holds the
 * machine name (slug, part of the address) and the display name; `room_songs`
 * says which songs of the master list a room offers.
 *
 * The default room is virtual: id 0, no row, the master list itself. It is
 * what / and /wishes show and is always there. Wishes carry room_id 0 for it.
 */
final class RoomRepository
{
    private const TABLE = '`' . Schema::ROOMS . '`';
    private const SONGS = '`' . Schema::ROOM_SONGS . '`';

    public const DEFAULT_ID = 0;

    public const MIN_SLUG = 2;
    public const MAX_SLUG = 64;
    public const MAX_NAME = 128;

    /** Lower-case letters, digits and single hyphens in between -- safe in a path without encoding. */
    public const SLUG_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    private const SELECT = 'SELECT id, slug, name, active, created_at, updated_at FROM ' . self::TABLE;

    /** Filters of the room list: only active rooms, only archived ones, or every room. */
    public const FILTERS = ['active', 'archived', 'all'];

    public function __construct(private readonly Database $db)
    {
    }

    /** Settings key under which the main room's chosen name is kept ('' = the default name). */
    public const MAIN_NAME_KEY = 'main_room_name';

    /**
     * Settings key of the start room: the id of the room a visitor without
     * any remembered room lands in when opening the bare address. Absent or
     * 0 = the main room, as the address says.
     */
    public const START_ROOM_KEY = 'start_room';

    /** @var string the main room's name as set by an editor, '' for the translated default */
    private static string $mainName = '';

    /**
     * Give the main room a name of its own (index.php reads it from the
     * settings on every request). An empty string means the default,
     * "Main room" in the visitor's language.
     */
    public static function nameMainRoom(string $name): void
    {
        self::$mainName = trim($name);
    }

    /** The virtual default room, under its chosen or its default name. */
    public static function defaultRoom(): array
    {
        return [
            'id'         => self::DEFAULT_ID,
            'slug'       => null,
            'name'       => self::$mainName !== '' ? self::$mainName : t('Main room'),
            'is_default' => true,
        ];
    }

    public static function isDefault(array $room): bool
    {
        return (int) ($room['id'] ?? self::DEFAULT_ID) === self::DEFAULT_ID;
    }

    /**
     * One page of rooms with their song and open-wish counts, by name.
     *
     * @param  string $query  search in name and slug
     * @param  string $filter one of FILTERS
     * @return array{rows: array<int,array<string,mixed>>, total: int}
     */
    public function search(string $query, string $filter, int $page, int $perPage): array
    {
        $t  = self::TABLE;
        $rs = self::SONGS;
        $w  = '`' . Schema::WISHES . '`';

        $conditions = [];
        $params     = [];

        if ($filter === 'active') {
            $conditions[] = 'r.active = 1';
        } elseif ($filter === 'archived') {
            $conditions[] = 'r.active = 0';
        }

        $query = trim($query);
        if ($query !== '') {
            $like         = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $query) . '%';
            $conditions[] = '(r.name LIKE ? OR r.slug LIKE ?)';
            array_push($params, $like, $like);
        }

        $where  = $conditions === [] ? '' : 'WHERE ' . implode(' AND ', $conditions);
        $total  = (int) ($this->db->one("SELECT COUNT(*) AS c FROM {$t} r {$where}", $params)['c'] ?? 0);
        $offset = max(0, ($page - 1) * $perPage);

        $rows = $this->db->all(
            "SELECT r.id, r.slug, r.name, r.active, r.created_at, r.updated_at,
                    (SELECT COUNT(*) FROM {$rs} s WHERE s.room_id = r.id) AS song_count,
                    (SELECT COUNT(*) FROM {$w} x WHERE x.room_id = r.id) AS wish_count
             FROM {$t} r {$where}
             ORDER BY r.name ASC, r.id ASC
             LIMIT {$perPage} OFFSET {$offset}",
            $params,
        );

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * Id, slug and name of every active room, by name -- for the room
     * switcher in the header, on every page. Archived rooms stay reachable
     * through their address but are not offered.
     *
     * @return array<int,array<string,mixed>>
     */
    public function names(): array
    {
        return $this->db->all('SELECT id, slug, name FROM ' . self::TABLE . ' WHERE active = 1 ORDER BY name ASC, id ASC');
    }

    /**
     * Display name of every room, active and archived, by id -- for tagging
     * rows (the suggestions) with the room they belong to.
     *
     * @return array<int,string>
     */
    public function namesById(): array
    {
        $names = [];
        foreach ($this->db->all('SELECT id, name FROM ' . self::TABLE) as $row) {
            $names[(int) $row['id']] = (string) $row['name'];
        }

        return $names;
    }

    /**
     * Ids of every room, active and archived -- for switches that act on all
     * rooms at once (the admin's pause).
     *
     * @return array<int,int>
     */
    public function ids(): array
    {
        return array_map(
            static fn (array $row): int => (int) $row['id'],
            $this->db->all('SELECT id FROM ' . self::TABLE . ' ORDER BY id ASC'),
        );
    }

    public function find(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        return $this->db->one(self::SELECT . ' WHERE id = ? LIMIT 1', [$id]);
    }

    /** Look a room up by its address part. Anything outside SLUG_PATTERN is unknown without a query. */
    public function findBySlug(string $slug): ?array
    {
        if (preg_match(self::SLUG_PATTERN, $slug) !== 1) {
            return null;
        }

        return $this->db->one(self::SELECT . ' WHERE slug = ? LIMIT 1', [$slug]);
    }

    // ---- Writing ----------------------------------------------------------

    /**
     * Validate form input. The slug is normalised (trimmed, lower-cased)
     * before the check, so "Sommerfest" becomes "sommerfest" instead of an
     * error.
     *
     * @param  array<string,string> $input
     * @return array{values: array<string,mixed>, errors: array<string,string>}
     */
    public function validate(array $input, ?array $existing): array
    {
        $errors = [];
        $values = [];

        $slug = mb_strtolower(trim((string) ($input['slug'] ?? '')));
        if ($slug === '') {
            $errors['slug'] = t('{field} is required.', ['field' => t('Machine name')]);
        } elseif (mb_strlen($slug) < self::MIN_SLUG || mb_strlen($slug) > self::MAX_SLUG) {
            $errors['slug'] = t('Machine name: {min} to {max} characters.', ['min' => self::MIN_SLUG, 'max' => self::MAX_SLUG]);
        } elseif (preg_match(self::SLUG_PATTERN, $slug) !== 1) {
            $errors['slug'] = t('Machine name: lower-case letters a–z, digits and hyphens, e.g. “sommerfest-2026”.');
        } else {
            $other = $this->findBySlug($slug);
            if ($other !== null && ($existing === null || (int) $other['id'] !== (int) $existing['id'])) {
                $errors['slug'] = t('This machine name is already taken.');
            } else {
                $values['slug'] = $slug;
            }
        }

        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            $errors['name'] = t('{field} is required.', ['field' => t('Name')]);
        } elseif (mb_strlen($name) > self::MAX_NAME) {
            $errors['name'] = t('{field} is too long: at most {max} characters.', ['field' => t('Name'), 'max' => self::MAX_NAME]);
        } else {
            $values['name'] = $name;
        }

        // Archived rooms leave the switcher and the guests' list; a new room
        // starts active.
        $values['active'] = (($input['active'] ?? '') === '1') ? 1 : 0;

        return ['values' => $values, 'errors' => $errors];
    }

    /** @param array<string,mixed> $values result of validate() */
    public function create(array $values): int
    {
        $now = date('Y-m-d H:i:s');
        $this->db->exec(
            'INSERT INTO ' . self::TABLE . ' (slug, name, active, created_at, updated_at) VALUES (?, ?, ?, ?, ?)',
            [$values['slug'], $values['name'], $values['active'] ?? 1, $now, $now],
        );

        return (int) $this->db->pdo()->lastInsertId();
    }

    /** @param array<string,mixed> $values result of validate() */
    public function update(int $id, array $values): void
    {
        if ($this->find($id) === null) {
            throw new RuntimeException(t('This room was not found.'));
        }

        $this->db->exec(
            'UPDATE ' . self::TABLE . ' SET slug = ?, name = ?, active = ?, updated_at = ? WHERE id = ? LIMIT 1',
            [$values['slug'], $values['name'], $values['active'] ?? 1, date('Y-m-d H:i:s'), $id],
        );
    }

    /** Delete a room together with its song selection and its wishes. */
    public function delete(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        $pdo = $this->db->pdo();
        $pdo->beginTransaction();
        try {
            $this->db->exec('DELETE FROM ' . self::SONGS . ' WHERE room_id = ?', [$id]);
            $this->db->exec('DELETE FROM `' . Schema::WISHES . '` WHERE room_id = ?', [$id]);
            // Suggestions made in the room stay -- they aim at the master
            // list anyway -- and fall back to the main room.
            $this->db->exec('UPDATE `' . Schema::SUGGESTIONS . '` SET room_id = 0 WHERE room_id = ?', [$id]);
            $removed = $this->db->exec('DELETE FROM ' . self::TABLE . ' WHERE id = ? LIMIT 1', [$id]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return $removed > 0;
    }

    // ---- Song selection ---------------------------------------------------

    /**
     * Add songs of the master list to a room. Ids already in the room or not
     * in the master list are skipped.
     *
     * @param  array<int,int> $songIds
     * @return int number of songs added
     */
    public function addSongs(int $roomId, array $songIds): int
    {
        $songIds = array_values(array_unique(array_filter(array_map('intval', $songIds), static fn (int $id): bool => $id > 0)));
        if ($roomId <= 0 || $songIds === []) {
            return 0;
        }

        $marks = implode(', ', array_fill(0, count($songIds), '?'));

        // INSERT ... SELECT keeps out ids that do not exist in `songs`.
        return $this->db->exec(
            'INSERT IGNORE INTO ' . self::SONGS . ' (room_id, song_id)
             SELECT ?, id FROM `' . Schema::SONGS . "` WHERE id IN ({$marks})",
            [$roomId, ...$songIds],
        );
    }

    /** @param array<int,int> $songIds */
    public function removeSongs(int $roomId, array $songIds): int
    {
        $songIds = array_values(array_filter(array_map('intval', $songIds), static fn (int $id): bool => $id > 0));
        if ($roomId <= 0 || $songIds === []) {
            return 0;
        }

        $marks = implode(', ', array_fill(0, count($songIds), '?'));

        return $this->db->exec(
            'DELETE FROM ' . self::SONGS . " WHERE room_id = ? AND song_id IN ({$marks})",
            [$roomId, ...$songIds],
        );
    }

    /** Remove a song from every room -- when it leaves the master list. */
    public function removeSongEverywhere(int $songId): void
    {
        $this->db->exec('DELETE FROM ' . self::SONGS . ' WHERE song_id = ?', [$songId]);
    }
}
