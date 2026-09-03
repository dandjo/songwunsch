<?php

declare(strict_types=1);

/**
 * Creates the bcrypt hash for the first admin's password.
 *
 * Usage:  php tools/hash.php 'MyPassword'
 * Put the result into config.php under auth.hash.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Command line only.\n");
}

$password = $argv[1] ?? null;

if (!is_string($password) || $password === '') {
    fwrite(STDERR, "Usage: php tools/hash.php 'MyPassword'\n");
    exit(1);
}

if (mb_strlen($password) < 10) {
    fwrite(STDERR, "Note: use at least 10 characters.\n");
}

echo password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]), PHP_EOL;
