-- Songwunsch: the application's tables.
--
-- The application creates these tables itself on the first request
-- (src/Schema.php holds the same definition). This script is for
-- installations where the web user is not allowed to CREATE TABLE, and it is
-- imported by the Docker stack on the very first start of the database.
--
-- Wishes and suggestions hold no IP and no user agent; the only personal data
-- is the name a guest chose to give (wisher, suggester), and it goes when the
-- wish or suggestion goes.
-- wish_throttle keeps a short-lived pseudonym (see there), users only the
-- username and password hash of the staff accounts.

-- The client must speak UTF-8, otherwise umlauts end up double-encoded in
-- the database (the mysql CLI uses latin1 depending on the version).
SET NAMES utf8mb4;

-- Repertoire the audience picks from.
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Received wishes. Artist/title/length/genre are copied so the list stays
-- readable after the song changes; song_id is deliberately not a foreign
-- key, a deleted song does not take its wishes with it.
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Upgrade of a song_wishes table from before rooms existed. The application
-- runs this itself on the first request when the column is missing
-- (src/Schema.php, ADDITIONS); by hand only if the web user may not ALTER:
--   ALTER TABLE `song_wishes`
--       ADD COLUMN `room_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'rooms.id, 0 = default room' AFTER `position`,
--       ADD KEY `idx_room_id` (`room_id`);
-- Likewise for a table from before guests could give their name:
--   ALTER TABLE `song_wishes`
--       ADD COLUMN `wisher` VARCHAR(64) NULL COMMENT 'name the guest gave for the wish list, optional' AFTER `genre`;

-- Song suggestions from the audience: artist and title of a song that is
-- missing from the repertoire. The editor adopts a suggestion into `songs`
-- (adding length and genre) or deletes it; either way it leaves this table.
-- Suggestions aim at the master list; room_id remembers the room the guest
-- was in, and an adopted song is offered in that room right away. suggester
-- is the guest's name if given, and goes with the suggestion.
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Upgrade of a song_suggestions table from before the room was remembered
-- (the application does this itself, see above):
--   ALTER TABLE `song_suggestions`
--       ADD COLUMN `room_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'rooms.id the suggestion was made in, 0 = main room' AFTER `created_at`;

-- State that outlives requests: the moderator's pause switch, daily secrets
-- of the wish guard.
CREATE TABLE IF NOT EXISTS `settings` (
    `name`       VARCHAR(64) NOT NULL,
    `value`      TEXT        NOT NULL,
    `updated_at` DATETIME    NOT NULL,
    PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rate limiting of wishing. sender is an HMAC of the IP address with a
-- secret that changes daily; entries are deleted after an hour -- no plain
-- IP, no lasting attribution.
CREATE TABLE IF NOT EXISTS `wish_throttle` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `sender`     CHAR(64)     NOT NULL COMMENT 'HMAC of the IP with the daily secret, never a plain IP',
    `created_at` DATETIME     NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_sender_created` (`sender`, `created_at`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Staff accounts. Exactly one admin: is_admin is 1 or NULL, the unique index
-- allows only one 1. The roles moderator (wish list) and editor (song list)
-- can be combined. The application creates the first admin from
-- auth.user/auth.hash in config.php as soon as the table is empty.
CREATE TABLE IF NOT EXISTS `users` (
    `id`             INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `username`       VARCHAR(64)      NOT NULL,
    `password_hash`  VARCHAR(255)     NOT NULL,
    `is_admin`       TINYINT UNSIGNED NULL COMMENT '1 for the single admin, otherwise NULL -- the unique index allows only one',
    `role_moderator` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'moderator: may edit the wish list',
    `role_editor`    TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'editor: may maintain the song list',
    `active`         TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `created_at`     DATETIME         NOT NULL,
    `updated_at`     DATETIME         NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_username` (`username`),
    UNIQUE KEY `uq_admin` (`is_admin`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rooms: a capsule of song list and wish list with its own address
-- /rooms/<slug>. The default room (/ and /wishes) is virtual -- id 0, no row,
-- the whole master list -- and always there.
CREATE TABLE IF NOT EXISTS `rooms` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `slug`       VARCHAR(64)  NOT NULL COMMENT 'machine name in the address: /rooms/<slug>',
    `name`       VARCHAR(128) NOT NULL COMMENT 'display name',
    `active`     TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '0 = archived: hidden from the switcher and from guests, still reachable',
    `created_at` DATETIME     NOT NULL,
    `updated_at` DATETIME     NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Upgrade of a rooms table from before archiving existed (the application does
-- this itself, see above):
--   ALTER TABLE `rooms` ADD COLUMN `active` TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER `name`;

-- Which songs of the master list a room offers.
CREATE TABLE IF NOT EXISTS `room_songs` (
    `room_id` INT UNSIGNED NOT NULL,
    `song_id` INT UNSIGNED NOT NULL COMMENT 'songs.id -- the room picks from the master list',
    PRIMARY KEY (`room_id`, `song_id`),
    KEY `idx_song_id` (`song_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Files the admin uploads (the header logos), kept in the database so the
-- deployment and a missing writable folder cannot lose them. Which logo the
-- header shows is the setting 'logo_id' (0 or absent = the word mark).
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
