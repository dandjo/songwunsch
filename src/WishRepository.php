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
 * counts on the existing entry (wishAgain()), so the list stays one row per
 * song and the moderator still sees how popular it is.
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

    public function count(): int
    {
        return (int) ($this->db->one('SELECT COUNT(*) AS c FROM ' . self::TABLE . ' WHERE room_id = ?', [$this->roomId])['c'] ?? 0);
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
     * The song is wished once more while it is already on the list: no new
     * row, the existing one counts the wish. Should the song be on the list
     * more than once (rows from before duplicates were folded), the count goes
     * to the entry that is played first -- lowest position, then the oldest.
     *
     * @return array<string,mixed>|null the updated row, null if the song is not open
     */
    public function wishAgain(int $songId): ?array
    {
        $row = $this->db->one(
            'SELECT * FROM ' . self::TABLE . ' WHERE room_id = ? AND song_id = ? ORDER BY position ASC, id ASC LIMIT 1',
            [$this->roomId, $songId],
        );
        if ($row === null) {
            return null;
        }

        $this->db->exec('UPDATE ' . self::TABLE . ' SET wished = wished + 1 WHERE id = ?', [(int) $row['id']]);
        $row['wished'] = (int) $row['wished'] + 1;

        return $row;
    }

    /**
     * Append a wish at the bottom of the list.
     *
     * @param array<string,mixed> $song   row from SongRepository
     * @param string|null         $wisher the guest's name for the list, if given
     * @return int the new wish's id
     */
    public function add(array $song, ?string $wisher = null): int
    {
        $table = self::TABLE;

        // Timestamp from PHP rather than NOW() on purpose: display and
        // "x minutes ago" then agree even when PHP and MySQL run in different
        // time zones (typical for separate containers).
        $this->db->exec(
            "INSERT INTO {$table} (song_id, artist, title, length_sec, genre, wisher, created_at, room_id, position, wished)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, (SELECT * FROM (SELECT COALESCE(MAX(position), 0) + 1 FROM {$table} WHERE room_id = ?) AS next_pos), 1)",
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

        return (int) $this->db->pdo()->lastInsertId();
    }

    /**
     * Store a new order (drag & drop). Only ids that actually exist in the
     * table are considered; anything not passed slides in behind them.
     *
     * @param array<int,int> $orderedIds
     * @return int number of repositioned entries
     */
    public function reorder(array $orderedIds): int
    {
        $known = array_map('intval', array_column(
            $this->db->all('SELECT id FROM ' . self::TABLE . ' WHERE room_id = ?', [$this->roomId]),
            'id',
        ));

        $ordered = [];
        foreach ($orderedIds as $id) {
            $id = (int) $id;
            if (in_array($id, $known, true) && !in_array($id, $ordered, true)) {
                $ordered[] = $id;
            }
        }

        if ($ordered === []) {
            return 0;
        }

        // Entries not passed (e.g. arrived in the meantime) keep their order
        // and end up at the bottom.
        foreach ($known as $id) {
            if (!in_array($id, $ordered, true)) {
                $ordered[] = $id;
            }
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
