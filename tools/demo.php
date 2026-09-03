<?php

declare(strict_types=1);

/**
 * Import the demo repertoire (sql/demo.sql, 50 songs) -- without a MySQL
 * client, over the application's own database connection:
 *
 *   php tools/demo.php            # only into an empty `songs` table
 *   php tools/demo.php --force    # add the songs even if the table has rows
 *   docker compose exec web php tools/demo.php
 *
 * Reads config.php (or the environment variables) like the application does
 * and creates missing tables first. Exit code 0 when the songs are in.
 */

use Songwunsch\Database;
use Songwunsch\Schema;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/src/bootstrap.php';

$force = in_array('--force', array_slice($argv, 1), true);
foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--force') {
        fwrite(STDERR, "Usage: php tools/demo.php [--force]\n");
        exit(2);
    }
}

$configFile = dirname(__DIR__) . '/config.php';
if (!is_file($configFile)) {
    fwrite(STDERR, "config.php is missing -- copy config.example.php and adjust it.\n");
    exit(1);
}

$sqlFile = dirname(__DIR__) . '/sql/demo.sql';
$sql     = is_file($sqlFile) ? file_get_contents($sqlFile) : false;
if ($sql === false) {
    fwrite(STDERR, "sql/demo.sql not found.\n");
    exit(1);
}

/** @var array<string,mixed> $config */
$config = require $configFile;

try {
    $db = new Database($config['db']);
    (new Schema($db))->ensure();

    $existing = (int) ($db->one('SELECT COUNT(*) AS n FROM `songs`')['n'] ?? 0);
    if ($existing > 0 && !$force) {
        fwrite(STDERR, "The songs table already has $existing rows -- nothing imported.\n");
        fwrite(STDERR, "Use --force to add the demo songs anyway.\n");
        exit(1);
    }

    // One statement per line ending in ';'. Comment lines are dropped first;
    // the values themselves contain no semicolons.
    $body       = preg_replace('/^\s*--.*$/m', '', $sql) ?? '';
    $statements = array_filter(array_map('trim', preg_split('/;\s*$/m', $body) ?: []));

    $inserted = 0;
    $pdo      = $db->pdo();
    $pdo->beginTransaction();
    foreach ($statements as $statement) {
        $affected = $pdo->exec($statement);
        if (str_starts_with(strtoupper($statement), 'INSERT')) {
            $inserted += (int) $affected;
        }
    }
    $pdo->commit();
} catch (Throwable $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    exit(1);
}

echo "Imported $inserted demo songs" . ($existing > 0 ? " (added to $existing existing)" : '') . ".\n";
