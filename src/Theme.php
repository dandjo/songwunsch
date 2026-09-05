<?php

declare(strict_types=1);

namespace Songwunsch;

/**
 * The site's colours, set by the admins under Administration -> Colours and
 * kept in the `settings` table as `theme.<area>`: one colour per area of
 * use -- accent, secondary, danger, success, background, text. The
 * stylesheet carries the defaults as custom properties on :root; this class
 * derives the shades and tints the stylesheet uses (bright, deep, line,
 * tint ...) from a configured base colour and hands the layout a :root block
 * that overrides them. What is not configured is not emitted, so the
 * stylesheet's own values apply.
 */
final class Theme
{
    public const PREFIX = 'theme.';

    /** The configurable areas, in the order the Colours page shows them. */
    public const AREAS = ['accent', 'secondary', 'danger', 'success', 'background', 'text'];

    /** The stylesheet's own colours (assets/style.css, :root) -- for the Colours page's pickers and hints. */
    public const DEFAULTS = [
        'accent'     => '#e6b450',
        'secondary'  => '#8d7ce0',
        'danger'     => '#ff6f85',
        'success'    => '#4ed08c',
        'background' => '#0d0e13',
        'text'       => '#e9ebf1',
    ];

    /**
     * The configured colours, area => '#rrggbb'; areas left at their default
     * are ''. One query.
     *
     * @return array<string,string>
     */
    public static function load(Settings $settings): array
    {
        $stored = $settings->withPrefix(self::PREFIX);
        $out    = [];
        foreach (self::AREAS as $area) {
            $rgb        = self::parse((string) ($stored[$area] ?? ''));
            $out[$area] = $rgb === null ? '' : self::hex($rgb);
        }

        return $out;
    }

    /**
     * Check the Colours form: every area either empty (the built-in colour)
     * or a hex colour, normalised to lower-case '#rrggbb'.
     *
     * @param array<string,string> $input  area => what was typed
     * @return array{values: array<string,string>, errors: array<string,string>}
     */
    public static function validate(array $input): array
    {
        $values = [];
        $errors = [];
        foreach (self::AREAS as $area) {
            $raw = trim((string) ($input[$area] ?? ''));
            if ($raw === '') {
                $values[$area] = '';
                continue;
            }
            $rgb = self::parse($raw[0] === '#' ? $raw : '#' . $raw);
            if ($rgb === null) {
                $errors[$area] = t('Please enter a colour as #rrggbb.');
                continue;
            }
            $values[$area] = self::hex($rgb);
        }

        return ['values' => $values, 'errors' => $errors];
    }

    /**
     * Store validated colours; an empty value drops the entry so the
     * stylesheet's colour applies again.
     *
     * @param array<string,string> $values  from validate()
     */
    public static function save(Settings $settings, array $values): void
    {
        foreach (self::AREAS as $area) {
            if (!isset($values[$area])) {
                continue;
            }
            if ($values[$area] === '') {
                $settings->delete(self::PREFIX . $area);
            } else {
                $settings->set(self::PREFIX . $area, $values[$area]);
            }
        }
    }

