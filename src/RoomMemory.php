<?php

declare(strict_types=1);

namespace Songwunsch;

/**
 * Remembers the room a visitor chose last.
 *
 * The room is part of the address (/rooms/<slug>); pages outside a room
 * (/rooms, /users, /settings, ...) carry none. Without help the header and
 * every link there would fall back to the main room, and a visitor coming
 * back tomorrow would land in the main room as well. So the slug of the
 * last room visited lives in a cookie (`songwunsch_room`, one year):
 *
 *  - a page inside a room writes it;
 *  - pages without a room context read it, so header, room switcher and
 *    the Songs / Wish list / Suggestions tabs stay in that room;
 *  - a room-bound address that names no room (the bare /, /wishes,
 *    /suggestions) redirects into the remembered room -- every time, so a
 *    bookmark or a typed address never drops the visitor out of their room;
 *  - the main room is chosen explicitly: the room switcher and the room
 *    list post the `room_switch` action, which remembers the main room as
 *    MAIN. Only that, or an address naming another room, changes the
 *    memory.
 *  - without any memory (a first visit) the bare addresses lead into the
 *    start room the editors set under Rooms, if any -- see index.php.
 *
 * A second cookie (`songwunsch_rooms`, one year) keeps the unlisted rooms a
 * guest has entered through their address: an unlisted room is offered in
 * the room switcher to nobody, so once the guest switches away the link or
 * QR code would be the only way back. The switcher shows these rooms under
 * "Your rooms" -- the five most recent ones; index.php drops rooms that are
 * gone, archived or listed by now.
 *
 * Both cookies hold nothing but rooms' machine names (or the MAIN mark):
 * no personal data.
 */
final class RoomMemory
{
    public const COOKIE = 'songwunsch_room';

    /** The unlisted rooms entered through their address, most recent first. */
    public const VISITED_COOKIE = 'songwunsch_rooms';

    /** How many unlisted rooms are kept; the oldest one drops out. */
    public const VISITED_MAX = 5;

    /** Cookie value for "the main room was chosen" -- no slug can look like this. */
    private const MAIN = '-';

    /**
     * @param string $cookiePath scope of the cookie, e.g. '/songliste/' --
     *                           always with a trailing slash, like the session.
     */
    public function __construct(
        private readonly string $cookiePath,
        private readonly bool $secure,
    ) {
    }

    /**
     * What is remembered: a room's slug, '' for the main room chosen on
     * purpose, null when nothing is remembered or the value is no slug at
     * all.
     */
    public function slug(): ?string
    {
        $raw = $_COOKIE[self::COOKIE] ?? null;
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        if ($raw === self::MAIN) {
            return '';
        }

        return preg_match(RoomRepository::SLUG_PATTERN, $raw) === 1 ? $raw : null;
    }

    /** Remember a room for a year; '' remembers the main room. */
    public function remember(string $slug): void
    {
        if ($this->slug() === $slug) {
            return;
        }
        $value = $slug === '' ? self::MAIN : $slug;
        $this->write(self::COOKIE, $value, time() + 365 * 86400);
        $_COOKIE[self::COOKIE] = $value;
    }

    /** Drop the memory -- the room it named is gone. */
    public function forget(): void
    {
        if (!isset($_COOKIE[self::COOKIE])) {
            return;
        }
        $this->write(self::COOKIE, '', time() - 42000);
        unset($_COOKIE[self::COOKIE]);
    }

    /**
     * The unlisted rooms the guest has entered, most recent first -- slugs
     * only; anything in the cookie that is no slug is ignored.
     *
     * @return list<string>
     */
    public function visited(): array
    {
        $raw = $_COOKIE[self::VISITED_COOKIE] ?? null;
        if (!is_string($raw) || $raw === '') {
            return [];
        }
        $slugs = [];
        foreach (explode(',', $raw) as $slug) {
            if (preg_match(RoomRepository::SLUG_PATTERN, $slug) === 1 && !in_array($slug, $slugs, true)) {
                $slugs[] = $slug;
            }
        }

        return array_slice($slugs, 0, self::VISITED_MAX);
    }

    /** Put a room at the front of the visited rooms; the oldest drops out. */
    public function noteVisit(string $slug): void
    {
        $slugs = $this->visited();
        if (($slugs[0] ?? null) === $slug) {
            return;
        }
        $slugs = array_values(array_filter($slugs, static fn (string $s): bool => $s !== $slug));
        array_unshift($slugs, $slug);
        $this->storeVisited(array_slice($slugs, 0, self::VISITED_MAX));
    }

    /**
     * Keep only these of the visited rooms -- the others are gone, archived
     * or listed by now. Writes the cookie only when something changes.
     *
     * @param list<string> $slugs
     */
    public function pruneVisited(array $slugs): void
    {
        $kept = array_values(array_filter($this->visited(), static fn (string $s): bool => in_array($s, $slugs, true)));
        if ($kept !== $this->visited()) {
            $this->storeVisited($kept);
        }
    }

    /** @param list<string> $slugs */
    private function storeVisited(array $slugs): void
    {
        if ($slugs === []) {
            if (isset($_COOKIE[self::VISITED_COOKIE])) {
                $this->write(self::VISITED_COOKIE, '', time() - 42000);
                unset($_COOKIE[self::VISITED_COOKIE]);
            }

            return;
        }
        $value = implode(',', $slugs);
        $this->write(self::VISITED_COOKIE, $value, time() + 365 * 86400);
        $_COOKIE[self::VISITED_COOKIE] = $value;
    }

    private function write(string $name, string $value, int $expires): void
    {
        setcookie($name, $value, [
            'expires'  => $expires,
            'path'     => $this->cookiePath,
            'secure'   => $this->secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}
