<?php

declare(strict_types=1);

namespace Songwunsch;

/**
 * The site's colours, set by the admins under Administration -> Interface.
 *
 * There are two schemes (Theme) and therefore two sets of six: one base
 * colour per area of use -- accent, secondary, danger, success, background,
 * text -- for the dark scheme in `colors.<area>` and for the light one in
 * `colors.light.<area>`. The stylesheet carries both sets as the built-in
 * values (assets/style.css); this class derives from a base colour every
 * shade and tint the stylesheet needs (bright, deep, line, tint ...) and
 * hands the layout a block that overrides them. What is not configured is
 * not emitted, so the stylesheet's own value applies.
 *
 * The same ratios serve both schemes once the directions are named instead
 * of written down: "brighter" means away from the page ground, a frame means
 * towards it. Only the surfaces need numbers of their own per scheme -- on
 * the dark scheme every surface is a lightened step of the ground, on the
 * light one the content surface is lifted towards white while the chrome
 * around it sinks. That is the light design's own idiom, not a translation
 * of the dark one.
 *
 * The colour a scheme gets is the colour the admins typed for that scheme.
 * Nothing here corrects it: a light set is chosen against the light ground,
 * so nudging it would override a deliberate choice. Whether it can be read
 * is the admins' to check, and the Interface page and docs/accessibility.md
 * say so.
 */
final class Colors
{
    public const PREFIX       = 'colors.';
    public const PREFIX_LIGHT = 'colors.light.';

    /** The configurable areas, in the order the Interface page shows them. */
    public const AREAS = ['accent', 'secondary', 'danger', 'success', 'background', 'text'];

    /** The stylesheet's dark colours (assets/style.css, :root). */
    public const DEFAULTS = [
        'accent'    => '#a1c2d8',
        'secondary' => '#d4e3e5',
        'danger'    => '#ff6f61',
        'success'   => '#ecf0f1',
        'background' => '#0d1417',
        'text'      => '#f6f8f8',
    ];

    /** The stylesheet's light colours (assets/style.css, :root[data-theme="light"]). */
    public const DEFAULTS_LIGHT = [
        'accent'    => '#2c3e50',
        'secondary' => '#5b80a4',
        'danger'    => '#973227',
        'success'   => '#88b04b',
        'background' => '#fbfcfe',
        'text'      => '#2c3e50',
    ];

    /**
     * Everything the layout puts into its <style>, '' when nothing is
     * configured at all.
     *
     * Every scheme's block goes out, not just the one this page is drawn in.
     * Each carries the selector of its own scheme, so only one of them ever
     * applies -- and the switch in the header changes the scheme in the
     * browser, without asking the server again (app.js). Sending only the
     * current scheme's block would leave the admins' colours behind for a
     * render after every switch.
     */
    public static function stylesheet(Settings $settings): string
    {
        $dark  = self::css(self::load($settings, true), true);
        $light = self::css(self::load($settings, false), false);
        // The light values once more for the visitor who follows their
        // device, behind the media query the stylesheet uses for them.
        $system = self::css(self::load($settings, false), false, Theme::SYSTEM);

        return $dark
            . $light
            . ($system === '' ? '' : '@media (prefers-color-scheme: light){' . $system . '}');
    }

    /**
     * The configured colours of one scheme, area => '#rrggbb'; areas left at
     * their default are ''. One query.
     *
     * @return array<string,string>
     */
    public static function load(Settings $settings, bool $dark): array
    {
        $stored = $settings->withPrefix($dark ? self::PREFIX : self::PREFIX_LIGHT);
        $out    = [];
        foreach (self::AREAS as $area) {
            $rgb        = self::parse((string) ($stored[$area] ?? ''));
            $out[$area] = $rgb === null ? '' : self::hex($rgb);
        }

        return $out;
    }

    /**
     * The configured colours of one scheme, keyed the way the Interface
     * form names its fields.
     *
     * @return array<string,string>
     */
    public static function fields(Settings $settings, bool $dark): array
    {
        $out = [];
        foreach (self::load($settings, $dark) as $area => $value) {
            $out[self::field($area, $dark)] = $value;
        }

        return $out;
    }

