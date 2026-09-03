<?php

declare(strict_types=1);

/**
 * Autoloader and small helpers for the front controller.
 */

spl_autoload_register(static function (string $class): void {
    $prefix = 'Songwunsch\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

/**
 * The translator for this request. index.php sets it once the language is
 * known; until then t() returns the English source text unchanged.
 */
function translator(?\Songwunsch\Translator $set = null): ?\Songwunsch\Translator
{
    /** @var \Songwunsch\Translator|null $instance */
    static $instance = null;

    if ($set !== null) {
        $instance = $set;
    }

    return $instance;
}

/**
 * Translate a UI string. The English text is the message id; {placeholders}
 * are filled from $args. Escape the result for HTML at the point of output.
 *
 * @param array<string,scalar|null> $args
 */
function t(string $message, array $args = [], ?string $context = null): string
{
    $tr = translator();
    if ($tr === null) {
        return $args === [] ? $message : strtr($message, array_combine(
            array_map(static fn ($k): string => '{' . $k . '}', array_keys($args)),
            array_map('strval', $args),
        ));
    }

    return $tr->t($message, $args, $context);
}

/**
 * Translate a string with a count; {n} is filled automatically.
 *
 * @param array<string,scalar|null> $args
 */
function tn(string $singular, string $plural, int $count, array $args = [], ?string $context = null): string
{
    $tr = translator();
    if ($tr === null) {
        return t(abs($count) === 1 ? $singular : $plural, $args + ['n' => $count]);
    }

    return $tr->n($singular, $plural, $count, $args, $context);
}

/**
 * Base path the application is mounted under: '' for the domain root,
 * otherwise with a leading and without a trailing slash, e.g. '/songliste'.
 *
 * Without an argument the value is read, with an argument it is set (done by
 * index.php once config.php is loaded). Until something is set, the BASE_PATH
 * environment variable applies -- so render_fatal(), which runs before the
 * configuration, works too.
 */
function base_path(?string $value = null): string
{
    /** @var string|null $base */
    static $base = null;

    if ($value !== null) {
        $base = normalize_base_path($value);
    }

    return $base ??= normalize_base_path((string) getenv('BASE_PATH'));
}

/**
 * '/songliste/', 'songliste', '//songliste' -> '/songliste';
 * '', '/' -> '' (domain root). Characters outside the whitelist are dropped,
 * so a typo in the configuration never ends up in a Location header.
 */
function normalize_base_path(string $value): string
{
    $value = preg_replace('#[^A-Za-z0-9/_.-]#', '', trim($value)) ?? '';
    $value = '/' . trim($value, '/');

    return $value === '/' ? '' : $value;
}

/**
 * The room of this request. index.php sets it once the route is known; url()
 * then keeps room-bound pages (songs, wishes, suggestions, room_songs) inside
 * that room.
 * The default room has no slug and lives at the base path itself.
 *
 * @param array<string,mixed>|null $set
 * @return array<string,mixed>
 */
function current_room(?array $set = null): array
{
    /** @var array<string,mixed>|null $room */
    static $room = null;

    if ($set !== null) {
        $room = $set;
    }

    return $room ?? \Songwunsch\RoomRepository::defaultRoom();
}

/**
 * Build an address, including the base path. 'p' names the page and becomes
 * the path: '/wishes', '/login', ... The start page (songs) is the base path
 * itself, so url() without 'p' is also the target of every form. All other
 * parameters go into the query string.
 *
 * Pages that belong to a room (songs, wishes, suggestions, room_songs) are
 * placed in the current room: /rooms/<slug>, /rooms/<slug>/wishes,
 * /rooms/<slug>/suggestions, /rooms/<slug>/manage. 'room' overrides that --
 * a slug for another room, '' for the default room.
 */
function url(array $params = []): string
{
    $page = (string) ($params['p'] ?? 'songs');
    $slug = array_key_exists('room', $params)
        ? (string) $params['room']
        : (string) (current_room()['slug'] ?? '');
    unset($params['p'], $params['room']);

    $params = array_filter($params, static fn ($v): bool => $v !== null && $v !== '');

    $target = base_path();
    if (in_array($page, ['songs', 'wishes', 'suggestions', 'room_songs'], true)) {
        $prefix  = $slug !== '' ? '/rooms/' . $slug : '';
        // A room's song list is the room itself: /rooms/<slug> without a
        // trailing slash; only the default room is the bare base path '/'.
        $target .= match ($page) {
            'songs'       => $prefix !== '' ? $prefix : '/',
            'wishes'      => $prefix . '/wishes',
            'suggestions' => $prefix . '/suggestions',
            'room_songs'  => $prefix . '/manage',
        };
    } else {
        $target .= '/' . $page;
    }

    return $params === [] ? $target : $target . '?' . http_build_query($params);
}

/**
 * Version of the bundled files, from config.php ('version'). index.php sets
 * it; asset() appends it as a cache buster.
 */
function asset_version(?string $set = null): string
{
    /** @var string $version */
    static $version = '';

    if ($set !== null) {
        $version = trim($set);
    }

    return $version;
}

/**
 * Inline SVG icon in front of a button label -- decorative, hidden from
 * assistive technology; the label carries the meaning. One place for the
 * paths so every button draws the same glyph. Unknown names yield nothing.
 * $trailing marks a glyph placed after its label (spacing mirrors).
 */
function icon(string $name, int $size = 16, bool $trailing = false): string
{
    // Every glyph fills the same optical box, roughly 2..14 of the 16-unit
    // grid: outlines reach 2/14 with their stroke, filled shapes stop a little
    // earlier because solid ink reads larger than lines.
    $paths = [
        'plus'   => '<path d="M8 3.3v9.4M3.3 8h9.4" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"/>',
        'pause'  => '<rect x="2.8" y="2.5" width="3.8" height="11" rx="1.2" fill="currentColor"/><rect x="9.4" y="2.5" width="3.8" height="11" rx="1.2" fill="currentColor"/>',
        'play'   => '<path d="M3.5 3.2v9.6a.8.8 0 0 0 1.2.7l8.4-4.8a.8.8 0 0 0 0-1.4L4.7 2.5a.8.8 0 0 0-1.2.7z" fill="currentColor"/>',
        'trash'  => '<path d="M5.8 1.2h4.4l.5 1.3h4v2H1.3v-2h4z" fill="currentColor"/><path fill-rule="evenodd" d="M2.3 5.7h11.4l-.9 8.2a1 1 0 0 1-1 .9H4.2a1 1 0 0 1-1-.9zM5.4 7.5h1.5v5.3H5.4zm3.7 0h1.5v5.3H9.1z" fill="currentColor"/>',
        'list'   => '<path d="M3.1 3.6h9.8M3.1 8h9.8M3.1 12.4h9.8" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>',
        'key'    => '<circle cx="5.4" cy="10.6" r="2.8" fill="none" stroke="currentColor" stroke-width="2.2"/><path d="M7.4 8.6 13.4 2.6M10.8 5.2l2 2M12.6 3.4l1.4 1.4" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>',
        'note'   => '<path d="M6.3 11.8V2.9l7.4-1.6v9.4" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linejoin="round"/><circle cx="3.8" cy="12.3" r="2.7" fill="currentColor"/><circle cx="12" cy="10.6" r="2.7" fill="currentColor"/>',
        'arrow-right' => '<path d="M2.6 8h10.2M8.6 3.6 13 8l-4.4 4.4" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/>',
        'arrow-left'  => '<path d="M13.4 8H3.2M7.4 3.6 3 8l4.4 4.4" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/>',
        // Rooms: a door with a knob. Users: two people, one in front.
        'door'   => '<path d="M3 14V2h8v12" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linejoin="round"/><path d="M1.5 14h13" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/><circle cx="8.6" cy="8.2" r="1.3" fill="currentColor"/>',
        // A clock -- when the wish came in.
        'clock'  => '<circle cx="8" cy="8" r="6" fill="none" stroke="currentColor" stroke-width="2"/><path d="M8 4.6V8l2.4 1.7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
        // One person -- who wished; the same figure as the account icon.
        'user'   => '<circle cx="8" cy="4.6" r="3.1" fill="currentColor"/><path d="M1.8 14.6c0-3.6 2.7-5.9 6.2-5.9s6.2 2.3 6.2 5.9z" fill="currentColor"/>',
        'users'  => '<circle cx="6" cy="5" r="3" fill="currentColor"/><path d="M.8 14.2c0-3.2 2.3-5.2 5.2-5.2s5.2 2 5.2 5.2z" fill="currentColor"/><circle cx="11.6" cy="5.6" r="2.3" fill="currentColor"/><path d="M12.4 14.2h3c0-2.7-1.6-4.4-3.9-4.6a6 6 0 0 1 .9 4.6z" fill="currentColor"/>',
        'pencil' => '<path d="M1.2 14.8v-3.8l9.4-9.4 3.8 3.8-9.4 9.4z" fill="currentColor"/><path d="M9.8 2.4l3.8 3.8" fill="none" stroke="var(--panel, #1b1e2a)" stroke-width="1.3"/>',
        'star'   => '<path d="M8 1 9.95 5.95 15.2 6.3 11.1 9.65 12.45 14.8 8 12 3.55 14.8 4.9 9.65.8 6.3 6.05 5.95z" fill="currentColor" stroke="currentColor" stroke-width=".8" stroke-linejoin="round"/>',
        'check'  => '<path d="M3.3 8.4 6.7 11.8 12.7 4.8" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/>',
        'cross'  => '<path d="M3.6 3.6l8.8 8.8M12.4 3.6l-8.8 8.8" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/>',
        // Settings: a cog. Generated: 8 teeth on radius 5.3..7, hole 2.4.
        'gear'   => '<path fill-rule="evenodd" d="M13.2 7.0 L14.9 7.1 L14.9 8.9 L13.2 9.0 L12.4 11.0 L13.5 12.3 L12.3 13.5 L11.0 12.4 L9.0 13.2 L8.9 14.9 L7.1 14.9 L7.0 13.2 L5.0 12.4 L3.7 13.5 L2.5 12.3 L3.6 11.0 L2.8 9.0 L1.1 8.9 L1.1 7.1 L2.8 7.0 L3.6 5.0 L2.5 3.7 L3.7 2.5 L5.0 3.6 L7.0 2.8 L7.1 1.1 L8.9 1.1 L9.0 2.8 L11.0 3.6 L12.3 2.5 L13.5 3.7 L12.4 5.0z M8 5.6a2.4 2.4 0 1 0 0 4.8a2.4 2.4 0 1 0 0-4.8z" fill="currentColor"/>',
        // Guest view: an eye.
        'eye'    => '<path d="M1.6 8c1.8-3.3 3.9-4.9 6.4-4.9S12.6 4.7 14.4 8c-1.8 3.3-3.9 4.9-6.4 4.9S3.4 11.3 1.6 8z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><circle cx="8" cy="8" r="2.1" fill="currentColor"/>',
        // Wish list: to the very top / bottom -- a bar with a filled triangle.
        'to-top'    => '<path d="M2.5 2.4h11" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/><path d="M8 5.2l5.2 8.3H2.8z" fill="currentColor"/>',
        'to-bottom' => '<path d="M2.5 13.6h11" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/><path d="M8 10.8L2.8 2.5h10.4z" fill="currentColor"/>',
        // Start room: a flag on a pole.
        'flag'   => '<path d="M3.2 14.5V1.8" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/><path d="M4.6 2.2h8.6l-2.2 3.2 2.2 3.2H4.6z" fill="currentColor"/>',
        // Suggestions: a light bulb -- the glass as an outline, the base solid.
        'bulb'   => '<path d="M5.7 10.4c0-1.7-2.4-2.5-2.4-5.1a4.7 4.7 0 0 1 9.4 0c0 2.6-2.4 3.4-2.4 5.1z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M5.6 13h4.8M6.6 15.2h2.8" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
    ];

    if (!isset($paths[$name])) {
        return '';
    }

    return '<svg class="button__glyph' . ($trailing ? ' button__glyph--trailing' : '') . '" viewBox="0 0 16 16" width="' . $size . '" height="' . $size . '"'
        . ' aria-hidden="true" focusable="false">' . $paths[$name] . '</svg>';
}

/**
 * Address of a bundled file (CSS, JavaScript), including the base path and,
 * when configured, ?v=<version> so a release invalidates the browser cache.
 */
function asset(string $file): string
{
    $url = base_path() . '/' . ltrim($file, '/');

    return asset_version() === '' ? $url : $url . '?v=' . rawurlencode(asset_version());
}

/**
 * Check a return address: only the application's own addresses below the
 * base path are accepted, otherwise null. '//host' and '/\\host' would be
 * read as protocol-relative by browsers and are rejected as well.
 */
function safe_target(?string $candidate): ?string
{
    $candidate = (string) $candidate;
    $base      = base_path() . '/';

    if ($candidate !== ''
        && str_starts_with($candidate, $base)
        && !str_starts_with($candidate, $base . '/')
        && !str_starts_with($candidate, $base . '\\')
        && !str_contains($candidate, "\n")
        && !str_contains($candidate, "\r")) {
        return $candidate;
    }

    return null;
}

/** Target address after a POST action (post/redirect/get). */
function back(?string $fallback = null): string
{
    return safe_target($_POST['back'] ?? null) ?? $fallback ?? url(['p' => 'songs']);
}

/**
 * Carry input and errors across the redirect. After post/redirect/get the
 * form is rebuilt and should show both again instead of making the user type
 * everything once more.
 *
 * @param array<string,string> $values
 * @param array<string,string> $errors
 */
function remember_input(array $values, array $errors): void
{
    $_SESSION['input'] = ['values' => $values, 'errors' => $errors];
}

/** @return array{values:array<string,string>,errors:array<string,string>}|null */
function remembered_input(): ?array
{
    $kept = $_SESSION['input'] ?? null;
    unset($_SESSION['input']);

    return is_array($kept) ? $kept : null;
}

/** Does the caller expect JSON (drag & drop via fetch)? */
function wants_json(): bool
{
    return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch'
        || str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
}

/** @param array<string,mixed> $payload */
function send_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

function redirect(string $target, int $status = 303): never
{
    header('Location: ' . $target, true, $status);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/** @return array{type:string,message:string}|null */
function flash_take(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);

    return is_array($flash) ? $flash : null;
}

function require_login(\Songwunsch\Security $security): void
{
    if (!$security->isLoggedIn()) {
        if (wants_json()) {
            send_json(['ok' => false, 'error' => t('Please log in first.')], 401);
        }
        flash('info', t('Please log in first.'));
        redirect(url(['p' => 'login']));
    }
}

/**
 * Login plus role for an area ('wishes', 'songs', 'suggestions', 'rooms',
 * 'users'). Without the role the user is sent back to the song list with a
 * notice.
 */
function require_role(\Songwunsch\Security $security, string $area): void
{
    require_login($security);

    if (!$security->can($area)) {
        if (wants_json()) {
            send_json(['ok' => false, 'error' => t('You do not have permission for that.')], 403);
        }
        flash('error', t('You do not have permission for that.'));
        redirect(url(['p' => 'songs']));
    }
}

/**
 * What this user may delete -- the kinds whose confirmation they can switch
 * off under Settings: songs, suggestions and rooms for editors, wishes for
 * moderators.
 *
 * @return list<string> subset of Settings::CONFIRM_DELETE
 */
function deletable_kinds(\Songwunsch\Security $security): array
{
    return array_values(array_filter(
        \Songwunsch\Settings::CONFIRM_DELETE,
        static fn (string $what): bool => $security->can($what),
    ));
}

/** Start page after logging in: the wish list for moderators, otherwise the song list. */
function home_for(\Songwunsch\Security $security): string
{
    return url(['p' => $security->can('wishes') ? 'wishes' : 'songs']);
}

/**
 * Error text for display. Details (table and column names) only for
 * logged-in users or while show_errors is on.
 *
 * @param array<string,mixed> $config
 */
function error_detail(\Throwable $e, array $config, \Songwunsch\Security $security): string
{
    if (($config['show_errors'] ?? false) === true || $security->isLoggedIn()) {
        return $e->getMessage();
    }

    error_log('[songwunsch] ' . $e::class . ': ' . $e->getMessage());

    return t('The repertoire is not available right now. Please try again later.');
}

/** Emergency exit when not even the configuration can be loaded. */
function render_fatal(string $title, string $html): never
{
    render_bare(500, $title, $html);
}

/**
 * 404 for an address below the base path that is not the front controller.
 * The web server routes everything to index.php (.htaccess); this is the
 * answer for the rest.
 */
function not_found(): never
{
    $link = '<a href="' . htmlspecialchars(url(), ENT_QUOTES, 'UTF-8') . '">'
        . htmlspecialchars(t('To the repertoire'), ENT_QUOTES, 'UTF-8') . '</a>';

    render_bare(404, t('Page not found'), htmlspecialchars(t('There is nothing at this address.'), ENT_QUOTES, 'UTF-8') . ' ' . $link);
}

/** Minimal page without layout, database or session -- for the cases above. */
function render_bare(int $status, string $title, string $html): never
{
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>'
        . '<link rel="stylesheet" href="' . htmlspecialchars(asset('assets/style.css'), ENT_QUOTES, 'UTF-8') . '"></head>'
        . '<body class="is-fatal"><main class="fatal"><h1>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>'
        . '<p>' . $html . '</p></main></body></html>';
    exit;
}
