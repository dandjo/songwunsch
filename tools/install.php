<?php

declare(strict_types=1);

/**
 * Create the tables up front and set up the first admin -- the same
 * routines the application runs itself, just without a web server -- and
 * add the indexes the table definitions declare but the live tables lack
 * (an index added in a later version; the application itself never alters
 * an existing table):
 *
 *   php tools/install.php
 *   docker compose exec web php tools/install.php
 *
 * Reads config.php (or the environment variables) like the application does.
 * Exit code 0 when every table exists or has been created and every index
 * is in place. Safe to run again at any time.
 */

use Songwunsch\Database;
use Songwunsch\Schema;
use Songwunsch\UserRepository;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/src/bootstrap.php';

$configFile = dirname(__DIR__) . '/config.php';
if (!is_file($configFile)) {
    fwrite(STDERR, "config.php is missing -- copy config.example.php and adjust it.\n");
    exit(1);
}

/** @var array<string,mixed> $config */
$config = require $configFile;

try {
    $db      = new Database($config['db']);
    $schema  = new Schema($db);
    $created = $schema->ensure();
    $indexed = $schema->addIndexes();
    $seeded  = (new UserRepository($db))->ensureAdmin($config['auth']);
} catch (Throwable $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    exit(1);
}

echo $created === []
    ? "All tables present.\n"
    : 'Created: ' . implode(', ', $created) . "\n";
echo $indexed === []
    ? "All indexes present.\n"
    : 'Indexes added: ' . implode(', ', $indexed) . "\n";
echo $seeded
    ? 'First admin "' . $config['auth']['user'] . "\" created from config.php.\n"
    : "Users present, no first admin needed.\n";
