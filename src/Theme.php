<?php

declare(strict_types=1);

namespace Songwunsch;

/**
 * Light or dark: the colour scheme a visitor picked for themselves.
 *
 * The interface is dark by design and stays dark for anyone who says
 * nothing -- `prefers-color-scheme` is deliberately not consulted, so nobody
 * who knows the site finds it changed one day. Whoever wants it light says
 * so with the switch in the header; the answer lives in a cookie
 * (`songwunsch_theme`, one year), like the language and the name for the
 * wish list, and nothing about it is stored on the server. Guests have no
 * account, and they are the many here -- so the choice must work without one.
 *
 * The value reaches the page as `data-theme` on <html>, where the stylesheet
 * picks it up (assets/style.css), and it decides which of the two palettes
 * the admins' own colours are derived for (Colors).
 *
 * A scheme is not personal data and says nothing about the visitor's device:
 * the cookie holds one of two words, at the visitor's own request.
 */
final class Theme
{
    public const COOKIE = 'songwunsch_theme';

    public const DARK  = 'dark';
    public const LIGHT = 'light';

    /** What the site looks like until someone says otherwise. */
    public const FALLBACK = self::DARK;

    /**
     * @param string $cookiePath scope of the cookie, e.g. '/songliste/' --
     *                           always with a trailing slash, like the session.
     */
    public function __construct(
        private readonly string $cookiePath,
        private readonly bool $secure,
    ) {
    }

    /** The scheme this request is answered in; anything unknown is the fallback. */
    public function current(): string
    {
        $raw = $_COOKIE[self::COOKIE] ?? null;

        return is_string($raw) && $raw === self::LIGHT ? self::LIGHT : self::FALLBACK;
    }

    public function isDark(): bool
    {
        return $this->current() === self::DARK;
    }

    /** The other one -- what the switch in the header offers. */
    public function other(): string
    {
        return $this->current() === self::LIGHT ? self::DARK : self::LIGHT;
    }

    /** Remember the choice for a year. An unknown value keeps the current one. */
    public function remember(string $theme): void
    {
        if ($theme !== self::LIGHT && $theme !== self::DARK) {
            return;
        }

        setcookie(self::COOKIE, $theme, [
            'expires'  => time() + 365 * 86400,
            'path'     => $this->cookiePath,
            'secure'   => $this->secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        // The answer to the switch is already rendered in the new scheme.
        $_COOKIE[self::COOKIE] = $theme;
    }
}
