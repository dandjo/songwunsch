<?php

declare(strict_types=1);

/**
 * The colour palettes against each other.
 *
 * There are three palette blocks in assets/style.css and they have to agree:
 * the dark one on :root, the light one on :root[data-theme="light"], and the
 * light one again inside the prefers-color-scheme media query, for the
 * visitor who follows their device. CSS cannot say "these values, but only
 * inside a media query" -- a rule cannot span one -- so that third block is
 * a copy, and a copy is only safe while something insists it stays a copy.
 *
 * This checks:
 *
 * 1. every colour token declared in one palette is declared in the others
 *    (the shared non-colour tokens -- radii, the gutter -- are named below
 *    and expected only once);
 * 2. the two light blocks are character-for-character the same, so nobody
 *    edits one and forgets the other;
 * 3. every var(--token) the stylesheet uses is declared somewhere;
 * 4. no colour token is declared and never used;
 * 5. every shade and tint Colors::derive() makes of the seven built-in base
 *    colours (Colors::DEFAULTS, Colors::DEFAULTS_LIGHT -- what the Interface
 *    page shows as "Built-in") is what the block of that scheme carries. The
 *    blocks are literal because the stylesheet has to work with nothing
 *    configured, so a re-tune recomputes some thirty shades by hand; this
 *    catches a slipped digit and makes the stylesheet's "same ratios as
 *    src/Colors.php" a checked statement. The neutral veils and shadows are
 *    not derived from anything and stay a decision made by hand;
 * 6. the two tables in docs/interface.md name the same fourteen base colours,
 *    and its preset table has a row for every Colors::PRESETS entry;
 * 7. the built-in palettes and every preset (Colors::PRESETS) read: the
 *    contrast WCAG 2.2 AA asks for, on the surfaces the stylesheet actually
 *    puts each colour on -- 4.5:1 for text, 3:1 for the counter discs. This
 *    is what lets docs/accessibility.md make that claim, and what a new
 *    preset has to pass. The success colour is left out on purpose: it only
 *    frames notices and draws a tick, soft by design (docs/interface.md).
 *
 *   php tools/check-colors.php
 *
 * Exits 0 when everything agrees, 1 with a report when it does not.
 */

require __DIR__ . '/../src/bootstrap.php';

use Songwunsch\Colors;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$cssFile = dirname(__DIR__) . '/assets/style.css';
$css     = (string) file_get_contents($cssFile);

/** Tokens that belong to one palette only, because they are not colours. */
const SHARED = ['--radius-sm', '--radius', '--radius-lg', '--radius-pill', '--gutter'];

/** The declarations of one block, as [token => value] plus the raw body. */
function block(string $css, string $selector): array
{
    $at = strpos($css, $selector . ' {');
    if ($at === false) {
        fwrite(STDERR, "no block for {$selector} in assets/style.css\n");
        exit(1);
    }
    $from = strpos($css, '{', $at) + 1;
    $to   = strpos($css, "\n}", $from);
    $body = substr($css, $from, $to - $from);

    preg_match_all('/(--[a-z0-9-]+)\s*:\s*([^;]+);/i', $body, $m, PREG_SET_ORDER);
    $out = [];
    foreach ($m as $one) {
        $out[$one[1]] = trim($one[2]);
    }

    return ['tokens' => $out, 'body' => $body];
}

$dark   = block($css, ':root');
$light  = block($css, ':root[data-theme="light"]');
$system = block($css, ':root[data-theme="system"]');

$problems = [];

// 1. The same token names everywhere.
foreach (['light' => $light, 'system' => $system] as $name => $other) {
    foreach ($dark['tokens'] as $token => $value) {
        if (in_array($token, SHARED, true)) {
            continue;
        }
        if (!isset($other['tokens'][$token])) {
            $problems[] = "{$token} is declared for dark but not for {$name}";
        }
    }
    foreach ($other['tokens'] as $token => $value) {
        if (!isset($dark['tokens'][$token])) {
            $problems[] = "{$token} is declared for {$name} but not for dark";
        }
    }
}

// 2. The two light blocks are one and the same.
if (trim($light['body']) !== trim($system['body'])) {
    $problems[] = 'the light block and its copy in the prefers-color-scheme'
        . ' media query differ -- copy the first over the second';
}

