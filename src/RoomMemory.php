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
 *    list post the `room_switch` action, which removes the cookie. Only
 *    that, or an address naming another room, changes the memory.
 *
 * The cookie holds nothing but a room's machine name: no personal data.
 */
final class RoomMemory
{
    public const COOKIE = 'songwunsch_room';

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
     * The remembered slug, or null when nothing is remembered (the main
     * room) or the value is no slug at all.
     */
    public function slug(): ?string
    {
        $raw = $_COOKIE[self::COOKIE] ?? null;
        if (!is_string($raw) || $raw === '') {
            return null;
        }

        return preg_match(RoomRepository::SLUG_PATTERN, $raw) === 1 ? $raw : null;
    }

    /** Remember a room for a year; '' (the main room) removes the memory. */
    public function remember(string $slug): void
    {
        if ($slug === '') {
            $this->forget();

            return;
        }
        if ($this->slug() === $slug) {
            return;
        }
        $this->write($slug, time() + 365 * 86400);
        $_COOKIE[self::COOKIE] = $slug;
    }

    /** Drop the memory -- the main room was chosen, or the room it named is gone. */
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
