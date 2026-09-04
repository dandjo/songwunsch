<?php

declare(strict_types=1);

namespace Songwunsch;

/**
 * Protects public wishing against bots and flooding -- in layers:
 *
 *  1. Global limits: open wishes in total and wishes per minute across all
 *     visitors. Bounds the damage regardless of the source.
 *  2. Per-sender limit without a plain IP: the key is an HMAC of the IP
 *     address with a secret that changes daily. Entries in `wish_throttle`
 *     live for an hour, the secret for a day -- after that nothing can be
 *     attributed to a person any more (GDPR: pseudonymisation, storage
 *     limitation).
 *  3. Bot hurdles in the form: an invisible honeypot field that humans leave
 *     empty, and a signed timestamp that rejects submissions made within a
 *     few seconds of the page load. No third-party service.
 *
 * Plus the moderator's pause switch, which closes wishing entirely -- per
 * room: the default room keeps the original key, every other room its own.
 *
 * The per-session cooldown (Security::throttled) stays in place as well.
 */
final class WishGuard
{
    private const THROTTLE      = '`' . Schema::THROTTLE . '`';
    private const PAUSED_KEY    = 'wishes_paused'; // + ':<room id>' for rooms other than the default
    private const REVISION_KEY  = 'wishes_rev';    // + ':<room id>' -- counts every change of the room's wish list
    private const PAUSED_ALL    = 'wishes_paused_all'; // JSON {room id: 0|1}: the states before the admin paused everywhere
    private const SECRET_KEY    = 'secret:'; // + date, e.g. secret:2026-09-02
    private const KEEP_SECONDS  = 3600;      // lifetime of the sender entries
    private const TOKEN_MAX_AGE = 6 * 3600;  // a form older than this must be reloaded

    public const CHECK_OK    = 'ok';
    public const CHECK_BOT   = 'bot';   // honeypot filled or bad signature
    public const CHECK_FAST  = 'fast';  // too soon after the page load
    public const CHECK_STALE = 'stale'; // form too old

    /** @var array<string,int> */
    private readonly array $limits;

    private ?string $sender = null;

    /**
     * @param array<string,int> $limits per_minute_total, per_minute_sender,
     *                                  per_hour_sender, max_open -- 0 disables
     *                                  the respective limit
     */
    public function __construct(
        private readonly Database $db,
        private readonly Settings $settings,
        array $limits,
        private readonly bool $trustProxy,
        private readonly int $minFormSeconds,
        private readonly int $roomId = RoomRepository::DEFAULT_ID,
    ) {
        $this->limits = [
            'per_minute_total'  => max(0, (int) ($limits['per_minute_total'] ?? 0)),
            'per_minute_sender' => max(0, (int) ($limits['per_minute_sender'] ?? 0)),
            'per_hour_sender'   => max(0, (int) ($limits['per_hour_sender'] ?? 0)),
            'max_open'          => max(0, (int) ($limits['max_open'] ?? 0)),
        ];
    }

    // ---- Pause ------------------------------------------------------------

    public function isPaused(): bool
    {
        return $this->pausedIn($this->roomId);
    }

    /** Is another room closed? For the room list, which shows every room's state. */
    public function pausedIn(int $roomId): bool
    {
        return $this->settings->get(self::pausedKeyFor($roomId), '0') === '1';
    }

    public function setPaused(bool $paused): void
    {
        $this->settings->set($this->pausedKey(), $paused ? '1' : '0');
        $this->touch();
    }

    // ---- Revision ---------------------------------------------------------

    /**
     * Revision of the room's wish list: a counter that every change raises
     * -- a wish coming in, deleted, moved, the list cleared, the room closed
     * or opened. Open wish-list pages poll it (app.js) and reload the list
     * when it moved on, so everyone sees the same order without refreshing.
     */
    public function revision(): int
    {
        return (int) $this->settings->get(self::REVISION_KEY . ($this->roomId === RoomRepository::DEFAULT_ID ? '' : ':' . $this->roomId), '0');
    }

    /** The wish list changed: raise the revision. */
    public function touch(): void
    {
        self::touchRoom($this->settings, $this->roomId);
    }

