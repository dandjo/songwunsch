<?php

declare(strict_types=1);

namespace Songwunsch;

/**
 * Light, dark, or whatever the device says: the colour scheme a page is
 * drawn in.
 *
 * Two parties have a say, in this order. A visitor who used the switch in
 * the header has said it for themselves; the answer lives in a cookie
 * (`songwunsch_theme`, one year), like the language and the name for the
 * wish list, and nothing about it is stored on the server. Whoever never
 * touched the switch gets what the admins set under Administration ->
 * Interface (`ui.theme`), which is dark until they say otherwise -- the
 * interface the site has always had.
 *
 * `system` is a scheme like the other two, not the absence of one: it means
 * "ask the device", and the answer is given by the stylesheet's
 * prefers-color-scheme block, not here. PHP cannot know what a device
 * prefers, so it says `system` and lets CSS finish the sentence.
 *
 * A scheme is not personal data and says nothing about the visitor: the
 * cookie holds one of three words, at the visitor's own request.
 */
final class Theme
{
    public const COOKIE = 'songwunsch_theme';

    public const DARK   = 'dark';
    public const LIGHT  = 'light';
    public const SYSTEM = 'system';

    /** Every scheme, in the order the switch and the Interface page offer them. */
    public const SCHEMES = [self::SYSTEM, self::LIGHT, self::DARK];

    /** Settings key of the admins' default: what a visitor without a choice gets. */
    public const DEFAULT_KEY = 'ui.theme';

    /** And what that is until the admins change it. */
    public const FALLBACK = self::DARK;

    /**
     * @param string $cookiePath scope of the cookie, e.g. '/songliste/' --
     *                           always with a trailing slash, like the session.
     */
    public function __construct(
        private readonly Settings $settings,
        private readonly string $cookiePath,
        private readonly bool $secure,
    ) {
    }

    /** The scheme this page is drawn in: the visitor's word, else the admins'. */
    public function current(): string
    {
        return $this->chosen() ?? $this->fallback();
    }

    /** What the visitor picked, or null while they have not picked anything. */
    public function chosen(): ?string
    {
        $raw = $_COOKIE[self::COOKIE] ?? null;

        return is_string($raw) && self::isScheme($raw) ? $raw : null;
    }

    /** The admins' default (Administration -> Interface). */
    public function fallback(): string
    {
        $stored = (string) $this->settings->get(self::DEFAULT_KEY, self::FALLBACK);

        return self::isScheme($stored) ? $stored : self::FALLBACK;
    }

    /** Remember the visitor's choice for a year. An unknown value changes nothing. */
    public function remember(string $scheme): void
    {
        if (!self::isScheme($scheme)) {
            return;
        }

        setcookie(self::COOKIE, $scheme, [
            'expires'  => time() + 365 * 86400,
            'path'     => $this->cookiePath,
            'secure'   => $this->secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        // The answer to the switch is already drawn in the new scheme.
        $_COOKIE[self::COOKIE] = $scheme;
    }

    /** Store the admins' default; an unknown value is ignored. */
    public function saveFallback(string $scheme): void
    {
        if (self::isScheme($scheme)) {
            $this->settings->set(self::DEFAULT_KEY, $scheme);
        }
    }

    public static function isScheme(string $value): bool
    {
        return in_array($value, self::SCHEMES, true);
    }

    /**
     * What <meta name="color-scheme"> says, so the browser's own furniture
     * -- scrollbars, form controls, the canvas behind the page -- follows.
     * A visitor on `system` may see either, and that is what the pair means.
     */
    public static function colorScheme(string $scheme): string
    {
        return $scheme === self::SYSTEM ? 'light dark' : $scheme;
    }
}
