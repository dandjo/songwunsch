<?php

declare(strict_types=1);

namespace Songwunsch;

/**
 * The name a visitor gives for the wish list.
 *
 * On the first visit the site asks for it; the answer lives in a cookie
 * (`songwunsch_name`, one year) and is copied into every wish the browser
 * submits, where it is shown next to the song. Nothing is stored on the
 * server until a wish is made; the name is changed or removed in the
 * account menu at any time. Giving one is optional -- "Not now" is
 * remembered for the session only, so a returning guest is asked again.
 *
 * The name is personal data (GDPR): it stays with the wish and disappears
 * with it. The cookie holds nothing but the name, at the visitor's request.
 */
final class GuestName
{
    public const COOKIE     = 'songwunsch_name';
    public const MAX_LENGTH = 40;

    private const SESSION_ASKED = 'name_asked';

    /**
     * @param string $cookiePath scope of the cookie, e.g. '/songliste/' --
     *                           always with a trailing slash, like the session.
     */
    public function __construct(
        private readonly string $cookiePath,
        private readonly bool $secure,
    ) {
    }

    /** The visitor's name, or null when none is set. */
    public function current(): ?string
    {
        $raw = $_COOKIE[self::COOKIE] ?? null;
        if (!is_string($raw)) {
            return null;
        }
        $name = self::clean($raw);

        return $name === '' ? null : $name;
    }

    /**
     * Should this request ask for the name? Only while none is set and the
     * visitor has not declined in this session.
     */
    public function shouldAsk(): bool
    {
        return $this->current() === null && empty($_SESSION[self::SESSION_ASKED]);
    }

    /** Remember the name for a year; an empty name removes the cookie. */
    public function remember(string $name): void
    {
        $name = self::clean($name);
        // Whatever the answer was, the question has been put this session.
        $_SESSION[self::SESSION_ASKED] = true;

        setcookie(self::COOKIE, $name, [
            'expires'  => $name === '' ? time() - 42000 : time() + 365 * 86400,
            'path'     => $this->cookiePath,
            'secure'   => $this->secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        // Make the new value visible to the rest of this request.
        if ($name === '') {
            unset($_COOKIE[self::COOKIE]);
        } else {
            $_COOKIE[self::COOKIE] = $name;
        }
    }

    /** "Not now": do not ask again during this session. */
    public function skip(): void
    {
        $_SESSION[self::SESSION_ASKED] = true;
    }

    /**
     * Tidy an entered name: no control characters, single spaces, at most
     * MAX_LENGTH characters. Escaping for HTML stays with the output.
     */
    public static function clean(string $raw): string
    {
        $name = preg_replace('/[\p{C}]+/u', ' ', $raw) ?? '';
        $name = preg_replace('/\s+/u', ' ', $name) ?? '';
        $name = trim($name);

        return mb_substr($name, 0, self::MAX_LENGTH);
    }
}