    private static function touchRoom(Settings $settings, int $roomId): void
    {
        $settings->increment(self::REVISION_KEY . ($roomId === RoomRepository::DEFAULT_ID ? '' : ':' . $roomId));
    }

    /**
     * Close wishing in the main room and in every room listed at once -- the
     * admin's switch on the room list. Archived rooms are included. The state
     * each room had before is remembered so resumeEverywhere() can hand it
     * back: a room the moderator had paused on purpose stays paused then.
     *
     * @param array<int,int> $roomIds ids of all rooms besides the main room
     */
    public function pauseEverywhere(array $roomIds): void
    {
        $before = [];
        foreach ([RoomRepository::DEFAULT_ID, ...$roomIds] as $roomId) {
            $key = self::pausedKeyFor((int) $roomId);
            $before[(string) $roomId] = $this->settings->get($key, '0') === '1' ? 1 : 0;
            $this->settings->set($key, '1');
            self::touchRoom($this->settings, (int) $roomId);
        }

        // Pressed twice: keep the first snapshot, it holds the real states.
        if (!$this->isPausedEverywhere()) {
            $this->settings->set(self::PAUSED_ALL, (string) json_encode($before));
        }
    }

    /**
     * Take the admin's pause back: every room returns to the state it had
     * before. Rooms created meanwhile are not in the snapshot and stay as
     * they are; rooms deleted meanwhile are simply skipped.
     *
     * @param array<int,int> $roomIds ids of all rooms besides the main room
     */
    public function resumeEverywhere(array $roomIds): void
    {
        $before = json_decode((string) $this->settings->get(self::PAUSED_ALL, '{}'), true);
        $before = is_array($before) ? $before : [];

        foreach ([RoomRepository::DEFAULT_ID, ...$roomIds] as $roomId) {
            if (array_key_exists((string) $roomId, $before)) {
                $this->settings->set(self::pausedKeyFor((int) $roomId), (int) $before[(string) $roomId] === 1 ? '1' : '0');
                self::touchRoom($this->settings, (int) $roomId);
            }
        }

        $this->settings->delete(self::PAUSED_ALL);
    }

    /** Is the admin's pause of every room in force? Decides what the switch offers. */
    public function isPausedEverywhere(): bool
    {
        return $this->settings->get(self::PAUSED_ALL) !== null;
    }

    private function pausedKey(): string
    {
        return self::pausedKeyFor($this->roomId);
    }

    /**
     * A deleted room takes its pause switch and its revision counter along,
     * so nothing of it lingers in the settings.
     */
    public function forgetRoom(int $roomId): void
    {
        if ($roomId === RoomRepository::DEFAULT_ID) {
            return;
        }
        $this->settings->delete(self::pausedKeyFor($roomId));
        $this->settings->delete(self::REVISION_KEY . ':' . $roomId);
    }

    private static function pausedKeyFor(int $roomId): string
    {
        return $roomId === RoomRepository::DEFAULT_ID ? self::PAUSED_KEY : self::PAUSED_KEY . ':' . $roomId;
    }

    // ---- Form hurdles -----------------------------------------------------

    /**
     * Signed timestamp for the wish form. Only the server knows the secret,
     * so a bot cannot produce the value itself -- it has to load the page
     * and then wait.
     */
    public function formToken(): string
    {
        $issued = (string) time();

        return $issued . '.' . $this->sign($issued, date('Y-m-d'));
    }

    /** @return string one of the CHECK_* constants */
    public function checkForm(?string $token, ?string $honeypot): string
    {
        if ($honeypot !== null && $honeypot !== '') {
            return self::CHECK_BOT;
        }

        [$issued, $signature] = array_pad(explode('.', (string) $token, 2), 2, '');
        if ($issued === '' || !ctype_digit($issued) || $signature === '') {
            return self::CHECK_BOT;
        }

        // Accept today's and yesterday's secret so a form opened around
        // midnight is not rejected.
        $valid = hash_equals($this->sign($issued, date('Y-m-d')), $signature)
            || hash_equals($this->sign($issued, date('Y-m-d', time() - 86400)), $signature);

        if (!$valid) {
            return self::CHECK_BOT;
        }

        $age = time() - (int) $issued;

        if ($age > self::TOKEN_MAX_AGE) {
            return self::CHECK_STALE;
        }

        return $age < $this->minFormSeconds ? self::CHECK_FAST : self::CHECK_OK;
    }

