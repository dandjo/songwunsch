<?php

declare(strict_types=1);

namespace Songwunsch;

use RuntimeException;

/**
 * Access to the `songs` table -- read for every visitor, write only from the
 * logged-in area (see index.php).
 *
 * Columns: id, artist, title, length_sec (seconds, NULL = unknown), genre.
 * All values go through placeholders; identifiers are fixed in the code.
 *
 * `songs` is the main list. A room (RoomRepository) offers a selection of
 * it: reading methods take a room id, 0 (the default room) means the whole
 * main list, anything else joins `room_songs`.
 */
final class SongRepository
{
    private const TABLE = Schema::SONGS;

    public const MAX_ARTIST = 255;
    public const MAX_TITLE  = 255;
    public const MAX_GENRE  = 128;
    public const MAX_LENGTH = 86399; // 23:59:59

    private const FIELDS = 's.id, s.artist, s.title, s.length_sec, s.genre';
    private const SELECT = 'SELECT ' . self::FIELDS . ' FROM `' . self::TABLE . '` s';

    public function __construct(private readonly Database $db)
    {
    }

    /** Sortable columns: key = value of the ?sort parameter. */
    public function sortableFields(): array
    {
        return [
            'artist' => 'artist',
            'title'  => 'title',
            'length' => 'length_sec',
            'genre'  => 'genre',
        ];
    }