    /** The built-in colours of one scheme -- the Interface page's pickers and hints. */
    public static function defaults(bool $dark): array
    {
        return $dark ? self::DEFAULTS : self::DEFAULTS_LIGHT;
    }

    /**
     * What one colour field of the Interface form is called. The dark set
     * keeps the bare area names it always had, so nothing an existing
     * install stored has to move.
     */
    public static function field(string $area, bool $dark): string
    {
        return $dark ? $area : 'light_' . $area;
    }

    /**
     * Check the colour fields of one scheme: every area either empty (the
     * built-in colour) or a hex colour, normalised to lower-case '#rrggbb'.
     * Values and errors come back keyed by field name, so the two schemes
     * cannot collide.
     *
     * @param array<string,string> $input  field name => what was typed
     * @return array{values: array<string,string>, errors: array<string,string>}
     */
    public static function validate(array $input, bool $dark): array
    {
        $values = [];
        $errors = [];
        foreach (self::AREAS as $area) {
            $field = self::field($area, $dark);
            $raw   = trim((string) ($input[$field] ?? ''));
            if ($raw === '') {
                $values[$field] = '';
                continue;
            }
            $rgb = self::parse($raw[0] === '#' ? $raw : '#' . $raw);
            if ($rgb === null) {
                $errors[$field] = t('Please enter a colour as #rrggbb.');
                continue;
            }
            $values[$field] = self::hex($rgb);
        }

        return ['values' => $values, 'errors' => $errors];
    }

    /**
     * Store the validated colours of one scheme; an empty value drops the
     * entry so the stylesheet's colour applies again.
     *
     * @param array<string,string> $values  from validate(), keyed by field name
     */
    public static function save(Settings $settings, array $values, bool $dark): void
    {
        $prefix = $dark ? self::PREFIX : self::PREFIX_LIGHT;
        foreach (self::AREAS as $area) {
            $field = self::field($area, $dark);
            if (!isset($values[$field])) {
                continue;
            }
            if ($values[$field] === '') {
                $settings->delete($prefix . $area);
            } else {
                $settings->set($prefix . $area, $values[$field]);
            }
        }
    }

    /**
     * One :root block for one scheme, '' when that scheme has nothing set.
     *
     * @param array<string,mixed> $colors area => '#rrggbb' (see load()); '' or missing = default
     * @param bool                $dark   which scheme these colours are for
     * @param string              $scope  the scheme the page is rendered in, when it
     *                                    differs from $dark -- a light block for a
     *                                    visitor following their device belongs to
     *                                    data-theme="system", not to "light"
     */
    public static function css(array $colors, bool $dark, string $scope = ''): string
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

        // The two directions every shade travels in. Away from the ground is
        // where a colour gains contrast -- towards white on the dark scheme,
        // towards black on the light one -- and the ground itself is where
        // frames and muted text lean.
        $white  = [255, 255, 255];
        $black  = [0, 0, 0];
        $away   = $dark ? $white : $black;
        $ground = $rgb['background'] ?? (array) self::parse(self::defaults($dark)['background']);

        // A frame is the base colour moved towards the ground. How far is a
        // matter of each scheme's own idiom rather than one rule: on the dark
        // one a frame is a muted version of the accent, on the light one it
        // is the pastel of that family -- soft, because the button it frames
        // carries readable text of its own. Both ratios land where the
        // built-in palettes put their frames.
        $frame = $dark ? .48 : .55;
        // The transparent tints are a shade weaker on the light scheme,
        // where a veil of colour over white reads stronger than over black.
        $tints = $dark ? [.06, .12, .14, .22] : [.05, .09, .12, .18];

