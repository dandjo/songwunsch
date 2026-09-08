<?php

declare(strict_types=1);

namespace Songwunsch;

use RuntimeException;

/**
 * The application's fixed database schema.
 *
 * Table and column names are a prerequisite and live in one place:
 * `songs`, `song_wishes`, `song_suggestions`, `settings`, `wish_throttle`,
 * `users`, `rooms`, `room_songs`, `uploads`, `pages`, `page_translations`.
 * ensure() runs on every request before the first data access:
 * a missing table is created -- whether the application runs in the Docker
 * stack, on a shared host or locally. Existing tables are checked for the
 * expected columns; a missing column fails with a clear message instead of
 * an SQL error in the middle of operation. It is one query per request,
 * memoised, and no query at all for a request that touches no data.
 *
 * Creating a table needs CREATE rights for the account PHP runs as, which is
 * convenient on a shared host and more than a running site should hold.
 * `$mayCreate` (config.php `schema_ddl`) says whether this instance is
 * allowed to: with it off, a missing table is reported instead of created,
 * and the database account needs no DDL rights at all. Run
 * tools/install.php once with an account that has them, or load
 * sql/schema.sql, which carries the same statements.
 */
final class Schema
{
    public const SONGS    = 'songs';
    public const WISHES   = 'song_wishes';
    public const SUGGESTIONS = 'song_suggestions';
    public const SETTINGS = 'settings';
    public const THROTTLE = 'wish_throttle';
    public const USERS    = 'users';
    public const ROOMS    = 'rooms';
    public const ROOM_SONGS = 'room_songs';
    public const UPLOADS  = 'uploads';
    public const PAGES    = 'pages';
    public const PAGE_TRANSLATIONS = 'page_translations';

    /** @var array<string,array<int,string>> table => required columns */
    private const COLUMNS = [
        self::SONGS    => ['id', 'artist', 'title', 'length_sec', 'genre'],
        self::WISHES   => ['id', 'song_id', 'artist', 'title', 'length_sec', 'genre', 'wisher', 'created_at', 'position', 'room_id', 'wished'],
        self::SUGGESTIONS => ['id', 'artist', 'title', 'suggester', 'created_at', 'room_id'],
        self::SETTINGS => ['name', 'value', 'updated_at'],
        self::THROTTLE => ['id', 'sender', 'created_at'],
        self::USERS    => ['id', 'username', 'password_hash', 'role_admin', 'role_moderator', 'role_editor', 'active', 'created_at', 'updated_at'],
        self::ROOMS    => ['id', 'slug', 'name', 'active', 'listed', 'created_at', 'updated_at'],
        self::ROOM_SONGS => ['room_id', 'song_id'],
        self::UPLOADS  => ['id', 'kind', 'mime', 'data', 'width', 'height', 'created_at'],
        self::PAGES    => ['id', 'slug', 'footer_position', 'created_at', 'updated_at'],
        self::PAGE_TRANSLATIONS => ['page_id', 'lang', 'title', 'body', 'updated_at'],
    ];