    /**
     * @param int $roomId 0 = main list, otherwise only the room's selection
     * @return array{rows: array<int,array<string,mixed>>, total: int}
     */
    public function search(string $query, string $sort, string $dir, int $page, int $perPage, int $roomId = 0): array
    {
        [$join, $joinParams] = $this->roomJoin($roomId);
        [$where, $params]    = $this->buildWhere($query);
        $orderBy             = $this->buildOrderBy($sort, $dir);
        $params              = [...$joinParams, ...$params];
        $table               = '`' . self::TABLE . '` s';

        $total = (int) ($this->db->one("SELECT COUNT(*) AS c FROM {$table} {$join} {$where}", $params)['c'] ?? 0);

        $offset = max(0, ($page - 1) * $perPage);
        $rows   = $this->db->all(
            self::SELECT . " {$join} {$where} ORDER BY {$orderBy} LIMIT {$perPage} OFFSET {$offset}",
            $params,
        );

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * Main-list songs that are NOT in the given room -- the left column of the
     * room's song picker.
     *
     * @return array{rows: array<int,array<string,mixed>>, total: int}
     */
    public function searchAvailable(string $query, string $sort, string $dir, int $page, int $perPage, int $roomId): array
    {
        [$where, $params] = $this->buildWhere($query);
        $orderBy          = $this->buildOrderBy($sort, $dir);
        $table            = '`' . self::TABLE . '` s';
        $join             = 'LEFT JOIN `' . Schema::ROOM_SONGS . '` rs ON rs.song_id = s.id AND rs.room_id = ?';
        $where            = ($where === '' ? 'WHERE' : $where . ' AND') . ' rs.song_id IS NULL';
        $params           = [$roomId, ...$params];

        $total = (int) ($this->db->one("SELECT COUNT(*) AS c FROM {$table} {$join} {$where}", $params)['c'] ?? 0);

        $offset = max(0, ($page - 1) * $perPage);
        $rows   = $this->db->all(
            self::SELECT . " {$join} {$where} ORDER BY {$orderBy} LIMIT {$perPage} OFFSET {$offset}",
            $params,
        );

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * Ids of every main-list song matching the query -- for "add all found".
     *
     * @return array<int,int>
     */
    public function idsMatching(string $query): array
    {
        [$where, $params] = $this->buildWhere($query);
        $rows = $this->db->all('SELECT s.id FROM `' . self::TABLE . "` s {$where}", $params);

        return array_map(static fn (array $row): int => (int) $row['id'], $rows);
    }

    /** Number of songs in the main list (0) or in a room. */
    public function count(int $roomId = 0): int
    {
        [$join, $params] = $this->roomJoin($roomId);

        return (int) ($this->db->one('SELECT COUNT(*) AS c FROM `' . self::TABLE . "` s {$join}", $params)['c'] ?? 0);
    }

    /** @param int $roomId 0 = main list; otherwise the song must be in the room */
    public function find(int $id, int $roomId = 0): ?array
    {
        if ($id <= 0) {
            return null;
        }

        [$join, $params] = $this->roomJoin($roomId);

        return $this->db->one(self::SELECT . " {$join} WHERE s.id = ? LIMIT 1", [...$params, $id]);
    }

    /**
     * Is this song already in the main list? Compared case-insensitively
     * through the table's collation -- used to tell a guest their suggestion
     * is already there to be wished for.
     */
    public function exists(string $artist, string $title): bool
    {
        return $this->db->one(
            'SELECT id FROM `' . self::TABLE . '` WHERE artist = ? AND title = ? LIMIT 1',
            [$artist, $title],
        ) !== null;
    }

    /**
     * Genres already in use, for the suggestion list in the form -- keeps
     * the spelling consistent.
     *
     * @return array<int,string>
     */
    public function knownGenres(): array
    {
        $rows = $this->db->all(
            'SELECT DISTINCT genre FROM `' . self::TABLE . "` WHERE genre IS NOT NULL AND genre <> '' ORDER BY genre LIMIT 200",
        );

        return array_map(static fn (array $row): string => (string) $row['genre'], $rows);
    }

    // ---- Writing ----------------------------------------------------------

    /**
     * Validate form input and translate it into column values. No database
     * access -- the result either goes to create()/update() or back into
     * the form together with the errors.
     *
     * @param  array<string,string> $input
     * @return array{values: array<string,mixed>, errors: array<string,string>}
     */
    public function validate(array $input): array
    {
        $errors = [];
        $values = [];

        $required = [
            'artist' => [t('Artist'), self::MAX_ARTIST],
            'title'  => [t('Title'), self::MAX_TITLE],
        ];

        foreach ($required as $field => [$label, $max]) {
            $value = trim((string) ($input[$field] ?? ''));

            if ($value === '') {
                $errors[$field] = t('{field} is required.', ['field' => $label]);
            } elseif (mb_strlen($value) > $max) {
                $errors[$field] = t('{field} is too long: at most {max} characters.', ['field' => $label, 'max' => $max]);
            } else {
                $values[$field] = $value;
            }
        }

        $genre = trim((string) ($input['genre'] ?? ''));
        if (mb_strlen($genre) > self::MAX_GENRE) {
            $errors['genre'] = t('{field} is too long: at most {max} characters.', ['field' => t('Genre'), 'max' => self::MAX_GENRE]);
        } else {
            $values['genre'] = $genre === '' ? null : $genre;
        }

        $raw     = trim((string) ($input['length'] ?? ''));
        $seconds = Format::parseLength($raw);

        if ($seconds === null) {
            $values['length_sec'] = null;
        } elseif ($seconds === false) {
            $errors['length'] = t('Length not understood. Examples: 3:45 or 225 (seconds).');
        } elseif ($seconds > self::MAX_LENGTH) {
            $errors['length'] = t('Length is unrealistic – at most 23:59:59.');
        } else {
            $values['length_sec'] = $seconds;
        }

        return ['values' => $values, 'errors' => $errors];
    }

    /**
     * @param array<string,mixed> $values result of validate()
     * @return int id of the new song
     */
    public function create(array $values): int
    {
        $this->db->exec(
            'INSERT INTO `' . self::TABLE . '` (artist, title, length_sec, genre) VALUES (?, ?, ?, ?)',
            [$values['artist'], $values['title'], $values['length_sec'], $values['genre']],
        );

        return (int) $this->db->pdo()->lastInsertId();
    }

    /** @param array<string,mixed> $values result of validate() */
    public function update(int $id, array $values): void
    {
        if ($this->find($id) === null) {
            throw new RuntimeException(t('This song was not found.'));
        }

        // rowCount() is deliberately not checked: MySQL reports 0 when no
        // value changed.
        $this->db->exec(
            'UPDATE `' . self::TABLE . '` SET artist = ?, title = ?, length_sec = ?, genre = ? WHERE id = ? LIMIT 1',
            [$values['artist'], $values['title'], $values['length_sec'], $values['genre'], $id],
        );
    }

    public function delete(int $id): bool
    {
        return $this->db->exec('DELETE FROM `' . self::TABLE . '` WHERE id = ? LIMIT 1', [$id]) > 0;
    }

    /**
     * JOIN that narrows the main list down to a room's selection.
     *
     * @return array{0:string,1:array<int,int>}
     */
    private function roomJoin(int $roomId): array
    {
        if ($roomId <= 0) {
            return ['', []];
        }

        return ['INNER JOIN `' . Schema::ROOM_SONGS . '` rs ON rs.song_id = s.id AND rs.room_id = ?', [$roomId]];
    }

    /** @return array{0:string,1:array<int,string>} */
    private function buildWhere(string $query): array
    {
        $query = trim($query);
        if ($query === '') {
            return ['', []];
        }

        // Several terms: all of them must appear somewhere (AND).
        $terms      = preg_split('/\s+/u', $query, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $conditions = [];
        $params     = [];

        foreach (array_slice($terms, 0, 6) as $term) {
            $like         = '%' . self::escapeLike($term) . '%';
            $conditions[] = '(s.artist LIKE ? OR s.title LIKE ? OR s.genre LIKE ?)';
            array_push($params, $like, $like, $like);
        }

        return ['WHERE ' . implode(' AND ', $conditions), $params];
    }

    private function buildOrderBy(string $sort, string $dir): string
    {
        $column    = $this->sortableFields()[$sort] ?? 'artist';
        $direction = strtolower($dir) === 'desc' ? 'DESC' : 'ASC';

        // Secondary sort so the order stays stable on ties.
        return $column === 'title'
            ? "s.title {$direction}"
            : "s.{$column} {$direction}, s.title ASC";
    }

    private static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }
}
