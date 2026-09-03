<?php

declare(strict_types=1);

/**
 * Import songs from a CSV file into the `songs` table.
 *
 *   php tools/import-csv.php songs.csv              # add the songs
 *   php tools/import-csv.php --replace songs.csv    # delete every song first
 *   php tools/import-csv.php --dry-run songs.csv    # parse and report, write nothing
 *   php tools/import-csv.php --skip='KEIN SONG!' -  # read the CSV from stdin, skip that title
 *   docker compose exec -T web php tools/import-csv.php --replace - < songs.csv
 *
 * The file needs a header row; the columns are found by name, so their order
 * does not matter. Recognised names (case-insensitive, first match wins):
 *   title   -- Songtitel, Titel, Title, Song
 *   artist  -- Künstler, Interpret, Artist
 *   genre   -- Attribute, Genre, Tags     (optional)
 *   length  -- Länge, Length, Dauer        (optional; m:ss or seconds)
 * Anything else is ignored. UTF-8 with or without BOM, comma or semicolon
 * separated (detected from the header).
 *
 * Genre: several values separated by ';' or ',' are kept, joined with ', '.
 * A few spellings from the streamersonglist export are tidied on the way:
 *   PopSong -> Pop, Rock Song -> Rock, RocknRoll -> Rock 'n' Roll, X-Mas -> Weihnachten.
 * Values are trimmed, validated like the song form (artist and title
 * required, length limits) and inserted in one transaction. Rows already
 * present with the same artist and title are skipped.
 *
 * --replace empties `songs` and `room_songs` before the import: the rooms
 * lose their selection, wishes keep their copies and stay readable.
 * --skip=<title> leaves rows with exactly that title out (placeholder rows).
 */

use Songwunsch\Database;
use Songwunsch\Schema;
use Songwunsch\SongRepository;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/src/bootstrap.php';

$replace = false;
$dryRun  = false;
$skip    = [];
$file    = null;

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--replace') {
        $replace = true;
    } elseif ($arg === '--dry-run' || $arg === '-n') {
        $dryRun = true;
    } elseif (str_starts_with($arg, '--skip=')) {
        $skip[] = trim(substr($arg, 7));
    } elseif ($file === null && ($arg === '-' || !str_starts_with($arg, '-'))) {
        $file = $arg;
    } else {
        fwrite(STDERR, "Usage: php tools/import-csv.php [--replace] [--dry-run] [--skip=<title>] <file.csv|->\n");
        exit(2);
    }
}

if ($file === null) {
    fwrite(STDERR, "Usage: php tools/import-csv.php [--replace] [--dry-run] [--skip=<title>] <file.csv|->\n");
    exit(2);
}

$handle = $file === '-' ? fopen('php://stdin', 'r') : @fopen($file, 'r');
if ($handle === false) {
    fwrite(STDERR, "Cannot open $file.\n");
    exit(1);
}

// --- Header: separator and column positions -----------------------------------
$headerLine = fgets($handle);
if ($headerLine === false) {
    fwrite(STDERR, "The file is empty.\n");
    exit(1);
}
$headerLine = preg_replace('/^\xEF\xBB\xBF/', '', $headerLine) ?? $headerLine; // BOM
$separator  = substr_count($headerLine, ';') > substr_count($headerLine, ',') ? ';' : ',';
$header     = array_map(static fn ($h): string => mb_strtolower(trim((string) $h)), str_getcsv($headerLine, $separator, '"', ''));

$aliases = [
    'title'  => ['songtitel', 'titel', 'title', 'song'],
    'artist' => ['künstler', 'kuenstler', 'interpret', 'artist'],
    'genre'  => ['attribute', 'genre', 'tags'],
    'length' => ['länge', 'laenge', 'length', 'dauer'],
];
$columns = [];
foreach ($aliases as $field => $names) {
    foreach ($names as $name) {
        $at = array_search($name, $header, true);
        if ($at !== false) {
            $columns[$field] = $at;
            break;
        }
    }
}
if (!isset($columns['title'], $columns['artist'])) {
    fwrite(STDERR, 'Header must name a title and an artist column; found: ' . implode(', ', $header) . "\n");
    exit(1);
}