    /** @var array<string,string> table => CREATE TABLE */
    private const DDL = [
        self::SONGS => <<<'SQL'
            CREATE TABLE IF NOT EXISTS `songs` (
                `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `artist`     VARCHAR(255) NOT NULL,
                `title`      VARCHAR(255) NOT NULL,
                `length_sec` INT UNSIGNED NULL COMMENT 'length in seconds',
                `genre`      VARCHAR(128) NULL,
                PRIMARY KEY (`id`),
                KEY `idx_artist` (`artist`),
                KEY `idx_title` (`title`),
                KEY `idx_genre` (`genre`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL,
        self::WISHES => <<<'SQL'
            CREATE TABLE IF NOT EXISTS `song_wishes` (
                `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `song_id`    INT UNSIGNED NULL COMMENT 'songs.id at the time of the wish, no foreign key',
                `artist`     VARCHAR(255) NOT NULL,
                `title`      VARCHAR(255) NOT NULL,
                `length_sec` INT UNSIGNED NULL,
                `genre`      VARCHAR(128) NULL,
                `wisher`     VARCHAR(64)  NULL COMMENT 'name the guest gave for the wish list, optional',
                `created_at` DATETIME     NOT NULL,
                `position`   INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'manual order (drag & drop)',
                `room_id`    INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'rooms.id, 0 = default room',
                `wished`     INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'how often the song was wished while this entry has been open',
                PRIMARY KEY (`id`),
                -- One entry per song and room, held here and not only by the
                -- code that checks first and writes second: two wishes
                -- arriving together would otherwise make two rows of the same
                -- song. song_id is nullable and no foreign key -- a wish
                -- carries its own artist and title and outlives the song it
                -- names -- and MySQL allows any number of NULLs in a unique
                -- index, so a row without one is unaffected.
                UNIQUE KEY `uniq_room_song` (`room_id`, `song_id`),
                KEY `idx_created_at` (`created_at`),
                KEY `idx_position` (`position`),
                KEY `idx_room_id` (`room_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL,
        self::SUGGESTIONS => <<<'SQL'
            CREATE TABLE IF NOT EXISTS `song_suggestions` (
                `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `artist`     VARCHAR(255) NOT NULL,
                `title`      VARCHAR(255) NOT NULL,
                `suggester`  VARCHAR(64)  NULL COMMENT 'name the guest gave, optional',
                `created_at` DATETIME     NOT NULL,
                `room_id`    INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'rooms.id whose list the suggestion is on, 0 = main room; the adopted song joins that room',
                PRIMARY KEY (`id`),
                -- One suggestion per song and room, for the same reason the
                -- wishes have one: the check-then-insert in the controller
                -- cannot see a request that is being served next to it. The
                -- key covers the lookup by artist and title as well.
                UNIQUE KEY `uniq_room_artist_title` (`room_id`, `artist`, `title`),
                KEY `idx_created_at` (`created_at`),
                KEY `idx_room_id` (`room_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL,
        self::SETTINGS => <<<'SQL'
            CREATE TABLE IF NOT EXISTS `settings` (
                `name`       VARCHAR(64) NOT NULL,
                `value`      TEXT        NOT NULL,
                `updated_at` DATETIME    NOT NULL,
                PRIMARY KEY (`name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL,
        self::THROTTLE => <<<'SQL'
            CREATE TABLE IF NOT EXISTS `wish_throttle` (
                `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `sender`     CHAR(64)     NOT NULL COMMENT 'HMAC of the IP with the daily secret, never a plain IP',
                `created_at` DATETIME     NOT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_sender_created` (`sender`, `created_at`),
                KEY `idx_created_at` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL,
        self::USERS => <<<'SQL'
            CREATE TABLE IF NOT EXISTS `users` (
                `id`             INT UNSIGNED     NOT NULL AUTO_INCREMENT,
                `username`       VARCHAR(64)      NOT NULL,
                `password_hash`  VARCHAR(255)     NOT NULL,
                `role_admin`     TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'admin: manages users and may do everything',
                `role_moderator` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'moderator: may edit the wish list',
                `role_editor`    TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'editor: may maintain the song list',
                `active`         TINYINT UNSIGNED NOT NULL DEFAULT 1,
                `created_at`     DATETIME         NOT NULL,
                `updated_at`     DATETIME         NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_username` (`username`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL,
        self::ROOMS => <<<'SQL'
            CREATE TABLE IF NOT EXISTS `rooms` (
                `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `slug`       VARCHAR(64)  NOT NULL COMMENT 'machine name in the address: /rooms/<slug>',
                `name`       VARCHAR(128) NOT NULL COMMENT 'display name',
                `active`     TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '0 = archived: out of the switcher and the list, reachable to signed-in users only',
                `listed`     TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '1 = guests see the room in the switcher and the list of rooms; 0 = reached through its address only',
                `created_at` DATETIME     NOT NULL,
                `updated_at` DATETIME     NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_slug` (`slug`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL,
        self::ROOM_SONGS => <<<'SQL'
            CREATE TABLE IF NOT EXISTS `room_songs` (
                `room_id` INT UNSIGNED NOT NULL,
                `song_id` INT UNSIGNED NOT NULL COMMENT 'songs.id -- the room picks from the main list',
                PRIMARY KEY (`room_id`, `song_id`),
                KEY `idx_song_id` (`song_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL,
        self::UPLOADS => <<<'SQL'
            CREATE TABLE IF NOT EXISTS `uploads` (
                `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `kind`       VARCHAR(32)  NOT NULL COMMENT 'what the file is for: logo',
                `mime`       VARCHAR(64)  NOT NULL,
                `data`       MEDIUMBLOB   NOT NULL,
                `width`      INT UNSIGNED NULL COMMENT 'pixels, NULL for SVG',
                `height`     INT UNSIGNED NULL,
                `created_at` DATETIME     NOT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_kind` (`kind`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL,
        self::PAGES => <<<'SQL'
            CREATE TABLE IF NOT EXISTS `pages` (
                `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `slug`       VARCHAR(64)  NOT NULL COMMENT 'machine name in the address: /pages/<slug>; title and body per language in page_translations',
                `footer_position` INT UNSIGNED NULL COMMENT 'place among the footer links; NULL = not linked in the footer',
                `created_at` DATETIME     NOT NULL,
                `updated_at` DATETIME     NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_slug` (`slug`),
                KEY `idx_footer_position` (`footer_position`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL,
        self::PAGE_TRANSLATIONS => <<<'SQL'
            CREATE TABLE IF NOT EXISTS `page_translations` (
                `page_id`    INT UNSIGNED NOT NULL COMMENT 'pages.id, no foreign key',
                `lang`       VARCHAR(16)  NOT NULL COMMENT 'language code as in lang/<code>.po: en, de, pt-br',
                `title`      VARCHAR(128) NOT NULL COMMENT 'heading of the page and text of the footer link, in this language',
                `body`       MEDIUMTEXT   NOT NULL COMMENT 'the content as HTML in this language, cleaned on save (src/Html.php)',
                `updated_at` DATETIME     NOT NULL,
                PRIMARY KEY (`page_id`, `lang`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL,
    ];

    /** The answer of ensure(), kept for the rest of the request; null = not asked yet. */
    private ?array $ensured = null;

    /**
     * @param bool $mayCreate whether this instance may create a missing table.
     *                        The tools pass true; a request passes what
     *                        config.php `schema_ddl` says.
     */
    public function __construct(
        private readonly Database $db,
        private readonly bool $mayCreate = true,
    ) {
    }

    /**
     * Create missing tables, check existing tables for their columns.
     * A single query against the INFORMATION_SCHEMA answers every question:
     * which tables exist and which columns they have. The answer is kept for
     * the rest of the request, so the four places that call this before they
     * touch the database -- RoomListener when it looks a room up and when it
     * reads the start room, LiveUpdateListener before the poll tokens, and
     * the kernel before the controller -- cost one query between them, and
     * nothing drops a table in between. Each of them is its own guard on
     * purpose: a request that reaches none of them (an unknown address, an
     * address a visitor may not open) asks nothing at all.
     *
     * @return array<int,string> names of the tables that were created
     */
    public function ensure(): array
    {
        if ($this->ensured !== null) {
            return $this->ensured;
        }

        $tables = array_keys(self::COLUMNS);
        $rows   = $this->db->all(
            'SELECT TABLE_NAME, COLUMN_NAME
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME IN (' . implode(', ', array_fill(0, count($tables), '?')) . ')',
            [$this->db->schemaName(), ...$tables],
        );

        /** @var array<string,array<int,string>> $present table => existing columns (lower case) */
        $present = [];
        foreach ($rows as $row) {
            $present[(string) $row['TABLE_NAME']][] = strtolower((string) $row['COLUMN_NAME']);
        }

        $created = [];

        foreach (self::COLUMNS as $table => $required) {
            if (!isset($present[$table])) {
                if (!$this->mayCreate) {
                    throw new RuntimeException(t(
                        'Table "{table}" is missing. Run tools/install.php or load sql/schema.sql.',
                        ['table' => $table],
                    ));
                }
                $this->db->pdo()->exec(self::DDL[$table]);
                $created[] = $table;
                continue;
            }

            $missing = array_diff($required, $present[$table]);
            if ($missing !== []) {
                throw new RuntimeException(t(
                    'Table "{table}" does not have the expected structure, the columns {columns} are missing. '
                    . 'Rename it or recreate it from sql/schema.sql.',
                    ['table' => $table, 'columns' => implode(', ', $missing)],
                ));
            }
        }

        return $this->ensured = $created;
    }
}
