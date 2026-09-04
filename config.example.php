<?php

declare(strict_types=1);

/**
 * Copy this file to config.php and adjust it.
 * config.php contains credentials and does NOT belong in version control.
 *
 * Every value can alternatively be set through an environment variable --
 * so the same config.php runs locally, in the Docker stack and on the server.
 */

$env = static function (string $key, ?string $default = null): ?string {
    $value = getenv($key);

    return ($value === false || $value === '') ? $default : $value;
};

return [
    // --- Database ---------------------------------------------------------
    // The database must exist; the tables are created by the application on
    // the first request (src/Schema.php). The user needs CREATE TABLE once,
    // afterwards SELECT, INSERT, UPDATE and DELETE. Without the CREATE
    // privilege: import sql/schema.sql.
    'db' => [
        'host'    => $env('DB_HOST', '127.0.0.1'),
        'port'    => (int) $env('DB_PORT', '3306'),
        'name'    => $env('DB_NAME', 'songwunsch'),
        'user'    => $env('DB_USER', 'songwunsch'),
        'pass'    => $env('DB_PASSWORD', 'bitte-aendern'),
        'charset' => 'utf8mb4',
    ],

    // --- Address ----------------------------------------------------------
    // Sub-path the application is reachable under. Empty or '/' (default)
    // means: directly at the domain root, https://example.org/. '/songliste'
    // gives https://example.org/songliste/. The value applies to both modes
    // of operation -- a reverse proxy that strips the prefix (Traefik
    // stripPrefix) and a sub-folder of that name in the document root.
    'base_path' => $env('BASE_PATH', '/'),

    // --- First admin ------------------------------------------------------
    // Users are managed in the `users` table (page "Users"). These values
    // only matter while that table is empty: then the application creates
    // the first admin from them, who makes further users -- and further
    // admins -- inside the application. Afterwards the table alone counts;
    // a password changed here has no effect any more.
    // Create the hash with:  php tools/hash.php 'MyPassword'
    'auth' => [
        'user' => $env('AUTH_USER', 'Administrator'),
        // Example hash for the password "Administrator" -- replace it!
        'hash' => $env('AUTH_HASH', '$2y$12$yj8cmii9zUipXmvTfcFUR.kZlaxBEVjHAVciYdvUBmQCd0ZD6vRKm'),
    ],

    // --- Version ----------------------------------------------------------
    // Appended to style.css and app.js as ?v=... so browsers fetch the new
    // files after a deployment instead of serving cached ones. Raise it with
    // every release (or set APP_VERSION in the environment).
    'version' => $env('APP_VERSION', '1.0.0'),

    // --- Footer -----------------------------------------------------------
    // A line at the bottom of every page: credits, an imprint link. HTML is
    // printed as is (not escaped), so only put your own markup here, never
    // anything from visitors. Empty (default): no footer at all.
    // Example:  '<p>Powered by <a href="https://example.org" rel="noopener">example.org</a></p>'
    'footer' => $env('FOOTER_HTML', ''),

    // --- Colours ----------------------------------------------------------
    // One colour per area of use, as '#rrggbb'. Shades and tints (hover,
    // frames, notices) are derived from it. Leave an entry empty to keep the
    // built-in colour. Keep the contrast to the background readable.
    'theme' => [
        'accent'     => $env('THEME_ACCENT', ''),     // actions: buttons, active tab, links, "wunsch" in the word mark (default gold #e6b450)
        'secondary'  => $env('THEME_SECONDARY', ''),  // tags, counters, chips (default violet #8d7ce0)
        'danger'     => $env('THEME_DANGER', ''),     // closed rooms, delete buttons, warnings, errors (default red #ff6f85)
        'success'    => $env('THEME_SUCCESS', ''),    // confirmations (default green #4ed08c)
        'background' => $env('THEME_BACKGROUND', ''), // page ground; shell, panels, fields and lines are lightened steps of it (default #0d0e13)
        'text'       => $env('THEME_TEXT', ''),       // text; the muted text is a step towards the background (default #e9ebf1)
    ],

    // --- Behaviour --------------------------------------------------------
    'per_page'          => (int) $env('PER_PAGE', '50'), // songs per page
    'wish_cooldown_sec' => 5,     // minimum gap between two wishes per session
    'allow_duplicates'  => false, // may the same song be wished while still open?
    // Song suggestions from the audience: open suggestions in total (0 = no
    // limit) and the minimum gap between two suggestions per session.
    'suggestion_max_open'     => 200,
    'suggestion_cooldown_sec' => 10,

    // --- Wish protection (see src/WishGuard.php) --------------------------
    // Limits; 0 disables the respective limit.
    'wish_limits' => [
        'max_open'          => 200, // open wishes in total
        'per_minute_total'  => 30,  // wishes per minute across all visitors
        'per_minute_sender' => 3,   // wishes per minute per sender
        'per_hour_sender'   => 20,  // wishes per hour per sender
    ],
    // Minimum time between page load and submit in seconds -- faster
    // submissions practically always come from scripts.
    'wish_min_form_sec' => 2,
    // When the application runs behind a reverse proxy (Traefik, nginx) the
    // visitor's IP is in X-Forwarded-For. Only enable this when the proxy is
    // the only way in -- otherwise senders could make up their address and
    // bypass the per-sender limit.
    'trust_proxy'       => $env('TRUST_PROXY', '0') === '1',

    // Show technical error messages (table/column names) in the browser.
    // Helpful during setup, set to false in production -- logged-in users
    // still see the details.
    'show_errors'       => $env('SHOW_ERRORS', '1') === '1',
];
