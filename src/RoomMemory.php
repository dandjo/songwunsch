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
 * The cookie holds nothing but a room's machine name (or the MAIN mark):
 * no personal data.
 */
final class RoomMemory
{
    public const COOKIE = 'songwunsch_room';

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
        $this->write($value, time() + 365 * 86400);
        $_COOKIE[self::COOKIE] = $value;
    }

    /** Drop the memory -- the room it named is gone. */
    public function forget(): void
    {
        if (!isset($_COOKIE[self::COOKIE])) {
            return;
        }
        $this->write('', time() - 42000);
        unset($_COOKIE[self::COOKIE]);
    }

    private function write(string $value, int $expires): void
    {
        setcookie(self::COOKIE, $value, [
            'expires'  => $expires,
            'path'     => $this->cookiePath,
            'secure'   => $this->secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}
