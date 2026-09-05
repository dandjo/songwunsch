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

-- Staff accounts. Three roles: admin (manages users, may do everything),
-- moderator (wish list) and editor (song list). Admin includes the other two
-- and is stored together with them; moderator and editor combine freely.
-- Any number of admins; the application only makes sure the last active
-- admin cannot give the role up. It creates the first admin from
-- auth.user/auth.hash in config.php as soon as the table is empty.
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rooms: a capsule of song list and wish list with its own address
-- /rooms/<slug>. The default room (/ and /wishes) is virtual -- id 0, no row,
-- the whole master list -- and always there.
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

-- Pages the admins write -- an imprint, FAQs, a privacy notice. Every page is
-- public under /page/<slug> and may link to any other; the ones with a
-- footer_position are linked at the bottom of every screen, in that order.
-- The body is HTML from the editor, reduced to an allowed set of tags and
-- attributes on save.
CREATE TABLE IF NOT EXISTS `pages` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `slug`       VARCHAR(64)  NOT NULL COMMENT 'machine name in the address: /pages/<slug>; title and body per language in page_translations',
    `footer_position` INT UNSIGNED NULL COMMENT 'place among the footer links; NULL = not linked in the footer',
    `created_at` DATETIME     NOT NULL,
    `updated_at` DATETIME     NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_slug` (`slug`),
    KEY `idx_footer_position` (`footer_position`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A page's title and body, one row per language of the language menu. A page
-- has at least one; a visitor gets the row in the chosen language, otherwise
-- the first language of the fallback order (setting pages_languages, the
-- admins arrange it under Pages) the page has.
CREATE TABLE IF NOT EXISTS `page_translations` (
    `page_id`    INT UNSIGNED NOT NULL COMMENT 'pages.id, no foreign key',
    `lang`       VARCHAR(16)  NOT NULL COMMENT 'language code as in lang/<code>.po: en, de, pt-br',
    `title`      VARCHAR(128) NOT NULL COMMENT 'heading of the page and text of the footer link, in this language',
    `body`       MEDIUMTEXT   NOT NULL COMMENT 'the content as HTML in this language, cleaned on save (src/Html.php)',
    `updated_at` DATETIME     NOT NULL,
    PRIMARY KEY (`page_id`, `lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