    // ---- Limits -----------------------------------------------------------

    /**
     * Check every limit. Returns the message for the visitor, or null when
     * the wish may pass.
     */
    public function limitReached(int $openWishes): ?string
    {
        $l = $this->limits;

        if ($l['max_open'] > 0 && $openWishes >= $l['max_open']) {
            return t('The wish list is full – please try again later.');
        }

        if ($l['per_minute_total'] > 0 && $this->countSince(60) >= $l['per_minute_total']) {
            return t('A lot of wishes are coming in right now – please try again in a moment.');
        }

        if ($l['per_minute_sender'] > 0 && $this->countSince(60, $this->sender()) >= $l['per_minute_sender']) {
            return t('Enough wishes for the moment – please wait a little.');
        }

        if ($l['per_hour_sender'] > 0 && $this->countSince(3600, $this->sender()) >= $l['per_hour_sender']) {
            return t('Quite a few wishes have come from you this hour – please come back later.');
        }

        return null;
    }

    /** Record an accepted wish and clean up old entries. */
    public function record(): void
    {
        $this->db->exec(
            'INSERT INTO ' . self::THROTTLE . ' (sender, created_at) VALUES (?, ?)',
            [$this->sender(), date('Y-m-d H:i:s')],
        );
        $this->db->exec(
            'DELETE FROM ' . self::THROTTLE . ' WHERE created_at < ?',
            [date('Y-m-d H:i:s', time() - self::KEEP_SECONDS)],
        );
    }

    private function countSince(int $seconds, ?string $sender = null): int
    {
        $sql    = 'SELECT COUNT(*) AS c FROM ' . self::THROTTLE . ' WHERE created_at >= ?';
        $params = [date('Y-m-d H:i:s', time() - $seconds)];

        if ($sender !== null) {
            $sql     .= ' AND sender = ?';
            $params[] = $sender;
        }

        return (int) ($this->db->one($sql, $params)['c'] ?? 0);
    }

    // ---- Sender and secrets -------------------------------------------------

    /** Pseudonym of the sender: HMAC of the IP with the daily secret. */
    private function sender(): string
    {
        return $this->sender ??= hash_hmac('sha256', $this->clientIp(), $this->secret(date('Y-m-d')));
    }

    /**
     * The visitor's IP. Behind a reverse proxy it is the last entry of
     * X-Forwarded-For -- the proxy appends that one itself, everything before
     * it can be made up by the client. Without a trusted proxy only
     * REMOTE_ADDR counts.
     */
    private function clientIp(): string
    {
        $remote = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));

        if ($this->trustProxy) {
            $chain = array_map('trim', explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '')));
            $last  = (string) end($chain);
            if ($last !== '' && filter_var($last, FILTER_VALIDATE_IP) !== false) {
                return $last;
            }
        }

        return $remote !== '' ? $remote : 'unknown';
    }

    private function sign(string $payload, string $day): string
    {
        return hash_hmac('sha256', $payload, $this->secret($day));
    }

    /**
     * Daily secret from the `settings` table; created on the first access of
     * the day, anything older than yesterday's is removed.
     */
    private function secret(string $day): string
    {
        /** @var array<string,string> $cache */
        static $cache = [];

        if (isset($cache[$day])) {
            return $cache[$day];
        }

        $name   = self::SECRET_KEY . $day;
        $secret = $this->settings->setIfMissing($name, bin2hex(random_bytes(32)));

        if ($day === date('Y-m-d')) {
            $this->settings->deleteByPrefixExcept(self::SECRET_KEY, [
                $name,
                self::SECRET_KEY . date('Y-m-d', time() - 86400),
            ]);
        }

        return $cache[$day] = $secret;
    }
}
