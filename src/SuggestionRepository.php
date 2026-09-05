<?php

declare(strict_types=1);

namespace Songwunsch;

/**
 * Song suggestions from the audience in the `song_suggestions` table: a
 * guest misses a song and names artist and title; the editor looks the
 * list over and either adopts a suggestion into the song list (adding
 * length and genre on the way) or deletes it. Adopting removes the
 * suggestion -- the list only ever holds what is still open.
 *
 * Suggestions aim at the main list, which is what every room picks from.
 * A suggestion made inside a room remembers that room (room_id, 0 = main
 * room): the adopted song is then offered in the room right away. The list
 * itself is one for the whole site -- the editor sees every suggestion,
 * tagged with its room.
 *
 * Like a wish, a suggestion stores no IP address and no user agent (GDPR:
 * data minimisation). The only personal data is the name the guest chose
 * to give (`suggester`, see GuestName); it goes with the suggestion.
 */
final class SuggestionRepository
{
    private const TABLE = '`' . Schema::SUGGESTIONS . '`';

    /** Settings key of the list's revision -- raised on every change, polled by open pages (app.js). */
    public const REVISION_KEY = 'suggestions_rev';

    public const MAX_ARTIST = SongRepository::MAX_ARTIST;
    public const MAX_TITLE  = SongRepository::MAX_TITLE;

    public function __construct(private readonly Database $db)
    {
    }

    /**
     * One page of the open suggestions, oldest first -- the order they came
     * in is the order the editor works through. With a query only those
     * whose artist, title or suggester contain every term (AND, like the
     * song search). `total` counts every match, not just the page.
     *
     * @return array{rows: array<int,array<string,mixed>>, total: int}
     */
    public function search(string $query, int $page, int $perPage): array
    {
        $terms      = preg_split('/\s+/u', trim($query), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $conditions = [];
        $params     = [];

        foreach (array_slice($terms, 0, 6) as $term) {
            $like         = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $term) . '%';
            $conditions[] = '(artist LIKE ? OR title LIKE ? OR suggester LIKE ?)';
            array_push($params, $like, $like, $like);
        }
        $where = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);

        $total  = (int) ($this->db->one('SELECT COUNT(*) AS c FROM ' . self::TABLE . $where, $params)['c'] ?? 0);
        $offset = max(0, ($page - 1) * $perPage);
        $rows   = $this->db->all(
            'SELECT * FROM ' . self::TABLE . $where . " ORDER BY created_at ASC, id ASC LIMIT {$perPage} OFFSET {$offset}",
            $params,
        );

        return ['rows' => $rows, 'total' => $total];
    }

    public function count(): int
    {
        return (int) ($this->db->one('SELECT COUNT(*) AS c FROM ' . self::TABLE)['c'] ?? 0);
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        return $this->db->one('SELECT * FROM ' . self::TABLE . ' WHERE id = ? LIMIT 1', [$id]);
    }

    /**
     * Has this song been suggested already? Artist and title compare
     * case-insensitively through the table's collation, so "abba" and
     * "ABBA" count as the same suggestion.
     */
    public function isPending(string $artist, string $title): bool
    {
        return $this->db->one(
            'SELECT id FROM ' . self::TABLE . ' WHERE artist = ? AND title = ? LIMIT 1',
            [$artist, $title],
        ) !== null;
    }

    /**
     * Validate the guest's input. Artist and title are required and bounded
     * like the song list's columns; control characters and runs of blanks
     * are tidied the way GuestName does it.
     *
     * @param  array<string,string> $input
     * @return array{values: array<string,string>, errors: array<string,string>}
     */
    public function validate(array $input): array
    {
        $errors = [];
        $values = [];

        $fields = [
            'artist' => [t('Artist'), self::MAX_ARTIST],
            'title'  => [t('Title'), self::MAX_TITLE],
        ];

        foreach ($fields as $field => [$label, $max]) {
            $value = preg_replace('/[\p{C}]+/u', ' ', (string) ($input[$field] ?? '')) ?? '';
            $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

            if ($value === '') {
                $errors[$field] = t('{field} is required.', ['field' => $label]);
            } elseif (mb_strlen($value) > $max) {
                $errors[$field] = t('{field} is too long: at most {max} characters.', ['field' => $label, 'max' => $max]);
            } else {
                $values[$field] = $value;
            }
        }

        return ['values' => $values, 'errors' => $errors];
    }

    /**
     * @param array<string,string> $values    result of validate()
     * @param string|null          $suggester the guest's name, if given
     * @param int                  $roomId    the room the guest was in, 0 = main room
     * @return int id of the new suggestion
     */
    public function add(array $values, ?string $suggester = null, int $roomId = RoomRepository::DEFAULT_ID): int
    {
        // Timestamp from PHP, like the wishes: display and "x minutes ago"
        // then agree even when PHP and MySQL run in different time zones.
        $this->db->exec(
            'INSERT INTO ' . self::TABLE . ' (artist, title, suggester, created_at, room_id) VALUES (?, ?, ?, ?, ?)',
            [
                $values['artist'],
                $values['title'],
                $suggester !== null && $suggester !== '' ? $suggester : null,
                date('Y-m-d H:i:s'),
                max(0, $roomId),
            ],
        );

        return (int) $this->db->pdo()->lastInsertId();
    }

    public function delete(int $id): bool
    {
        return $this->db->exec('DELETE FROM ' . self::TABLE . ' WHERE id = ? LIMIT 1', [$id]) > 0;
    }

    public function deleteAll(): int
    {
        return $this->db->exec('DELETE FROM ' . self::TABLE);
    }
}