    /**
     * CSS for the layout's <style>, '' when nothing is configured.
     *
     * @param array<string,mixed> $theme  area => '#rrggbb' (see load()); '' or missing = default
     */
    public static function css(array $theme): string
    {
        $vars = [];
        $rgb  = [];
        foreach (self::AREAS as $area) {
            $parsed = self::parse((string) ($theme[$area] ?? ''));
            if ($parsed !== null) {
                $rgb[$area] = $parsed;
            }
        }
        if ($rgb === []) {
            return '';
        }

        $white = [255, 255, 255];
        $black = [0, 0, 0];
        // Text shades lean towards the (configured or default) background.
        $ground = $rgb['background'] ?? [13, 14, 19];

        if (isset($rgb['accent'])) {
            $c = $rgb['accent'];
            $vars += [
                '--gold'             => self::hex($c),
                '--gold-bright'      => self::hex(self::mix($c, $white, .30)),
                '--gold-deep'        => self::hex(self::mix($c, $black, .48)),
                '--gold-wash'        => self::rgba($c, .06),
                '--gold-tint'        => self::rgba($c, .12),
                '--gold-tint-mid'    => self::rgba($c, .14),
                '--gold-tint-strong' => self::rgba($c, .22),
                '--glow-gold'        => '0 0 0 3px ' . self::rgba($c, .2),
            ];
        }
        if (isset($rgb['secondary'])) {
            $c = $rgb['secondary'];
            $vars += [
                '--violet'        => self::hex($c),
                '--violet-bright' => self::hex(self::mix($c, $white, .30)),
                '--violet-soft'   => self::rgba($c, .13),
                '--violet-line'   => self::rgba($c, .32),
            ];
        }
        if (isset($rgb['danger'])) {
            $c = $rgb['danger'];
            $vars += [
                '--danger'             => self::hex($c),
                '--danger-bright'      => self::hex(self::mix($c, $white, .30)),
                '--danger-deep'        => self::hex(self::mix($c, $black, .35)),
                '--danger-line'        => self::hex(self::mix($c, $black, .60)),
                '--danger-tint'        => self::rgba($c, .12),
                '--danger-tint-strong' => self::rgba($c, .25),
                '--danger-glow'        => self::rgba($c, .15),
            ];
        }
        if (isset($rgb['success'])) {
            $vars['--ok'] = self::hex($rgb['success']);
        }
        if (isset($rgb['background'])) {
            $c = $rgb['background'];
            $vars += [
                '--ink'     => self::hex($c),
                '--surface' => self::hex(self::mix($c, $white, .015)),
                '--shell'   => self::hex(self::mix($c, $white, .03)),
                '--base'    => self::hex(self::mix($c, $white, .03)),
                '--panel'   => self::hex(self::mix($c, $white, .055)),
                '--line'    => self::hex(self::mix($c, $white, .13)),
            ];
        }
        if (isset($rgb['text'])) {
            $c = $rgb['text'];
            $vars += [
                '--text'       => self::hex($c),
                '--text-muted' => self::hex(self::mix($c, $ground, .37)),
                '--chrome'     => self::hex(self::mix($c, $ground, .15)),
            ];
        }

        $lines = [];
        foreach ($vars as $name => $value) {
            $lines[] = $name . ':' . $value;
        }

        return ':root{' . implode(';', $lines) . '}';
    }

    /**
     * '#rgb' or '#rrggbb' to [r, g, b]; anything else is ignored (null).
     *
     * @return array{0:int,1:int,2:int}|null
     */
    public static function parse(string $hex): ?array
    {
        $hex = trim($hex);
        if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $hex, $m) !== 1) {
            return null;
        }
        $h = strlen($m[1]) === 3 ? $m[1][0] . $m[1][0] . $m[1][1] . $m[1][1] . $m[1][2] . $m[1][2] : $m[1];

        return [(int) hexdec(substr($h, 0, 2)), (int) hexdec(substr($h, 2, 2)), (int) hexdec(substr($h, 4, 2))];
    }

    /**
     * @param array{0:int,1:int,2:int} $a
     * @param array{0:int,1:int,2:int} $b
     * @return array{0:int,1:int,2:int} $a moved towards $b by $ratio (0..1)
     */
    private static function mix(array $a, array $b, float $ratio): array
    {
        return [
            (int) round($a[0] + ($b[0] - $a[0]) * $ratio),
            (int) round($a[1] + ($b[1] - $a[1]) * $ratio),
            (int) round($a[2] + ($b[2] - $a[2]) * $ratio),
        ];
    }

    /** @param array{0:int,1:int,2:int} $c */
    private static function hex(array $c): string
    {
        return sprintf('#%02x%02x%02x', $c[0], $c[1], $c[2]);
    }

    /** @param array{0:int,1:int,2:int} $c */
    private static function rgba(array $c, float $alpha): string
    {
        return sprintf('rgba(%d, %d, %d, %s)', $c[0], $c[1], $c[2], rtrim(rtrim(number_format($alpha, 3, '.', ''), '0'), '.'));
    }
}