// 3. and 4. Used and declared.
preg_match_all('/var\((--[a-z0-9-]+)/i', $css, $m);
$used = array_unique($m[1]);
// Tokens a rule declares for itself, further down the stylesheet -- a
// declaration is the only place a token name is followed by a colon.
preg_match_all('/(--[a-z0-9-]+)\s*:/i', $css, $m);
$declaredAnywhere = array_unique(array_merge($m[1], array_keys($dark['tokens'])));

foreach ($used as $token) {
    if (str_starts_with($token, '--ck-')) {
        continue;   // the editor's own, declared by CKEditor's stylesheet
    }
    if (!in_array($token, $declaredAnywhere, true)) {
        $problems[] = "var({$token}) is used but declared nowhere";
    }
}
foreach ($dark['tokens'] as $token => $value) {
    if (!in_array($token, $used, true) && !in_array($token, SHARED, true)) {
        $problems[] = "{$token} is declared but never used";
    }
}

/**
 * One colour value in a form two spellings of it agree on: '#fff' and
 * '#ffffff', 'rgba(5, 8, 10, .06)' and 'rgba(5, 8, 10, 0.06)'. Colors writes
 * the long forms, the stylesheet the short ones.
 */
function normalise(string $value): string
{
    $value = strtolower(trim($value));
    $rgb   = Colors::parse($value);
    if ($rgb !== null) {
        return Colors::hex($rgb);
    }

    return (string) preg_replace(['/\s+/', '/(?<![0-9])\./'], ['', '0.'], $value);
}

// 5. Every shade and tint derived from the built-in base colours is what the
//    block of that scheme carries. First the bases themselves have to parse:
//    an entry derive() cannot read drops its whole family from the result,
//    and a comparison over what was emitted would not notice.
foreach ([true, false] as $isDark) {
    $scheme   = $isDark ? 'dark' : 'light';
    $constant = 'Colors::' . ($isDark ? 'DEFAULTS' : 'DEFAULTS_LIGHT');
    $defaults = Colors::defaults($isDark);
    $tokens   = $isDark ? $dark['tokens'] : $light['tokens'];
    foreach (Colors::AREAS as $area) {
        if (Colors::parse((string) ($defaults[$area] ?? '')) === null) {
            $problems[] = sprintf('%s has no #rrggbb for %s', $constant, $area);
        }
    }
    foreach (Colors::derive($defaults, $isDark) as $token => $derived) {
        $inCss = normalise((string) ($tokens[$token] ?? ''));
        if ($inCss !== normalise($derived)) {
            $problems[] = sprintf(
                'the %s block says %s for %s, %s derives %s',
                $scheme,
                $inCss === '' ? '(nothing)' : $inCss,
                $token,
                $constant,
                $derived
            );
        }
    }
}

// 6. docs/interface.md names the base colours in two tables: the dark
//    defaults under "Default (dark)", the light ones under "Light default".
//    The rows are read from the table they stand in, whatever else the file
//    says; a stale table is what the Interface page's "Built-in" hint would
//    contradict.
$doc = (string) file_get_contents(dirname(__DIR__) . '/docs/interface.md');
$rowPattern = '/^\|\s*(' . implode('|', Colors::AREAS) . ')\s*\|.*`(#[0-9a-f]{3}|#[0-9a-f]{6})`\s*\|\s*$/mi';
foreach (['Default (dark)' => true, 'Light default' => false] as $heading => $isDark) {
    $constant = 'Colors::' . ($isDark ? 'DEFAULTS' : 'DEFAULTS_LIGHT');
    $from     = strpos($doc, '| ' . $heading . ' |');
    if ($from === false) {
        $problems[] = "docs/interface.md has no table headed \"{$heading}\"";
        continue;
    }
    // A table ends at the first blank line after its heading.
    $to    = strpos($doc, "\n\n", $from) ?: strlen($doc);
    $table = substr($doc, $from, $to - $from);
    preg_match_all($rowPattern, $table, $m, PREG_SET_ORDER);
    $rows = [];
    foreach ($m as [, $area, $hex]) {
        $rows[strtolower($area)] = normalise($hex);
    }
    foreach (Colors::defaults($isDark) as $area => $said) {
        if (($rows[$area] ?? '') !== normalise($said)) {
            $problems[] = sprintf(
                'the "%s" table in docs/interface.md names %s for %s, %s says %s',
                $heading,
                $rows[$area] ?? '(nothing)',
                $area,
                $constant,
                $said
            );
        }
    }
}

