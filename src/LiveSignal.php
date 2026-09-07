<?php

declare(strict_types=1);

namespace Songwunsch;

/**
 * The doorbell for the live update: one tiny file, `assets/live.txt`, whose
 * content changes whenever anything in the `settings` table changes -- and
 * every change an open page cares about raises a counter there (the room's
 * open/closed switch, the catalogue, the wish lists, the suggestions; see
 * index.php). Open pages ask for this file instead of asking PHP.
 *
 * Why: the application runs on shared hosting, where the account may use only
 * a handful of PHP processes at a time. A poll of `?poll=1` costs one of them
 * plus a database connection; this file is handed out by the web server alone
 * -- no PHP, no MySQL -- and, because Apache sends it with an ETag, a poll
 * that finds nothing new is answered with "304 Not Modified" and an empty
 * body. Only when the content differs from what the page was given does
 * app.js ask `?poll=1` for the real tokens. In a quiet room that is never.
 *
 * The content is eight random bytes, not a counter and not a timestamp.
 * Writing it needs no read, so two visitors wishing at the same moment cannot
 * end up writing the same value; and nothing about it can be read as
 * information. app.js only ever asks whether it is still the one it was
 * given. As a safety net app.js asks PHP directly every now and then anyway,
 * so a page cannot fall behind even if the file stops being written.
 *
 * The file says "something changed", never what and never when: it is
 * world-readable, and a room is often named after the hosts of a private
 * party (see docs/rooms.md). Room names, room ids, how busy a room is and the
 * hour the last wish came in all stay out of it.
 */
final class LiveSignal
{
    /** Below the application's root, in the one folder the web server hands out itself (.htaccess). */
    private const FILE = '/assets/live.txt';

    /** Exactly what touch() writes: 8 random bytes as hex. Anything else is ignored. */
    private const PATTERN = '/^[0-9a-f]{16}$/';

    /** The signal as it stands, or '' when there is none (yet) or it cannot be read. */
    public static function current(): string
    {
        $raw = @file_get_contents(self::path());
        if (!is_string($raw)) {
            return '';
        }
        $value = trim($raw);

        return preg_match(self::PATTERN, $value) === 1 ? $value : '';
    }

    /**
     * Something changed: write a new signal. Written to a neighbouring file
     * and moved into place, so a page asking for it at that very moment gets
     * the old value or the new one, never half of either.
     *
     * The mode is set by hand: rename() does not keep the mode of the file it
     * replaces, and a umask that leaves the file unreadable for the web
     * server would turn every poll into a 403 the browser has to ask for
     * again.
     *
     * Failure is silent on purpose. The signal is an accelerator, not a
     * source of truth: without it app.js falls back to asking `?poll=1` every
     * time, which is what it did before this file existed. A read-only
     * directory must never make a guest's wish fail.
     */
    public static function touch(): void
    {
        $path = self::path();
        // The temp name carries random bytes, not just the process id: two
        // requests may share a process (a threaded server), and a leftover
        // from a crash must never block the next writer.
        $tmp = $path . '.' . bin2hex(random_bytes(6)) . '.tmp';

        if (@file_put_contents($tmp, bin2hex(random_bytes(8))) === false) {
            return;
        }
        @chmod($tmp, 0644);
        if (!@rename($tmp, $path)) {
            @unlink($tmp);
        }
    }

    /** Write the signal if there is none -- after a deployment, which does not carry it along. */
    public static function ensure(): string
    {
        $value = self::current();
        if ($value !== '') {
            return $value;
        }
        self::touch();

        return self::current();
    }

    /** The address open pages ask for; below the base path like every other asset. */
    public static function url(): string
    {
        return base_path() . self::FILE;
    }

    private static function path(): string
    {
        return dirname(__DIR__) . self::FILE;
    }
}
