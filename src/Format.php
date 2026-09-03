<?php

declare(strict_types=1);

namespace Songwunsch;

/**
 * Output helpers. e() is the only way values get into the HTML.
 */
final class Format
{
    public static function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Integer with the thousands separator of the active language. The
     * separator is a translatable message so a .po file can set it.
     */
    public static function number(int $value): string
    {
        return number_format($value, 0, '', t(',', [], 'thousands separator'));
    }

    /**
     * Length for display. The database holds seconds (INT); the helper also
     * accepts '00:03:45' or 'mm:ss' so it works for form input as well.
     * Anything recognisable is normalised to m:ss, everything else is passed
     * through unchanged.
     */
    public static function length(mixed $raw): string
    {
        if ($raw === null || $raw === '') {
            return '–';
        }

        if (is_int($raw) || (is_string($raw) && ctype_digit($raw))) {
            return self::fromSeconds((int) $raw);
        }

        if (is_float($raw)) {
            return self::fromSeconds((int) round($raw));
        }

        $value = trim((string) $raw);

        // 00:03:45 or 03:45
        if (preg_match('/^(?:(\d{1,3}):)?(\d{1,3}):(\d{2})(?:\.\d+)?$/', $value, $m) === 1) {
            $seconds = ((int) ($m[1] !== '' ? $m[1] : 0)) * 3600 + ((int) $m[2]) * 60 + (int) $m[3];

            return self::fromSeconds($seconds);
        }

        return $value;
    }

    /** Value in seconds for sorting in the browser or as a raw value. */
    public static function lengthSeconds(mixed $raw): ?int
    {
        if (is_int($raw) || (is_string($raw) && ctype_digit((string) $raw))) {
            return (int) $raw;
        }

        if (is_string($raw) && preg_match('/^(?:(\d{1,3}):)?(\d{1,3}):(\d{2})/', trim($raw), $m) === 1) {
            return ((int) ($m[1] !== '' ? $m[1] : 0)) * 3600 + ((int) $m[2]) * 60 + (int) $m[3];
        }

        return null;
    }

    /**
     * Input from the edit form as seconds. Allowed are '3:45', '1:02:03' and
     * a plain number of seconds. null means: left empty, false: not
     * understood -- the two cases must be told apart.
     */
    public static function parseLength(string $input): int|false|null
    {
        $value = trim($input);

        if ($value === '') {
            return null;
        }

        if (ctype_digit($value)) {
            return (int) $value;
        }

        if (preg_match('/^(?:(\d{1,3}):)?(\d{1,3}):(\d{1,2})$/', $value, $m) === 1) {
            return ((int) ($m[1] !== '' ? $m[1] : 0)) * 3600 + ((int) $m[2]) * 60 + (int) $m[3];
        }

        return false;
    }

    /**
     * Length value for the input field of the edit form: empty stays empty,
     * everything else comes as m:ss or as unchanged free text.
     */
    public static function lengthInput(mixed $raw): string
    {
        if ($raw === null || $raw === '') {
            return '';
        }

        $shown = self::length($raw);

        return $shown === '–' ? '' : $shown;
    }

    private static function fromSeconds(int $seconds): string
    {
        if ($seconds <= 0) {
            return '–';
        }

        $hours   = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $rest    = $seconds % 60;

        return $hours > 0
            ? sprintf('%d:%02d:%02d', $hours, $minutes, $rest)
            : sprintf('%d:%02d', $minutes, $rest);
    }

    /**
     * Timestamp of a wish: "22:14:03" today, otherwise date and time. The
     * PHP date() patterns are translatable so a language can pick its order.
     */
    public static function moment(string $timestamp): string
    {
        $time = strtotime($timestamp);
        if ($time === false) {
            return $timestamp;
        }

        $today = date('Y-m-d');
        $day   = date('Y-m-d', $time);

        return $day === $today
            ? date(t('H:i:s', [], 'time format, PHP date()'), $time)
            : date(t('M j, H:i', [], 'date format, PHP date()'), $time);
    }

    /** "3 min ago" -- purely relative so the list feels live. */
    public static function ago(string $timestamp): string
    {
        $time = strtotime($timestamp);
        if ($time === false) {
            return '';
        }

        $diff = max(0, time() - $time);

        return match (true) {
            $diff < 60    => t('just now'),
            $diff < 3600  => t('{n} min ago', ['n' => intdiv($diff, 60)]),
            $diff < 86400 => t('{n} h ago', ['n' => intdiv($diff, 3600)]),
            default       => tn('{n} day ago', '{n} days ago', intdiv($diff, 86400)),
        };
    }
}
