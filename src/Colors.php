<?php

declare(strict_types=1);

namespace Songwunsch;

/**
 * The site's colours, set by the admins under Administration -> Interface.
 *
 * There are two schemes (Theme) and therefore two sets of seven: one base
 * colour per area of use -- primary, secondary, accent, danger, success,
 * background, text -- for the dark scheme in `colors.<area>` and for the
 * light one in `colors.light.<area>`.
 *
 * The first three are the interface's own three voices, and they are meant
 * to be read together: primary is the menu and everything a visitor acts on
 * (buttons, links, every tab and the room switcher), secondary is the
 * counter discs those tabs carry, accent is what stands out from both (the
 * word mark, the tags, what an editor does, the frame of an info notice). A palette that gives the three the same hue reads as one colour
 * used three times; that is a choice, not a mistake. The stylesheet carries both sets as the built-in
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
    public const AREAS = ['primary', 'secondary', 'accent', 'danger', 'success', 'background', 'text'];

    /** The stylesheet's dark colours (assets/style.css, :root). */
    public const DEFAULTS = [
        'primary'   => '#93c5fd',
        'secondary' => '#60a5fa',
        'accent'    => '#fbbf24',
        'danger'    => '#f87171',
        'success'   => '#4ade80',
        'background' => '#111827',
        'text'      => '#f3f4f6',
    ];

    /** The stylesheet's light colours (assets/style.css, :root[data-theme="light"]). */
    public const DEFAULTS_LIGHT = [
        'primary'   => '#1e40af',
        'secondary' => '#3b82f6',
        'accent'    => '#b45309',
        'danger'    => '#b91c1c',
        'success'   => '#15803d',
        'background' => '#f3f4f6',
        'text'      => '#1f2937',
    ];

    /**
     * Settings key of the admins' own palette: the fourteen colours they kept
     * with "Save as my palette", as JSON in one row. One row rather than
     * fourteen, because it is one thing -- a palette is only useful whole --
     * and Settings reads the table in one query anyway.
     */
    public const OWN_KEY = 'colors.own';

    /**
     * Ready-made pairs of sets, offered on the Interface page: one click fills
     * all fourteen fields, saving is still the admins' own step. Each preset is
     * a dark set and a light one built from the same colour families, so the
     * switch in the header keeps the character of the design.
     *
     * The families are the Tailwind CSS colour scale (MIT), the pairings are
     * after the role palettes at mypalettetool.com, which name the same three
     * voices this application does: their primary, secondary and accent are
     * ours, their neutral is the ground. A red and a green for danger and
     * success are the same in every preset --
     * those two mean something, they do not brand. Every preset passes the
     * contrast checks tools/check-colors.php runs, in both schemes; a new one
     * has to as well. The order is the order the page shows them in, and
     * Corporate Blue comes first because it is what the stylesheet carries as
     * the built-in palette: it is offered like any other, so a set of fields
     * that has wandered can be put back to it in one click.
     *
     * @var array<string,array{name:string,dark:array<string,string>,light:array<string,string>}>
     */
    public const PRESETS = [
        'corporate-blue' => [
            'name'  => 'Corporate Blue',
            'dark' => ['primary' => '#93c5fd', 'secondary' => '#60a5fa', 'accent' => '#fbbf24', 'danger' => '#f87171', 'success' => '#4ade80', 'background' => '#111827', 'text' => '#f3f4f6'],
            'light' => ['primary' => '#1e40af', 'secondary' => '#3b82f6', 'accent' => '#b45309', 'danger' => '#b91c1c', 'success' => '#15803d', 'background' => '#f3f4f6', 'text' => '#1f2937'],
        ],
        'slate-teal' => [
            'name'  => 'Slate & Teal',
            'dark' => ['primary' => '#cbd5e1', 'secondary' => '#5eead4', 'accent' => '#fbbf24', 'danger' => '#f87171', 'success' => '#4ade80', 'background' => '#0f172a', 'text' => '#f1f5f9'],
            'light' => ['primary' => '#475569', 'secondary' => '#0d9488', 'accent' => '#b45309', 'danger' => '#b91c1c', 'success' => '#15803d', 'background' => '#f1f5f9', 'text' => '#1e293b'],
        ],
        'autumn-harvest' => [
            'name'  => 'Autumn Harvest',
            'dark' => ['primary' => '#fbbf24', 'secondary' => '#fdba74', 'accent' => '#a3e635', 'danger' => '#f87171', 'success' => '#4ade80', 'background' => '#1c1917', 'text' => '#fffbeb'],
            'light' => ['primary' => '#b45309', 'secondary' => '#92400e', 'accent' => '#4d7c0f', 'danger' => '#b91c1c', 'success' => '#15803d', 'background' => '#fef3c7', 'text' => '#451a03'],
        ],
        'coral-cream' => [
            'name'  => 'Coral & Cream',
            'dark' => ['primary' => '#fda4af', 'secondary' => '#fcd34d', 'accent' => '#5eead4', 'danger' => '#f87171', 'success' => '#4ade80', 'background' => '#1c1917', 'text' => '#fff1f2'],
            'light' => ['primary' => '#9f1239', 'secondary' => '#a16207', 'accent' => '#0f766e', 'danger' => '#b91c1c', 'success' => '#15803d', 'background' => '#fff1f2', 'text' => '#881337'],
        ],
        'earth-tones' => [
            'name'  => 'Earth Tones',
            'dark' => ['primary' => '#d6d3d1', 'secondary' => '#a8a29e', 'accent' => '#86efac', 'danger' => '#f87171', 'success' => '#4ade80', 'background' => '#1c1917', 'text' => '#fafaf9'],
            'light' => ['primary' => '#57534e', 'secondary' => '#78716c', 'accent' => '#15803d', 'danger' => '#b91c1c', 'success' => '#15803d', 'background' => '#fafaf9', 'text' => '#292524'],
        ],
        'clean-minimal' => [
            'name'  => 'Clean Minimal',
            'dark' => ['primary' => '#e5e5e5', 'secondary' => '#a3a3a3', 'accent' => '#93c5fd', 'danger' => '#f87171', 'success' => '#4ade80', 'background' => '#0a0a0a', 'text' => '#fafafa'],
            'light' => ['primary' => '#171717', 'secondary' => '#404040', 'accent' => '#1d4ed8', 'danger' => '#b91c1c', 'success' => '#15803d', 'background' => '#fafafa', 'text' => '#171717'],
        ],
        'swiss-design' => [
            'name'  => 'Swiss Design',
            'dark' => ['primary' => '#fca5a5', 'secondary' => '#d4d4d4', 'accent' => '#e5e5e5', 'danger' => '#f87171', 'success' => '#4ade80', 'background' => '#0a0a0a', 'text' => '#fafafa'],
            'light' => ['primary' => '#b91c1c', 'secondary' => '#404040', 'accent' => '#525252', 'danger' => '#b91c1c', 'success' => '#15803d', 'background' => '#f9fafb', 'text' => '#000000'],
        ],
        'black-gold' => [
            'name'  => 'Black & Gold',
            'dark' => ['primary' => '#facc15', 'secondary' => '#d4d4d4', 'accent' => '#fde68a', 'danger' => '#f87171', 'success' => '#4ade80', 'background' => '#0a0a0a', 'text' => '#fafafa'],
            'light' => ['primary' => '#a16207', 'secondary' => '#404040', 'accent' => '#854d0e', 'danger' => '#b91c1c', 'success' => '#15803d', 'background' => '#fafafa', 'text' => '#171717'],
        ],
        'forest-green' => [
            'name'  => 'Forest Green',
            'dark' => ['primary' => '#86efac', 'secondary' => '#4ade80', 'accent' => '#fbbf24', 'danger' => '#f87171', 'success' => '#4ade80', 'background' => '#0c1a12', 'text' => '#f0fdf4'],
            'light' => ['primary' => '#15803d', 'secondary' => '#166534', 'accent' => '#b45309', 'danger' => '#b91c1c', 'success' => '#15803d', 'background' => '#f0fdf4', 'text' => '#14532d'],
        ],
        'holiday-magic' => [
            'name'  => 'Holiday Magic',
            'dark' => ['primary' => '#fca5a5', 'secondary' => '#86efac', 'accent' => '#fbbf24', 'danger' => '#f87171', 'success' => '#4ade80', 'background' => '#1c1917', 'text' => '#fffbeb'],
            'light' => ['primary' => '#b91c1c', 'secondary' => '#15803d', 'accent' => '#a16207', 'danger' => '#b91c1c', 'success' => '#15803d', 'background' => '#fffbeb', 'text' => '#451a03'],
        ],
        'electric-neon' => [
            'name'  => 'Electric Neon',
            'dark' => ['primary' => '#22d3ee', 'secondary' => '#f472b6', 'accent' => '#fbbf24', 'danger' => '#f87171', 'success' => '#4ade80', 'background' => '#18181b', 'text' => '#fafafa'],
            'light' => ['primary' => '#0e7490', 'secondary' => '#db2777', 'accent' => '#a16207', 'danger' => '#b91c1c', 'success' => '#15803d', 'background' => '#fafafa', 'text' => '#18181b'],
        ],
        'retro-gaming' => [
            'name'  => 'Retro Gaming',
            'dark' => ['primary' => '#a78bfa', 'secondary' => '#22d3ee', 'accent' => '#fbbf24', 'danger' => '#f87171', 'success' => '#4ade80', 'background' => '#1e1b4b', 'text' => '#f5f3ff'],
            'light' => ['primary' => '#6d28d9', 'secondary' => '#0e7490', 'accent' => '#a16207', 'danger' => '#b91c1c', 'success' => '#15803d', 'background' => '#f5f3ff', 'text' => '#1e1b4b'],
        ],
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

    /**
     * The admins' own palette, in the shape PRESETS uses, or null when they
     * have not kept one. A row that cannot be read is treated as absent: it
     * is a convenience, and a broken one must not take the page down.
     *
     * @return array{name:string,dark:array<string,string>,light:array<string,string>}|null
     */
    public static function own(Settings $settings): ?array
    {
        $stored = json_decode((string) $settings->get(self::OWN_KEY, ''), true);
        if (!is_array($stored)) {
            return null;
        }

        $out = [];
        foreach (['dark', 'light'] as $scheme) {
            foreach (self::AREAS as $area) {
                $rgb = self::parse((string) ($stored[$scheme][$area] ?? ''));
                if ($rgb === null) {
                    return null;
                }
                $out[$scheme][$area] = self::hex($rgb);
            }
        }

        return ['name' => t('My palette')] + $out;
    }

    /**
     * Keep the fourteen colours as the admins' own palette. An area left empty
     * is the built-in colour of its scheme, so that is what gets written --
     * a palette has to be able to fill every field it offers.
     *
     * @param array<string,string> $values field name => '#rrggbb' or '', from validate()
     */
    public static function saveOwn(Settings $settings, array $values): void
    {
        $palette = [];
        foreach ([true => 'dark', false => 'light'] as $dark => $scheme) {
            foreach (self::AREAS as $area) {
                $typed = (string) ($values[self::field($area, (bool) $dark)] ?? '');
                $palette[$scheme][$area] = $typed !== '' ? $typed : self::defaults((bool) $dark)[$area];
            }
        }

        $settings->set(self::OWN_KEY, (string) json_encode($palette));
    }

    /**
     * Move what an installation stored as `accent` to `primary`.
     *
     * Until the interface grew its third voice, `accent` was what `primary`
     * is now -- the colour of the buttons and links -- and `accent` is now
     * the tags and the counters. An installation that kept a colour under
     * the old name would therefore have it land in the wrong place, quietly.
     * This moves it once and takes the old row away; run from
     * tools/install.php and safe to run again, because there is nothing left
     * to move the second time.
     *
     * @return array<int,string> the keys that were moved
     */
    public static function migrateAccentToPrimary(Settings $settings): array
    {
        $moved = [];
        foreach ([self::PREFIX, self::PREFIX_LIGHT] as $prefix) {
            $stored = $settings->withPrefix($prefix);
            if (!isset($stored['accent']) || isset($stored['primary'])) {
                continue;
            }
            $settings->set($prefix . 'primary', (string) $stored['accent']);
            $settings->delete($prefix . 'accent');
            $moved[] = $prefix . 'accent';
        }

        return $moved;
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
        $vars = self::derive($colors, $dark);
        if ($vars === []) {
            return '';
        }

        $lines = [];
        foreach ($vars as $name => $value) {
            $lines[] = $name . ':' . $value;
        }

        return self::selector($dark, $scope) . '{' . implode(';', $lines) . '}';
    }

    /**
     * Every token a scheme's block carries for the given base colours,
     * token => value; empty when nothing is set. The derivation stands apart
     * from css() so tools/check-colors.php can hold the stylesheet's built-in
     * blocks against it as values, without parsing the CSS css() writes.
     *
     * @param array<string,mixed> $colors area => '#rrggbb'; '' or missing = default
     * @return array<string,string>
     */
    public static function derive(array $colors, bool $dark): array
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
            return [];
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

        // What a visitor acts on.
        if (isset($rgb['primary'])) {
            $c = $rgb['primary'];
            $vars += [
                '--gold'             => self::hex($c),
                '--gold-bright'      => self::hex(self::mix($c, $away, .30)),
                '--gold-deep'        => self::hex(self::mix($c, $ground, $frame)),
                '--gold-wash'        => self::rgba($c, $tints[0]),
                '--gold-tint'        => self::rgba($c, $tints[1]),
                '--gold-tint-mid'    => self::rgba($c, $tints[2]),
                '--gold-tint-strong' => self::rgba($c, $tints[3]),
            ];
        }
        // The counter discs on the tabs, and nothing else: a supporting
        // colour on a primary element. It carries a number, so the text on
        // it is what has to read -- no shades are derived, because there is
        // no hover and no frame to derive them for.
        if (isset($rgb['secondary'])) {
            $vars['--steel'] = self::hex($rgb['secondary']);
        }
        // What stands out from both: tags, the counters on the tabs, the
        // frame of an info notice.
        if (isset($rgb['accent'])) {
            $c = $rgb['accent'];
            $vars += [
                '--violet'        => self::hex($c),
                '--violet-bright' => self::hex(self::mix($c, $away, .30)),
                // The faintest of the three: it lies under a whole row of a
                // list, where the muted text has to keep reading.
                '--violet-wash'   => self::rgba($c, $tints[0]),
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

        return $vars;
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
    public static function hex(array $c): string
    {
        return sprintf('#%02x%02x%02x', $c[0], $c[1], $c[2]);
    }

    /** @param array{0:int,1:int,2:int} $c */
    private static function rgba(array $c, float $alpha): string
    {
        return sprintf('rgba(%d, %d, %d, %s)', $c[0], $c[1], $c[2], rtrim(rtrim(number_format($alpha, 3, '.', ''), '0'), '.'));
    }
}