// 6b. Every preset is named in the preset table of docs/interface.md -- the
//     page offers them, the docs have to say what each one is.
foreach (Colors::PRESETS as $key => $preset) {
    if (!str_contains($doc, '| ' . $preset['name'] . ' |')) {
        $problems[] = sprintf(
            'docs/interface.md has no row for the preset %s ("%s")',
            $key,
            $preset['name']
        );
    }
}

// 7. Contrast. The pairs are the ones the stylesheet draws: which token is
//    written on which surface. Everything else a scheme shows is a tint of
//    these or a hover state one step away from them.
const PAIRS = [
    // [text token, surface token, minimum, what it is]
    ['--text',          '--panel',       4.5, 'text on the content surface'],
    ['--text',          '--ink',         4.5, 'text on the page ground'],
    ['--text-muted',    '--panel',       4.5, 'muted text on the content surface'],
    ['--text-muted',    '--shell',       4.5, 'the label of a tab that is not the current page'],
    ['--base',          '--gold',        4.5, 'the label of a filled accent button'],
    ['--gold',          '--panel',       4.5, 'links and the framed accent buttons'],
    ['--violet-bright', '--panel',       4.5, 'the text of a tag, and Edit in a list'],
    ['--violet-bright', '--shell',       4.5, '"wunsch" in the word mark'],
    ['--base',          '--violet',      4.5, 'the label of the sort chip that is on'],
    ['--base',          '--steel',       3.0, 'the number on a counter disc'],
    ['--violet',        '--shell',       3.0, 'the dot that says there is something new'],
    ['--gold-bright',   '--shell',       4.5, 'the room name in the header and a tab under the pointer'],
    ['--danger',        '--panel',       4.5, 'the framed danger buttons'],
    ['--text-strong',   '--danger-deep', 4.5, 'the label of a filled danger button'],
];

/** WCAG relative luminance of an [r, g, b]. */
function luminance(array $rgb): float
{
    $lin = array_map(static function (int $v): float {
        $v /= 255;

        return $v <= .03928 ? $v / 12.92 : (($v + .055) / 1.055) ** 2.4;
    }, $rgb);

    return .2126 * $lin[0] + .7152 * $lin[1] + .0722 * $lin[2];
}

/** WCAG contrast ratio of two '#rrggbb'. */
function contrast(string $a, string $b): float
{
    $la = luminance((array) Colors::parse($a));
    $lb = luminance((array) Colors::parse($b));

    return (max($la, $lb) + .05) / (min($la, $lb) + .05);
}

$palettes = ['the built-in palette' => ['dark' => Colors::DEFAULTS, 'light' => Colors::DEFAULTS_LIGHT]];
foreach (Colors::PRESETS as $key => $preset) {
    $palettes["preset {$key}"] = ['dark' => $preset['dark'], 'light' => $preset['light']];
}
foreach ($palettes as $what => $sets) {
    foreach ($sets as $scheme => $set) {
        foreach (Colors::AREAS as $area) {
            if (Colors::parse((string) ($set[$area] ?? '')) === null) {
                $problems[] = "{$what} has no #rrggbb for {$area} ({$scheme})";
                continue 2;
            }
        }
        // The tokens that are not derived from a base colour (--text-strong)
        // come from the stylesheet's block of that scheme.
        $tokens = ($scheme === 'dark' ? $dark['tokens'] : $light['tokens']);
        $tokens = Colors::derive($set, $scheme === 'dark') + array_map('normalise', $tokens);
        foreach (PAIRS as [$fg, $bg, $min, $where]) {
            $ratio = contrast($tokens[$fg], $tokens[$bg]);
            if ($ratio < $min) {
                $problems[] = sprintf(
                    '%s, %s: %s on %s is %.1f:1, %s needs %.1f:1',
                    $what,
                    $scheme,
                    $tokens[$fg],
                    $tokens[$bg],
                    $ratio,
                    $where,
                    $min
                );
            }
        }
    }
}

$count = count($dark['tokens']);
if ($problems === []) {
    printf("\n%d token(s) per palette, 3 blocks checked, 0 failure(s).\n", $count);
    exit(0);
}

fwrite(STDERR, "\n" . count($problems) . " failure(s):\n");
foreach ($problems as $problem) {
    fwrite(STDERR, '  - ' . $problem . "\n");
}
exit(1);
