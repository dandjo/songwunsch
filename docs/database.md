# Database

The application works with eleven fixed tables. Their names and columns are
a prerequisite; there is no detection or mapping of other tables.

| Table | Columns | Purpose |
| --- | --- | --- |
| `songs` | `id`, `artist`, `title`, `length_sec` (seconds, `NULL` = unknown), `genre` | Repertoire, see [Maintaining the repertoire](repertoire.md) |
| `song_wishes` | `id`, `song_id`, `artist`, `title`, `length_sec`, `genre`, `wisher`, `created_at`, `position`, `room_id`, `wished` | Wish list, one row per song and room, held by a unique key on (`room_id`, `song_id`). `wisher` = the guest's name if given; `position` = manual order (drag & drop); `room_id` 0 = main room; `wished` = how often the song was wished while the entry has been open |
| `song_suggestions` | `id`, `artist`, `title`, `suggester`, `created_at`, `room_id` | Open song suggestions, unique per room, artist and title, see [Song suggestions](suggestions.md). `suggester` = the guest's name if given; `room_id` = the room whose list it is on (0 = main room). An adopted or deleted suggestion leaves the table |
| `settings` | `name`, `value`, `updated_at` | Key/value store: the open/closed switch per room (`wishes_paused`, `wishes_paused:<room id>`), the marker of *Close all rooms* (`wishes_paused_all`), the daily secrets of the wish guard (`secret:<date>`), the live logo per design (`logo_id`, `logo_id_light`), the main room's name and the start room, the languages' fallback order, the footer line per language (`footer_html.<code>`), the colours (`colors.*`, and `colors.own` for the admins' own palette), the interface settings (`ui.*`), the limits (`limits.*`), each user's own preferences (`user.<id>.…`, for instance the delete confirmations) and the revision counters for live updates |
| `wish_throttle` | `id`, `sender`, `created_at` | Rate limiting, see [Protecting the wishing](wish-protection.md). `sender` is an HMAC of the IP address with the daily secret, never a plain IP; entries older than an hour are deleted |
| `users` | `id`, `username`, `password_hash`, `role_admin`, `role_moderator`, `role_editor`, `active`, `created_at`, `updated_at` | Staff accounts, see [Users and roles](users-and-roles.md). `username` is unique |
| `rooms` | `id`, `slug`, `name`, `active`, `listed`, `created_at`, `updated_at` | Rooms, see [Rooms](rooms.md). `slug` = the machine name in the address (unique); `active` 0 = archived; `listed` 1 = guests see the room in the switcher and the list, 0 = reached through its address only. The main room has no row (id 0) |
| `room_songs` | `room_id`, `song_id` | A room's song selection from `songs` |
| `uploads` | `id`, `kind`, `mime`, `data`, `width`, `height`, `created_at` | The header logos, see [Logo](logo.md) – kept in the database so a deployment cannot lose them. `width` and `height` are `NULL` for SVG |
| `pages` | `id`, `slug`, `footer_position`, `created_at`, `updated_at` | The admins' pages: address and footer place, see [Pages and footer](pages.md). `slug` is unique; `footer_position` `NULL` = not linked in the footer |
| `page_translations` | `page_id`, `lang`, `title`, `body`, `updated_at` | A page's title and body per language, one row per language, see [Pages in several languages](pages.md#pages-in-several-languages) |

All tables use InnoDB with `utf8mb4` / `utf8mb4_unicode_ci`. There are no
foreign keys. A wish copies artist, title, length and genre; `song_id` is
deliberately not a foreign key, so a deleted song does not take its wishes
with it. A song wished again while it is still open gets no second row –
`wished` counts on the existing one, and a unique key makes that the
database's rule rather than the caller's timing. Wishes and suggestions hold no IP
address and no user agent; the only personal data is the name a guest chose
to give, and it goes when the wish or suggestion goes.

## Creation

The definition lives in one place, `src/Schema.php`. On every request,
before the first data access, `Schema::ensure()` runs one query against the
`INFORMATION_SCHEMA`. It learns which of the eleven tables exist and which
columns they have, and creates the missing tables. This happens in every
environment – Docker, shared host, local. For that the database user needs
`CREATE TABLE` once, and `SELECT`, `INSERT`, `UPDATE`, `DELETE` in
operation. The database itself must exist.

Whether a request may create a table at all is `schema_ddl` in `config.php`
(`SCHEMA_DDL` in the `.env`), on by default. With it off, a missing table is
reported – *Run tools/install.php or load sql/schema.sql* – and the account
the site runs under needs `SELECT`, `INSERT`, `UPDATE` and `DELETE` and
nothing more.

Three ways lead to the same result; the second one only while `schema_ddl`
is on, while `tools/install.php` creates the tables either way:

```bash
php tools/install.php        # beforehand, without a web server (exit code 0 = all there)
 or: open the first page in the browser
 or: mysql songwunsch < sql/schema.sql   (if the web user may not CREATE)
```

`tools/install.php` reads `config.php` (or the environment variables) like
the application. It creates the missing tables and the first admin from
`auth.user` / `auth.hash` if the `users` table is empty – nothing else.
Exit code 0 means everything is in place; on an error
it prints the message and exits with 1. It is safe to run at any time. In
the Docker stack: `docker compose exec web php tools/install.php`.

`sql/schema.sql` contains the same statements as `src/Schema.php`; whoever
changes one changes both. The file begins with `SET NAMES utf8mb4`, so
umlauts survive the import through the `mysql` client.

## Existing tables

The application never alters an existing table, and it never migrates one.
A table it finds is a table it uses.

**Columns.** If one of the expected columns is missing from an existing
table, the application stops with a clear message that names the table and
the columns, instead of failing with an SQL error in the middle of
operation. Rename the table or recreate it from `sql/schema.sql`.