// --- Rows ----------------------------------------------------------------------
$genreFixes = [
    'popsong'   => 'Pop',
    'rock song' => 'Rock',
    'rocksong'  => 'Rock',
    'rocknroll' => "Rock 'n' Roll",
    'x-mas'     => 'Weihnachten',
];
$tidyGenre = static function (string $raw) use ($genreFixes): string {
    $parts = preg_split('/\s*[;,]\s*/u', trim($raw), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $out   = [];
    foreach ($parts as $part) {
        $part = $genreFixes[mb_strtolower($part)] ?? $part;
        if (!in_array($part, $out, true)) {
            $out[] = $part;
        }
    }

    return implode(', ', $out);
};

$repo    = new SongRepository(new Database([])); // validate() never touches the connection, which is opened lazily
$songs   = [];
$errors  = [];
$skipped = 0;
$line    = 1;

while (($row = fgetcsv($handle, 0, $separator, '"', '')) !== false) {
    $line++;
    if ($row === [null] || implode('', array_map('strval', $row)) === '') {
        continue; // blank line
    }
    $get   = static fn (string $field): string => isset($columns[$field]) ? trim((string) ($row[$columns[$field]] ?? '')) : '';
    $title = $get('title');
    if (in_array($title, $skip, true)) {
        $skipped++;
        continue;
    }

    $checked = $repo->validate([
        'artist' => $get('artist'),
        'title'  => $title,
        'genre'  => $tidyGenre($get('genre')),
        'length' => $get('length'),
    ]);
    if ($checked['errors'] !== []) {
        $errors[] = "line $line: " . implode(' ', $checked['errors']);
        continue;
    }
    $songs[] = $checked['values'];
}
fclose($handle);

if ($errors !== []) {
    fwrite(STDERR, count($errors) . " row(s) rejected:\n  " . implode("\n  ", $errors) . "\n");
    exit(1);
}

printf("%d song(s) parsed%s.\n", count($songs), $skipped > 0 ? ", $skipped skipped by title" : '');
if ($dryRun) {
    foreach (array_slice($songs, 0, 5) as $song) {
        printf("  %s – %s [%s]\n", $song['artist'], $song['title'], $song['genre'] ?? '');
    }
    echo "Dry run: nothing written.\n";
    exit(0);
}

// --- Database --------------------------------------------------------------------
$configFile = dirname(__DIR__) . '/config.php';
if (!is_file($configFile)) {
    fwrite(STDERR, "config.php is missing -- copy config.example.php and adjust it.\n");
    exit(1);
}
/** @var array<string,mixed> $config */
$config = require $configFile;

try {
    $db = new Database($config['db']);
    (new Schema($db))->ensure();
    $pdo = $db->pdo();

    $pdo->beginTransaction();
    $removed = 0;
    if ($replace) {
        $removed = $db->exec('DELETE FROM `' . Schema::SONGS . '`');
        $db->exec('DELETE FROM `' . Schema::ROOM_SONGS . '`');
    }

    $exists = $pdo->prepare('SELECT id FROM `' . Schema::SONGS . '` WHERE artist = ? AND title = ? LIMIT 1');
    $insert = $pdo->prepare('INSERT INTO `' . Schema::SONGS . '` (artist, title, length_sec, genre) VALUES (?, ?, ?, ?)');
    $inserted = 0;
    $present  = 0;
    foreach ($songs as $song) {
        $exists->execute([$song['artist'], $song['title']]);
        if ($exists->fetch() !== false) {
            $present++;
            continue;
        }
        $insert->execute([$song['artist'], $song['title'], $song['length_sec'], $song['genre']]);
        $inserted++;
    }
    $pdo->commit();
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    exit(1);
}

if ($replace) {
    printf("%d song(s) deleted, the rooms' selections cleared.\n", $removed);
}
printf("%d song(s) inserted%s.\n", $inserted, $present > 0 ? ", $present already present" : '');
