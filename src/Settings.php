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
     * What the light design shows instead. Three states, and the difference
     * between two of them matters: **no entry at all** means it follows the
     * dark design, whatever that becomes later; `'0'` means the word mark,
     * chosen for the light design and left alone when a logo goes live in
     * the dark one; an id means that logo. A logo drawn in pale lettering
     * for the dark ground disappears on a white one, so an operator either
     * has a second version or would rather show the word mark there.
     */
    public const LOGO_ID_LIGHT = 'logo_id_light';

    /**
     * The operator's own footer line (credits, a link), one entry per
     * language below this prefix ('footer_html.de'), HTML reduced by
     * Html::clean(); a language without an entry falls back like a page
     * (PageRepository).
     */
    public const FOOTER_HTML_PREFIX = 'footer_html.';

    /** What a user can switch the delete confirmation off for -- each an area of Security::can(). */
    public const CONFIRM_DELETE = ['songs', 'suggestions', 'wishes', 'rooms'];

    /**
     * The whole table, for the rest of the request: name => value.
     *
     * Read in one query on the first access rather than key by key. This is
     * a small table by construction -- one row per switch, per counter and
     * per value the admins set, two per room, a handful per user; a live
     * installation has some 40 rows and about a kilobyte in them. Building
     * one page used to cost eleven queries against it (five prefix scans,
     * five single keys and one batch), because the values are read from all
     * over: the main room's name and the start room while the room is being
     * worked out, the limits, the interface values, the revision counters,
     * the footer, the language order, the logo, the colours. One query for
     * all of it beats eleven for parts of it, and no caller has to know
     * which values it will need.
     *
     * Every write empties this again, so a value that was just saved is
     * never served from it.
     *
     * @var array<string,string>
     */
    private array $cache = [];

    /** Is $cache the whole table? Reset by every write, see changed(). */
    private bool $loaded = false;

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
        $this->loaded = false;
        LiveSignal::touch();
    }

    public function get(string $name, ?string $default = null): ?string
    {
        $this->load();

        return $this->cache[$name] ?? $default;
    }

    /**
     * Read the table, once per request. Everything below this line answers
     * from memory afterwards -- see $cache for why that is the cheaper way
     * round.
     */
    private function load(): void
    {
        if ($this->loaded) {
            return;
        }
        $this->loaded = true;

        foreach ($this->db->all('SELECT name, value FROM ' . self::TABLE) as $row) {
            $this->cache[(string) $row['name']] = (string) $row['value'];
        }
    }

    /**
     * Every entry whose name starts with the prefix, the prefix stripped:
     * 'colors.accent' => '#e6b450' becomes 'accent' => '#e6b450' -- a whole
     * group of values at once (the colours, the limits, the interface). Out
     * of the table this already holds, so it costs no query of its own.
     *
     * @return array<string,string>
     */
    public function withPrefix(string $prefix): array
    {
        $this->load();

        $out = [];
        foreach ($this->cache as $name => $value) {
            if (str_starts_with($name, $prefix)) {
                $out[substr($name, strlen($prefix))] = $value;
            }
        }

        return $out;
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

        // Past the cache on purpose: another request may have created the
        // row a moment ago, and the value that won is the one to return.
        $row   = $this->db->one('SELECT value FROM ' . self::TABLE . ' WHERE name = ?', [$name]);
        $found = $row === null ? $value : (string) $row['value'];

        return $this->cache[$name] = $found;
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
