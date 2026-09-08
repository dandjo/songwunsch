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
 *    (the shared non-colour tokens -- radii, the gutter, the derived glow --
 *    are named below and expected only once);
 * 2. the two light blocks are character-for-character the same, so nobody
 *    edits one and forgets the other;
 * 3. every var(--token) the stylesheet uses is declared somewhere;
 * 4. no colour token is declared and never used;
 * 5. Colors::DEFAULTS and Colors::DEFAULTS_LIGHT still name the six base
 *    colours the two blocks actually carry -- they are what the Interface
 *    page shows as "Built-in".
 *
 *   php tools/check-colors.php
 *
 * Exits 0 when everything agrees, 1 with a report when it does not.
 */

require __DIR__ . '/../src/bootstrap.php';

use Songwunsch\Colors;

$cssFile = dirname(__DIR__) . '/assets/style.css';
$css     = (string) file_get_contents($cssFile);

/** Tokens that belong to one palette only: not colours, or derived from one. */
const SHARED = [
    '--radius-sm', '--radius', '--radius-lg', '--radius-pill', '--gutter',
    // Built from --gold-tint-strong, so it follows the palette by itself.
    '--glow-gold',
];

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

// 5. The built-in colours the Interface page promises.
$expected = [
    'dark'  => [Colors::DEFAULTS, $dark['tokens']],
    'light' => [Colors::DEFAULTS_LIGHT, $light['tokens']],
];
$base = ['accent' => '--gold', 'secondary' => '--violet', 'danger' => '--danger',
         'success' => '--ok', 'background' => '--ink', 'text' => '--text'];
foreach ($expected as $scheme => [$defaults, $tokens]) {
    foreach ($base as $area => $token) {
        $inCss = strtolower(trim((string) ($tokens[$token] ?? '')));
        $inCss = (string) preg_replace('/\s*\/\*.*$/', '', $inCss);
        $said  = strtolower((string) $defaults[$area]);
        // #fff and #ffffff are the same colour.
        $a = Colors::parse($inCss);
        $b = Colors::parse($said);
        if ($a === null || $b === null || $a !== $b) {
            $problems[] = sprintf(
                'Colors::%s says %s for %s, the stylesheet says %s for %s',
                $scheme === 'dark' ? 'DEFAULTS' : 'DEFAULTS_LIGHT',
                $said,
                $area,
                $inCss === '' ? '(nothing)' : $inCss,
                $token
            );
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
