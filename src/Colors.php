<?php

declare(strict_types=1);

namespace Songwunsch;

/**
 * The site's colours, set by the admins under Administration -> Interface
 * and kept in the `settings` table as `colors.<area>`: one colour per area of
 * use -- accent, secondary, danger, success, background, text. The
 * stylesheet carries the defaults as custom properties on :root; this class
 * derives the shades and tints the stylesheet uses (bright, deep, line,
 * tint ...) from a configured base colour and hands the layout a block
 * that overrides them. What is not configured is not emitted, so the
 * stylesheet's own values apply.
 *
 * There are two schemes (Theme), and the same ratios serve both once the
 * directions are named instead of written down: "brighter" means away from
 * the page ground, a frame means towards it. Two areas are the exception --
 * background and text shape the dark scheme only, because a ground the
 * admins picked dark would make the light scheme dark again and the switch
 * in the header would look broken. On the light scheme an accent is first
 * moved far enough to be readable there (see readable()): it was chosen
 * against a dark ground, and a pale yellow that shines on black cannot be
 * read on white.
 */
final class Colors
{
    public const PREFIX = 'colors.';

    /** The configurable areas, in the order the Interface page shows them. */
    public const AREAS = ['accent', 'secondary', 'danger', 'success', 'background', 'text'];

    /** The light scheme's page ground (assets/style.css, :root[data-theme="light"]). */
    private const LIGHT_GROUND = [246, 246, 249];

    /** The stylesheet's own colours (assets/style.css, :root) -- for the Interface page's pickers and hints. */
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
     * Check the colour fields of the Interface form: every area either empty (the built-in colour)
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
     * @param array<string,mixed> $colors area => '#rrggbb' (see load()); '' or missing = default
     * @param bool                $dark   the scheme the block is for (Theme);
     *                                    no default, so a caller has to say
     */
    public static function css(array $colors, bool $dark): string
    {
        $vars = [];
        $rgb  = [];
        foreach (self::AREAS as $area) {
            $parsed = self::parse((string) ($colors[$area] ?? ''));
            if ($parsed !== null) {
                $rgb[$area] = $parsed;
            }
        }
        if ($rgb === []) {
            return '';
        }

        // The two directions every shade below travels in. Away from the
        // ground is where a colour gets more contrast -- towards white on the
        // dark scheme, towards black on the light one -- and the ground
        // itself is where frames and muted text lean.
        $away   = $dark ? [255, 255, 255] : [0, 0, 0];
        $black  = [0, 0, 0];
        $ground = $dark
            ? ($rgb['background'] ?? (array) self::parse(self::DEFAULTS['background']))
            : self::LIGHT_GROUND;
        // How far a frame is moved towards the ground. The contrast formula
        // is not symmetric: against a near-black ground half the way still
        // stands out, against a near-white one it has all but vanished. The
        // two ratios keep a gold-framed button about equally visible on
        // either scheme (~3.5:1 against the ground).
        $frame = $dark ? .48 : .20;

        if (isset($rgb['accent'])) {
            $c = $dark ? $rgb['accent'] : self::readable($rgb['accent'], $ground, $away);
            $vars += [
                '--gold'             => self::hex($c),
                '--gold-bright'      => self::hex(self::mix($c, $away, .30)),
                '--gold-deep'        => self::hex(self::mix($c, $ground, $frame)),
                '--gold-wash'        => self::rgba($c, .06),
                '--gold-tint'        => self::rgba($c, .12),
                '--gold-tint-mid'    => self::rgba($c, .14),
                '--gold-tint-strong' => self::rgba($c, .22),
                '--glow-gold'        => '0 0 0 3px ' . self::rgba($c, .2),
            ];
        }
        if (isset($rgb['secondary'])) {
            $c = $dark ? $rgb['secondary'] : self::readable($rgb['secondary'], $ground, $away);
            $vars += [
                '--violet'        => self::hex($c),
                '--violet-bright' => self::hex(self::mix($c, $away, .30)),
                '--violet-soft'   => self::rgba($c, .13),
                '--violet-line'   => self::rgba($c, .32),
            ];
        }
        if (isset($rgb['danger'])) {
            $c = $dark ? $rgb['danger'] : self::readable($rgb['danger'], $ground, $away);
            $vars += [
                '--danger'             => self::hex($c),
                '--danger-bright'      => self::hex(self::mix($c, $away, .30)),
                // The filled hover of a danger button, with white on it:
                // darker than the base on either scheme.
                '--danger-deep'        => self::hex(self::mix($c, $black, .35)),
                '--danger-line'        => self::hex(self::mix($c, $ground, .60)),
                '--danger-tint'        => self::rgba($c, .12),
                '--danger-tint-strong' => self::rgba($c, .25),
                '--danger-glow'        => self::rgba($c, .15),
            ];
        }
        if (isset($rgb['success'])) {
            $vars['--ok'] = self::hex($dark
                ? $rgb['success']
                : self::readable($rgb['success'], $ground, $away));
        }
        // Ground and text belong to the dark scheme; the light one keeps its
        // own, see the class comment.
        if ($dark && isset($rgb['background'])) {
            $c = $rgb['background'];
            $vars += [
                '--ink'     => self::hex($c),
                '--surface' => self::hex(self::mix($c, $away, .015)),
                '--shell'   => self::hex(self::mix($c, $away, .03)),
                '--base'    => self::hex(self::mix($c, $away, .03)),
                '--panel'   => self::hex(self::mix($c, $away, .055)),
                '--line'    => self::hex(self::mix($c, $away, .13)),
            ];
        }
        if ($dark && isset($rgb['text'])) {
            $c = $rgb['text'];
            $vars += [
                '--text'       => self::hex($c),
                '--text-muted' => self::hex(self::mix($c, $ground, .37)),
            ];
        }
        if ($vars === []) {
            return '';
        }

        $lines = [];
        foreach ($vars as $name => $value) {
            $lines[] = $name . ':' . $value;
        }

        // The light scheme's own tokens sit on :root[data-theme="light"] in
        // the stylesheet. Specificity beats document order, so a block that
        // is to override them has to be written with the same selector --
        // a plain :root would lose although it comes later.
        $selector = $dark ? ':root' : ':root[data-theme="light"]';

        return $selector . '{' . implode(';', $lines) . '}';
    }

