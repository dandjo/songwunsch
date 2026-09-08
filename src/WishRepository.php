<?php

declare(strict_types=1);

namespace Songwunsch;

/**
 * Received wishes in the `song_wishes` table. Every wish keeps a copy of
 * artist/title/length/genre, so the wish list stays readable even when the
 * song is edited or deleted later.
 *
 * No IP address and no user agent are stored (GDPR: data minimisation). The
 * only personal data is the name a guest chose to give (`wisher`, see
 * GuestName); it stays with the wish and is deleted with it.
 *
 * A song wished again while it is still open gets no second row: `wished`
 * counts on the existing entry, so the list stays one row per song and the
 * moderator still sees how popular it is. A unique key on (room_id, song_id)
 * holds that rule in the database rather than in the caller's timing, and
 * wish() is the one write that puts a song on the list.
 *
 * Every instance is bound to one room (room_id, 0 = default room): all
 * reading and writing stays inside that room's list.
 */
final class WishRepository
{
    private const TABLE = '`' . Schema::WISHES . '`';

    public function __construct(
        private readonly Database $db,
        private readonly int $roomId = RoomRepository::DEFAULT_ID,
    ) {
    }

    public function roomId(): int
    {
        return $this->roomId;
    }

    /**
     * Sortable columns of the wish list.
     * 'manual' is the order set by drag & drop and also the default -- it
     * matches the order of arrival at first.
     */
    public function sortableFields(): array
    {
        return [
            'manual' => 'position',
            'time'   => 'created_at',
            'artist' => 'artist',
            'title'  => 'title',
            'length' => 'length_sec',
            'genre'  => 'genre',
            'wisher' => 'wisher',
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public function all(string $sort = 'manual', string $dir = 'asc'): array
    {
        $column    = $this->sortableFields()[$sort] ?? 'position';
        $direction = strtolower($dir) === 'desc' ? 'DESC' : 'ASC';

        // Secondary key id: equal values stay in order of arrival.
        return $this->db->all(
            'SELECT * FROM ' . self::TABLE . " WHERE room_id = ? ORDER BY {$column} {$direction}, id ASC",
            [$this->roomId],
        );
    }

    /**
     * One page of the list in the given sorting, plus the total.
     *
     * @return array{rows: array<int,array<string,mixed>>, total: int}
     */
    public function page(string $sort, string $dir, int $page, int $perPage): array
    {
        $column    = $this->sortableFields()[$sort] ?? 'position';
        $direction = strtolower($dir) === 'desc' ? 'DESC' : 'ASC';
        $offset    = max(0, ($page - 1) * $perPage);

        $rows = $this->db->all(
            'SELECT * FROM ' . self::TABLE . " WHERE room_id = ? ORDER BY {$column} {$direction}, id ASC LIMIT {$perPage} OFFSET {$offset}",
            [$this->roomId],
        );

        return ['rows' => $rows, 'total' => $this->count()];
    }

    public function count(): int
    {
        return (int) ($this->db->one('SELECT COUNT(*) AS c FROM ' . self::TABLE . ' WHERE room_id = ?', [$this->roomId])['c'] ?? 0);
    }

    /**
     * Which of these songs are on the room's list right now.
     *
     * One query for a whole page of the repertoire, so the list can show
     * before it is pressed what the message would otherwise say afterwards.
     * Answers an empty list for an empty question, which is what a page
     * without songs asks.
     *
     * @param array<int,int> $songIds
     * @return array<int,bool> song id => true, for the ones that are open
     */
    public function pendingAmong(array $songIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $songIds))));
        if ($ids === []) {
            return [];
        }

        $marks = implode(', ', array_fill(0, count($ids), '?'));
        $rows  = $this->db->all(
            'SELECT DISTINCT song_id FROM ' . self::TABLE . " WHERE room_id = ? AND song_id IN ({$marks})",
            [$this->roomId, ...$ids],
        );

        $open = [];
        foreach ($rows as $row) {
            $open[(int) $row['song_id']] = true;
        }

        return $open;
    }

    /** Is this song already on the list? */
    public function isPending(int $songId): bool
    {
        return $this->db->one(
            'SELECT id FROM ' . self::TABLE . ' WHERE room_id = ? AND song_id = ? LIMIT 1',
            [$this->roomId, $songId],
        ) !== null;
    }

    /**
     * Put a song on the wish list: a new entry, or one more count on the
     * entry that is already there.
     *
     * One statement decides which of the two it is. The unique key on
     * (room_id, song_id) makes a second row for the same song impossible,
     * and ON DUPLICATE KEY UPDATE turns the attempt into the count-up the
     * application means by it -- so two wishes for the same song arriving
     * together end as one entry wished twice, whatever order they reach the
     * database in. Looking first and writing afterwards could not promise
     * that: between the look and the write another request fits.
     *
     * The wisher of the first wish stays; a repeat wish does not overwrite
     * the name, exactly as counting up did before.
     *
     * $maxOpen is the cap on open entries, 0 for none. It applies to a new
     * entry only -- a count-up adds no row -- and it is applied *after* the
     * insert, by taking the row back out again when the list turned out to
     * be full: a cap that is read before the insert can be passed by two
     * requests at once, and locking the room's rows for every wish would
     * trade that for deadlocks on the one path that has to stay quick.
     *
     * @param array<string,mixed> $song   row from SongRepository
     * @param string|null         $wisher the guest's name for the list, if given
     * @return array{added: bool, full: bool, id: int, wished: int}
     */
    public function wish(array $song, ?string $wisher = null, int $maxOpen = 0): array
    {
        $table = self::TABLE;

        // Timestamp from PHP rather than NOW() on purpose: display and
        // "x minutes ago" then agree even when PHP and MySQL run in different
        // time zones (typical for separate containers).
        $affected = $this->db->exec(
            "INSERT INTO {$table} (song_id, artist, title, length_sec, genre, wisher, created_at, room_id, position, wished)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, (SELECT * FROM (SELECT COALESCE(MAX(position), 0) + 1 FROM {$table} WHERE room_id = ?) AS next_pos), 1)
             ON DUPLICATE KEY UPDATE wished = wished + 1",
            [
                (int) $song['id'],
                (string) $song['artist'],
                (string) $song['title'],
                $song['length_sec'] !== null ? (int) $song['length_sec'] : null,
                $song['genre'] !== null && $song['genre'] !== '' ? (string) $song['genre'] : null,
                $wisher !== null && $wisher !== '' ? $wisher : null,
                date('Y-m-d H:i:s'),
                $this->roomId,
                $this->roomId,
            ],
        );

        // MySQL answers 1 for a row it inserted and 2 for one it updated.
        $added = $affected === 1;

        if ($added && $maxOpen > 0 && $this->count() > $maxOpen) {
            $this->db->exec("DELETE FROM {$table} WHERE id = ? LIMIT 1", [(int) $this->db->pdo()->lastInsertId()]);

            return ['added' => false, 'full' => true, 'id' => 0, 'wished' => 0];
        }

        if ($added) {
            return ['added' => true, 'full' => false, 'id' => (int) $this->db->pdo()->lastInsertId(), 'wished' => 1];
        }

        // Counted up: the row carries the new number.
        $row = $this->db->one(
            "SELECT id, wished FROM {$table} WHERE room_id = ? AND song_id = ? LIMIT 1",
            [$this->roomId, (int) $song['id']],
        );

        return [
            'added'  => false,
            'full'   => false,
            'id'     => (int) ($row['id'] ?? 0),
            'wished' => (int) ($row['wished'] ?? 1),
        ];
    }

    /**
     * Store a new order (drag & drop). Only ids that actually exist in the
     * table are considered. The ids passed may be the whole list or one
     * page of it: they take, in their new order, the places those same
     * entries held before, so every other entry -- on another page, or
     * arrived in the meantime -- keeps its place.
     *
     * @param array<int,int> $orderedIds
     * @return int number of repositioned entries
     */
    public function reorder(array $orderedIds): int
    {
        $known = array_map('intval', array_column(
            $this->db->all('SELECT id FROM ' . self::TABLE . ' WHERE room_id = ? ORDER BY position ASC, id ASC', [$this->roomId]),
            'id',
        ));

        $ordered = self::placed($known, $orderedIds);
        if ($ordered === []) {
            return 0;
        }

        $pdo = $this->db->pdo();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('UPDATE ' . self::TABLE . ' SET position = ? WHERE id = ? AND room_id = ?');
            foreach ($ordered as $index => $id) {
                $stmt->execute([$index + 1, $id, $this->roomId]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return count($ordered);
    }

    /**
     * The whole list in its new order: $moved (a page of it, or all of it,
     * in the order wanted) fills the places its entries held in $known;
     * everything else stays where it was. Unknown and repeated ids are
     * dropped. Empty when nothing of $moved is known.
     *
     * @param array<int,int> $known    every id, in the stored order
     * @param array<int,int|string> $moved
     * @return array<int,int>
     */
    public static function placed(array $known, array $moved): array
    {
        $wanted = [];
        foreach ($moved as $id) {
            $id = (int) $id;
            if (in_array($id, $known, true) && !in_array($id, $wanted, true)) {
                $wanted[] = $id;
            }
        }
        if ($wanted === []) {
            return [];
        }

        // Every place a wanted id holds is a slot; the wanted ids fill the
        // slots in their new order, one after the other.
        $slots  = array_fill_keys($wanted, true);
        $queue  = $wanted;
        $result = [];
        foreach ($known as $id) {
            $result[] = isset($slots[$id]) ? array_shift($queue) : $id;
        }

        return $result;
    }

    /**
     * Move an entry one position up (-1) or down (+1). The keyboard route to
     * the same ordering that drag & drop offers with the mouse.
     */
    public function move(int $id, int $direction): bool
    {
        $ids = array_map('intval', array_column($this->all('manual', 'asc'), 'id'));
        $at  = array_search($id, $ids, true);

        if ($at === false) {
            return false;
        }

        $target = $at + ($direction < 0 ? -1 : 1);
        if ($target < 0 || $target >= count($ids)) {
            return false;
        }

        [$ids[$at], $ids[$target]] = [$ids[$target], $ids[$at]];
        $this->reorder($ids);

        return true;
    }

    /** Move an entry to the very top or the very bottom of the list. */
    public function moveToEnd(int $id, bool $top): bool
    {
        $ids = array_map('intval', array_column($this->all('manual', 'asc'), 'id'));
        $at  = array_search($id, $ids, true);

        if ($at === false) {
            return false;
        }

        array_splice($ids, $at, 1);
        $top ? array_unshift($ids, $id) : array_push($ids, $id);
        $this->reorder($ids);

        return true;
    }

    public function delete(int $id): bool
    {
        return $this->db->exec('DELETE FROM ' . self::TABLE . ' WHERE id = ? AND room_id = ?', [$id, $this->roomId]) > 0;
    }

    public function deleteAll(): int
    {
        return $this->db->exec('DELETE FROM ' . self::TABLE . ' WHERE room_id = ?', [$this->roomId]);
    }
}