        if (isset($rgb['accent'])) {
            $c = $rgb['accent'];
            $vars += [
                '--gold'             => self::hex($c),
                '--gold-bright'      => self::hex(self::mix($c, $away, .30)),
                '--gold-deep'        => self::hex(self::mix($c, $ground, $frame)),
                '--gold-wash'        => self::rgba($c, $tints[0]),
                '--gold-tint'        => self::rgba($c, $tints[1]),
                '--gold-tint-mid'    => self::rgba($c, $tints[2]),
                '--gold-tint-strong' => self::rgba($c, $tints[3]),
                '--glow-gold'        => '0 0 0 3px ' . self::rgba($c, .2),
            ];
        }
        if (isset($rgb['secondary'])) {
            $c = $rgb['secondary'];
            $vars += [
                '--violet'        => self::hex($c),
                '--violet-bright' => self::hex(self::mix($c, $away, .30)),
                '--violet-soft'   => self::rgba($c, $dark ? .13 : .09),
                '--violet-line'   => self::rgba($c, $dark ? .32 : .24),
            ];
        }
        if (isset($rgb['danger'])) {
            $c = $rgb['danger'];
            $vars += [
                '--danger'        => self::hex($c),
                '--danger-bright' => self::hex(self::mix($c, $away, .30)),
                // The filled hover of a danger button, with white on it:
                // darker than the base on either scheme.
                '--danger-deep'   => self::hex(self::mix($c, $black, .35)),
                '--danger-line'   => self::hex(self::mix($c, $ground, .60)),
                '--danger-tint'        => self::rgba($c, .08),
                '--danger-tint-strong' => self::rgba($c, $dark ? .25 : .16),
                '--danger-glow'        => self::rgba($c, $dark ? .15 : .12),
            ];
        }
        if (isset($rgb['success'])) {
            $vars['--ok'] = self::hex($rgb['success']);
        }
        if (isset($rgb['background'])) {
            $c = $rgb['background'];
            // Dark: every surface is a lightened step of the ground. Light:
            // the content surface and the text on a filled accent are lifted
            // towards white, while the chrome around the content and the
            // recessed fields sink -- a white card on a grey page, which is
            // how a light interface reads.
            $vars += $dark
                ? [
                    '--ink'     => self::hex($c),
                    '--surface' => self::hex(self::mix($c, $white, .015)),
                    '--shell'   => self::hex(self::mix($c, $white, .03)),
                    '--base'    => self::hex(self::mix($c, $white, .03)),
                    '--panel'   => self::hex(self::mix($c, $white, .055)),
                    '--line'    => self::hex(self::mix($c, $white, .13)),
                ]
                : [
                    '--ink'     => self::hex($c),
                    '--surface' => self::hex(self::mix($c, $black, .025)),
                    '--shell'   => self::hex(self::mix($c, $black, .035)),
                    '--base'    => self::hex(self::mix($c, $white, .92)),
                    '--panel'   => self::hex(self::mix($c, $white, .55)),
                    '--line'    => self::hex(self::mix($c, $black, .15)),
                ];
        }
        if (isset($rgb['text'])) {
            $c = $rgb['text'];
            // Muted text is the text moved towards the ground -- a shorter
            // way on the light scheme: a pale ground is much nearer to a
            // dark text in contrast terms than a dark ground is to a light
            // one, and the same step there leaves a grey that no longer reads.
            $vars += [
                '--text'       => self::hex($c),
                '--text-muted' => self::hex(self::mix($c, $ground, $dark ? .37 : .22)),
            ];
        }

        $lines = [];
        foreach ($vars as $name => $value) {
            $lines[] = $name . ':' . $value;
        }

        return self::selector($dark, $scope) . '{' . implode(';', $lines) . '}';
    }

    /**
     * Which selector a block has to carry. The light values live on
     * :root[data-theme="light"] in the stylesheet, and specificity beats
     * document order -- a block meant to override them has to be written
     * with the same selector or it loses, however late it comes.
     */
    private static function selector(bool $dark, string $scope): string
    {
        if ($dark) {
            return ':root';
        }

        return $scope === Theme::SYSTEM
            ? ':root[data-theme="' . Theme::SYSTEM . '"]'
            : ':root[data-theme="' . Theme::LIGHT . '"]';
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