    /**
     * A base colour moved far enough to be read on a ground it was not
     * picked for: mixed towards the pole in twentieths until it reaches the
     * contrast WCAG asks for body text (4.5:1). Only the light scheme uses
     * this -- there the admins' colour was chosen against the dark ground,
     * and the site would be unreadable if it were taken as it stands. On the
     * dark scheme the colour is left exactly as it was typed in.
     *
     * @param array{0:int,1:int,2:int} $c
     * @param array{0:int,1:int,2:int} $ground
     * @param array{0:int,1:int,2:int} $pole
     * @return array{0:int,1:int,2:int}
     */
    private static function readable(array $c, array $ground, array $pole): array
    {
        for ($step = 0; $step < 20; $step++) {
            $try = $step === 0 ? $c : self::mix($c, $pole, $step * .05);
            if (self::contrast($try, $ground) >= 4.5) {
                return $try;
            }
        }

        return $pole;
    }

    /**
     * The WCAG contrast ratio between two colours, 1 (equal) to 21 (black
     * on white).
     *
     * @param array{0:int,1:int,2:int} $a
     * @param array{0:int,1:int,2:int} $b
     */
    private static function contrast(array $a, array $b): float
    {
        $la = self::luminance($a);
        $lb = self::luminance($b);

        return (max($la, $lb) + .05) / (min($la, $lb) + .05);
    }

    /**
     * Relative luminance as WCAG defines it: the channels back off the
     * display's gamma curve, then weighted by how bright the eye finds them.
     *
     * @param array{0:int,1:int,2:int} $c
     */
    private static function luminance(array $c): float
    {
        $linear = [];
        foreach ($c as $channel) {
            $s        = $channel / 255;
            $linear[] = $s <= .03928 ? $s / 12.92 : (($s + .055) / 1.055) ** 2.4;
        }

        return .2126 * $linear[0] + .7152 * $linear[1] + .0722 * $linear[2];
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
