<?php

declare(strict_types=1);

namespace Songwunsch;

/**
 * Small key/value store in the `settings` table for state that must outlive
 * sessions and requests: the moderator's pause switch, the rotating secrets
 * of the wish guard, the live logo, and what the admins set in the
 * Administration menu -- the colours (Colors, `colors.*`), the message
 * duration and polling intervals (Ui, `ui.*`) and the wish limits (Limits,
 * `limits.*`).
 */
final class Settings
{
    private const TABLE = '`' . Schema::SETTINGS . '`';

    /** Id of the logo the header shows (uploads.id); '0' or absent = the word mark. */
    public const LOGO_ID = 'logo_id';

    /**
     * The operator's own footer line (credits, a link), one entry per
     * language below this prefix ('footer_html.de'), HTML reduced by
     * Html::clean(); a language without an entry falls back like a page
     * (PageRepository). FOOTER_HTML is the key of the one line before the
     * languages -- read as long as no language has a line, dropped on the
     * next save.
     */
    public const FOOTER_HTML_PREFIX = 'footer_html.';
    public const FOOTER_HTML        = 'footer_html';

    /** What a user can switch the delete confirmation off for -- each an area of Security::can(). */
    public const CONFIRM_DELETE = ['songs', 'suggestions', 'wishes', 'rooms'];

    /**
     * What was read already, for the rest of the request: name => value, or
     * null for a name the table does not have. The same entries are asked for
     * several times while a page is built -- the room's open/closed switch
     * once for the header notice and once for the live token, the main room's
     * name, the colours -- and the live-update poll is almost nothing but
     * such reads. Every write empties the cache again, so a value that was
     * just saved is never served from it.
     *
     * @var array<string,?string>
     */
    private array $cache = [];

    /** @var array<string,array<string,string>> the same for withPrefix() */
    private array $groups = [];

    public function __construct(private readonly Database $db)
    {
    }

    /**
     * A write went through: forget what was read and ring the doorbell for
     * the open pages (LiveSignal). Everything an open page watches -- the
     * room's open/closed switch, the revision counters of the catalogue, the
     * wish lists and the suggestions -- is a row of this table, so this one
     * place catches every change; a write that moves no token at most costs
     * one page a single ?poll=1 that finds nothing.
     *
     * $rows is what the statement really changed; a statement that changed
     * nothing must not ring. The case that matters is the DELETE that matched
     * no row: the wish guard sweeps the old daily secrets on every single
     * request, and without this the doorbell would ring for every visitor all
     * evening and the pages would be back to asking PHP every time. Saving a
     * value that stood already does ring, because updated_at moves with it --
     * that is an admin pressing Save, not a hot path.
     */
    private function changed(int $rows): void
    {
        if ($rows < 1) {
            return;
        }
        $this->cache  = [];
        $this->groups = [];
        LiveSignal::touch();
    }

    public function get(string $name, ?string $default = null): ?string
    {
        if (!array_key_exists($name, $this->cache)) {
            $row = $this->db->one('SELECT value FROM ' . self::TABLE . ' WHERE name = ?', [$name]);
            $this->cache[$name] = $row === null ? null : (string) $row['value'];
        }

        return $this->cache[$name] ?? $default;
    }

    /**
     * Read several entries in one query and keep them for the rest of the
     * request. The live-update poll is nothing but four such reads (the
     * room's open/closed switch and three revision counters), and four
     * round trips for four small rows of one table are three too many.
     * Names that the table does not have are remembered as missing, so the
     * get() that follows does not go looking for them either.
     *
     * @param array<int,string> $names
     */
    public function prefetch(array $names): void
    {
        $wanted = array_values(array_unique(array_filter($names, fn (string $n): bool => !array_key_exists($n, $this->cache))));
        if ($wanted === []) {
            return;
        }

        $rows = $this->db->all(
            'SELECT name, value FROM ' . self::TABLE . ' WHERE name IN (' . implode(', ', array_fill(0, count($wanted), '?')) . ')',
            $wanted,
        );

        foreach ($wanted as $name) {
            $this->cache[$name] = null;
        }
        foreach ($rows as $row) {
            $this->cache[(string) $row['name']] = (string) $row['value'];
        }
    }

    /**
     * Every entry whose name starts with the prefix, the prefix stripped:
     * 'colors.accent' => '#e6b450' becomes 'accent' => '#e6b450'. One query
     * for a whole group of values (the colours, the limits).
     *
     * @return array<string,string>
     */
    public function withPrefix(string $prefix): array
    {
        if (isset($this->groups[$prefix])) {
            return $this->groups[$prefix];
        }

        $rows = $this->db->all(
            'SELECT name, value FROM ' . self::TABLE . ' WHERE name LIKE ?',
            [str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $prefix) . '%'],
        );

        $out = [];
        foreach ($rows as $row) {
            $out[substr((string) $row['name'], strlen($prefix))] = (string) $row['value'];
        }

        return $this->groups[$prefix] = $out;
    }

    public function set(string $name, string $value): void
    {
        // MySQL counts an INSERT ... ON DUPLICATE KEY UPDATE as 1 for a new
        // row, 2 for one it really changed and 0 when the value stood
        // already -- exactly what changed() wants to know.
        $rows = $this->db->exec(
            'INSERT INTO ' . self::TABLE . ' (name, value, updated_at) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = VALUES(updated_at)',
            [$name, $value, date('Y-m-d H:i:s')],
        );
        $this->changed($rows);
    }

    /**
     * Raise a counter by one, creating it as 1. Done in the database, so two
     * concurrent changes never end up with the same number -- the revision
     * counters of the wish lists and the suggestions rely on that. Only the
     * difference between two readings matters, never the height, so the
     * counter wraps around at a million and stays a short number for good.
     */
    public function increment(string $name): void
    {
        $rows = $this->db->exec(
            'INSERT INTO ' . self::TABLE . " (name, value, updated_at) VALUES (?, '1', ?)
             ON DUPLICATE KEY UPDATE value = (CAST(value AS UNSIGNED) + 1) % 1000000, updated_at = VALUES(updated_at)",
            [$name, date('Y-m-d H:i:s')],
        );
        $this->changed($rows);
    }

    /**
     * Create a value only if it does not exist yet. Two concurrent calls thus
     * agree on the same value -- important for secrets.
     *
     * No doorbell here: this runs on every request for the day's secret and
     * writes nothing almost every time, so ringing would signal a change that
     * did not happen. The read afterwards must not come from the cache
     * either, in case another request created the row meanwhile.
     */
    public function setIfMissing(string $name, string $value): string
    {
        $this->db->exec(
            'INSERT IGNORE INTO ' . self::TABLE . ' (name, value, updated_at) VALUES (?, ?, ?)',
            [$name, $value, date('Y-m-d H:i:s')],
        );
        unset($this->cache[$name]);

        return (string) $this->get($name, $value);
    }

    /**
     * Does deleting a single song, suggestion, wish or room ask this user
     * for confirmation? On by default; every user switches it off per kind
     * under Settings, for their own account only. Bulk actions (clear the
     * wish list or the suggestions) always ask. Without a user (id 0) the
     * answer is always yes.
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
        $this->changed($this->db->exec('DELETE FROM ' . self::TABLE . ' WHERE name = ?', [$name]));
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

        $this->changed($this->db->exec($sql, $params));
    }
}
