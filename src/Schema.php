<?php

declare(strict_types=1);

namespace Songwunsch;

use RuntimeException;

/**
 * The application's fixed database schema.
 *
 * Table and column names are a prerequisite and live in one place:
 * `songs`, `song_wishes`, `song_suggestions`, `settings`, `wish_throttle`,
 * `users`, `rooms`, `room_songs`, `uploads`. ensure() runs on every request before the
 * first data access:
 * a missing table is created -- whether the application runs in the Docker
 * stack, on a shared host or locally. Existing tables are checked for the
 * expected columns; a column that a later version added (ADDITIONS) is added
 * on the spot, anything else missing fails with a clear message instead of
 * an SQL error.
 *
 * sql/schema.sql contains the same statements for installations where the
 * web user is not allowed to CREATE TABLE.
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

    /** @var array<string,array<int,string>> table => required columns */
    private const COLUMNS = [
        self::SONGS    => ['id', 'artist', 'title', 'length_sec', 'genre'],
        self::WISHES   => ['id', 'song_id', 'artist', 'title', 'length_sec', 'genre', 'wisher', 'created_at', 'position', 'room_id'],
        self::SUGGESTIONS => ['id', 'artist', 'title', 'suggester', 'created_at', 'room_id'],
        self::SETTINGS => ['name', 'value', 'updated_at'],
        self::THROTTLE => ['id', 'sender', 'created_at'],
        self::USERS    => ['id', 'username', 'password_hash', 'role_admin', 'role_moderator', 'role_editor', 'active', 'created_at', 'updated_at'],
        self::ROOMS    => ['id', 'slug', 'name', 'active', 'created_at', 'updated_at'],
        self::ROOM_SONGS => ['room_id', 'song_id'],
        self::UPLOADS  => ['id', 'kind', 'mime', 'data', 'width', 'height', 'created_at'],
    ];

    /**
     * Columns added by later versions: table => column => statements, run in
     * order when the table exists without the column, so an installation
     * upgrades itself on the first request (needs the ALTER privilege once).
     * Usually a single ALTER TABLE; a column that replaces an older one also
     * carries its data over and drops the old column.
     *
     * @var array<string,array<string,list<string>>>
     */
    private const ADDITIONS = [
        self::ROOMS => [
            'active' => [
                'ALTER TABLE `rooms` ADD COLUMN `active` TINYINT UNSIGNED NOT NULL DEFAULT 1 '
                . "COMMENT '0 = archived: hidden from the switcher and from guests, still reachable' AFTER `name`",
            ],
        ],
        self::WISHES => [
            'room_id' => [
                'ALTER TABLE `song_wishes` ADD COLUMN `room_id` INT UNSIGNED NOT NULL DEFAULT 0 '
                . "COMMENT 'rooms.id, 0 = default room' AFTER `position`, ADD KEY `idx_room_id` (`room_id`)",
            ],
            'wisher'  => [
                'ALTER TABLE `song_wishes` ADD COLUMN `wisher` VARCHAR(64) NULL '
                . "COMMENT 'name the guest gave for the wish list, optional' AFTER `genre`",
            ],
        ],
        self::SUGGESTIONS => [
            'room_id' => [
                'ALTER TABLE `song_suggestions` ADD COLUMN `room_id` INT UNSIGNED NOT NULL DEFAULT 0 '
                . "COMMENT 'rooms.id the suggestion was made in, 0 = main room; the adopted song joins that room' AFTER `created_at`",
            ],
        ],
        self::USERS => [
            // Admin used to be a single account: is_admin was 1 or NULL under a
            // unique index. Now it is a role like the others, so the flag
            // becomes role_admin, the one admin keeps the role (with the two
            // roles it includes) and the old column goes (its unique index
            // with it).
            'role_admin' => [
                'ALTER TABLE `users` ADD COLUMN `role_admin` TINYINT UNSIGNED NOT NULL DEFAULT 0 '
                . "COMMENT 'admin: manages users and may do everything' AFTER `password_hash`",
                'UPDATE `users` SET `role_admin` = 1, `role_moderator` = 1, `role_editor` = 1 WHERE `is_admin` = 1',
                'ALTER TABLE `users` DROP COLUMN `is_admin`',
            ],
        ],
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
                PRIMARY KEY (`id`),
                KEY `idx_created_at` (`created_at`),
                KEY `idx_song_id` (`song_id`),
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
                `room_id`    INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'rooms.id the suggestion was made in, 0 = main room; the adopted song joins that room',
                PRIMARY KEY (`id`),
                KEY `idx_created_at` (`created_at`),
                KEY `idx_artist_title` (`artist`, `title`)
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
                `active`     TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '0 = archived: hidden from the switcher and from guests, still reachable',
                `created_at` DATETIME     NOT NULL,
                `updated_at` DATETIME     NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_slug` (`slug`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL,
        self::ROOM_SONGS => <<<'SQL'
            CREATE TABLE IF NOT EXISTS `room_songs` (
                `room_id` INT UNSIGNED NOT NULL,
                `song_id` INT UNSIGNED NOT NULL COMMENT 'songs.id -- the room picks from the master list',
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
    ];

    public function __construct(private readonly Database $db)
    {
    }

    /**
     * Create missing tables, add known new columns, check existing tables
     * for their columns.
     *
     * A single query against the INFORMATION_SCHEMA answers every question:
     * which tables exist and which columns they have.
     *
     * @return array<int,string> names of the tables that were created
     */
    public function ensure(): array
    {
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
                $this->db->pdo()->exec(self::DDL[$table]);
                $created[] = $table;
                continue;
            }

            $missing = array_diff($required, $present[$table]);
            foreach ($missing as $i => $column) {
                if (isset(self::ADDITIONS[$table][$column])) {
                    foreach (self::ADDITIONS[$table][$column] as $statement) {
                        $this->db->pdo()->exec($statement);
                    }
                    unset($missing[$i]);
                }
            }
            if ($missing !== []) {
                throw new RuntimeException(t(
                    'Table "{table}" does not have the expected structure, the columns {columns} are missing. '
                    . 'If it comes from an older version, rename it or recreate it from sql/schema.sql.',
                    ['table' => $table, 'columns' => implode(', ', $missing)],
                ));
            }
        }

        return $created;
    }
}
