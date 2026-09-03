<?php

declare(strict_types=1);

namespace Songwunsch;

/**
 * Translations for the user interface.
 *
 * English is the source language: every string in the code is English and
 * doubles as the message id. Other languages live in lang/<code>.po; dropping
 * a new .po file into that directory is all it takes for the language to
 * show up in the switcher. The file's "X-Native-Name" header supplies the
 * name shown there (falling back to the code).
 *
 * Placeholders are written as {name} and filled with strtr(); the caller
 * escapes the result for HTML like any other value.
 */
final class Translator
{
    public const DEFAULT = 'en';

    private const COOKIE  = 'songwunsch_lang';
    private const CONTEXT = "\x04";

    /** @var array<string,string>|null code => native name, lazily scanned */
    private ?array $available = null;

    private string $code = self::DEFAULT;

    private ?PoFile $catalog = null;

    public function __construct(private readonly string $dir)
    {
    }

    // ---- Languages ---------------------------------------------------------

    /**
     * Languages offered in the switcher: English plus every lang/*.po that
     * declares a Language header. Keys are lower-case codes.
     *
     * @return array<string,string> code => native name
     */
    public function available(): array
    {
        if ($this->available !== null) {
            return $this->available;
        }

        $found = [self::DEFAULT => 'English'];

        foreach (glob($this->dir . '/*.po') ?: [] as $file) {
            $code = strtolower(basename($file, '.po'));
            if ($code === self::DEFAULT || preg_match('/^[a-z]{2,3}(-[a-z0-9]{2,8})?$/', $code) !== 1) {
                continue;
            }

            // Only the header is needed here; the full catalog is parsed on load().
            $head = (string) file_get_contents($file, false, null, 0, 4096);
            $name = preg_match('/X-Native-Name:\s*([^\\\\"\n]+)/', $head, $m) === 1 ? trim($m[1]) : $code;

            $found[$code] = $name;
        }

        return $this->available = $found;
    }

    public function has(string $code): bool
    {
        return isset($this->available()[strtolower($code)]);
    }

    public function code(): string
    {
        return $this->code;
    }

    /** Language for the <html lang> attribute: "de", "pt-br". */
    public function htmlLang(): string
    {
        return $this->code;
    }

    /** Activate a language; unknown codes fall back to English. */
    public function load(string $code): void
    {
        $code = strtolower($code);

        if ($code === self::DEFAULT || !$this->has($code)) {
            $this->code    = self::DEFAULT;
            $this->catalog = null;

            return;
        }

        $this->code    = $code;
        $this->catalog = PoFile::load($this->dir . '/' . $code . '.po');
    }

    /**
     * Pick the language for this request, in order of preference:
     * explicit choice, session, cookie, browser Accept-Language, English.
     */
    public function detect(?string $requested, ?string $session, ?string $cookie, ?string $acceptLanguage): string
    {
        foreach ([$requested, $session, $cookie] as $candidate) {
            if (is_string($candidate) && $candidate !== '' && $this->has($candidate)) {
                return strtolower($candidate);
            }
        }

        // "de-AT,de;q=0.9,en;q=0.8" -> try de-at, de, en in that order.
        foreach (self::acceptedLanguages((string) $acceptLanguage) as $tag) {
            if ($this->has($tag)) {
                return $tag;
            }
            $primary = explode('-', $tag)[0];
            if ($this->has($primary)) {
                return $primary;
            }
        }

        return self::DEFAULT;
    }

    /** Remember the choice for later requests: session plus a one-year cookie. */
    public function remember(string $code, string $cookiePath, bool $secure): void
    {
        $_SESSION['lang'] = $code;
        setcookie(self::COOKIE, $code, [
            'expires'  => time() + 365 * 86400,
            'path'     => $cookiePath,
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    public static function cookieName(): string
    {
        return self::COOKIE;
    }

    // ---- Lookup ------------------------------------------------------------

    /**
     * Translate a message. {placeholders} in the result are filled from $args.
     *
     * @param array<string,scalar|null> $args
     */
    public function t(string $message, array $args = [], ?string $context = null): string
    {
        $key   = $context !== null ? $context . self::CONTEXT . $message : $message;
        $entry = $this->catalog?->messages[$key] ?? null;
        $text  = is_string($entry) && $entry !== '' ? $entry : $message;

        return self::fill($text, $args);
    }

    /**
     * Translate a message with a count. {n} is always available as a
     * placeholder; the English source has two forms, the catalog may have
     * as many as its Plural-Forms header declares.
     *
     * @param array<string,scalar|null> $args
     */
    public function n(string $singular, string $plural, int $count, array $args = [], ?string $context = null): string
    {
        $args += ['n' => $count];
        $key   = $context !== null ? $context . self::CONTEXT . $singular : $singular;
        $entry = $this->catalog?->messages[$key] ?? null;

        if (is_array($entry) && $this->catalog !== null) {
            $form = $entry[$this->catalog->pluralIndex($count)] ?? '';
            if ($form !== '') {
                return self::fill($form, $args);
            }
        }

        return self::fill(abs($count) === 1 ? $singular : $plural, $args);
    }

    /** @param array<string,scalar|null> $args */
    private static function fill(string $text, array $args): string
    {
        if ($args === []) {
            return $text;
        }

        $map = [];
        foreach ($args as $name => $value) {
            $map['{' . $name . '}'] = (string) $value;
        }

        return strtr($text, $map);
    }

    /** @return array<int,string> lower-case tags, best first */
    private static function acceptedLanguages(string $header): array
    {
        $weighted = [];
        foreach (explode(',', $header) as $i => $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            $q = 1.0;
            if (preg_match('/^([A-Za-z0-9-]+)\s*;\s*q=([0-9.]+)$/', $part, $m) === 1) {
                $part = $m[1];
                $q    = (float) $m[2];
            }
            if (preg_match('/^[A-Za-z]{2,3}(-[A-Za-z0-9]{2,8})?$/', $part) === 1) {
                $weighted[] = [strtolower($part), $q, $i];
            }
        }

        usort($weighted, static fn (array $a, array $b): int => $b[1] <=> $a[1] ?: $a[2] <=> $b[2]);

        return array_column($weighted, 0);
    }
}
