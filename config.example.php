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

    // --- Reverse proxy ----------------------------------------------------
    // Behind a reverse proxy (Traefik, nginx) the visitor's IP is in
    // X-Forwarded-For -- the basis of the per-sender wish limit (see
    // src/WishGuard.php). Only enable this when the proxy is the only way in;
    // otherwise senders could make up their address and bypass that limit.
    'trust_proxy' => $env('TRUST_PROXY', '0') === '1',

    // --- Errors -----------------------------------------------------------
    // Show technical error messages (table/column names) in the browser.
    // Helpful during setup, set to false in production -- logged-in users
    // still see the details.
    'show_errors' => $env('SHOW_ERRORS', '1') === '1',
];
