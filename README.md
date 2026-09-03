# Songwunsch

A small PHP 8 application with a sober, dark interface: neutral dark grey,
layered by brightness (`#0d0e13` ground, `#14161c` shell, `#1a1c24` content),
hairlines as edges, flat surfaces without gradients. Only the accents carry
colour – gold (`#e6b450`) for actions and the active menu item, violet
(`#8d7ce0`) as a secondary tone for genre chips and counters. Rounded corners
are functional rather than decorative: 8 px on buttons and inputs, 12 px on
surfaces, 16 px on the shell. System fonts only.

By default the application sits at the domain root: `https://example.org/` is
the song list, `/wishes` the wish list, `/login` the sign-in page. A sub-path
such as `/songliste` is possible, see [Base path](#base-path). In addition
there are **rooms** with their own song selection and their own wish list
under `/rooms/<name>`, see [Rooms](#rooms).

* **Start page** (public) – the song list as cards with title, artist, length
  and genre, a search field and a sort bar above. One click on a song puts it
  on the wish list.
* **Edit the song list** (editor role) – add, change and delete titles; see
  [Maintaining the song list](#maintaining-the-song-list).
* **Wish list** (publicly readable) – received wishes in the order they will
  be played. Guests see the list without buttons and without sorting; the
  moderator role sorts, reorders, deletes single wishes or the whole list and
  pauses wishing.
* **Users** (admin) – create accounts, assign roles, lock them; see
  [Users and roles](#users-and-roles).

No framework, no Composer dependencies, no external fonts or CDNs – the
application runs on any hosting with PHP 8.1+ and PDO/MySQL.

The interface is written in English and translated through `.po` files;
German and French are included, further languages are one file away. See
[Languages](#languages).

## Running with Docker

The one-time prerequisite is the Docker network through which the reverse
proxy reaches the application:

```bash
docker network create proxy   # first time only
cp sample.env .env            # adjust the passwords in there
```

### Option A – an existing Traefik

If a Traefik is already running on the machine, attached to the `proxy`
network, this is enough:

```bash
docker compose up -d
```

The application registers through its labels and is reachable at
<https://songwunsch.localhost/>. For a locally trusted certificate, once, in
the Traefik directory:

```bash
./add-domain-cert.sh songwunsch.localhost
```

### Option B – without an external Traefik

The `standalone` profile brings its own Traefik:

```bash
./docker/traefik/make-cert.sh songwunsch.localhost   # optional, otherwise a browser warning
docker compose --profile standalone up -d
```

This Traefik occupies ports 80 and 443; if those are taken, change
`TRAEFIK_HTTP_PORT` and `TRAEFIK_HTTPS_PORT` in the `.env`. The dashboard is
at <http://127.0.0.1:8081/dashboard/>.

Both options use the same labels – the only question is which Traefik picks
them up.

### What the stack contains

| Service | Content |
| --- | --- |
| `web` | PHP 8.3 + Apache, source code as a bind mount (changes take effect immediately) |
| `db` | MySQL 8; on the very first start (empty volume) the demo repertoire from `sql/demo.sql` is imported, later via `php tools/demo.php --force` |
| `traefik` | only in the `standalone` profile |

The tables are not created by the stack but by the application itself – see
[Database](#database). `config.php` is generated from `config.example.php` on
the first start and takes its values from the `.env`. To create a password
hash:

```bash
docker compose exec web php tools/hash.php 'MyPassword'
```

To reset the database (this deletes the wishes as well):

```bash
docker compose down -v
```

## Base path

The path the application lives under is set in one place: `BASE_PATH` in the
`.env` (or `base_path` in `config.php`). The default is `/`, the domain root.

```
BASE_PATH=/              ->  https://songwunsch.localhost/
BASE_PATH=/songliste     ->  https://songwunsch.localhost/songliste/
```

Below the base path these addresses exist; anything else is a 404:

| Address | Page |
| --- | --- |
| `/` | Song list (start page) |
| `/wishes` | Wish list |
| `/login` | Sign-in |
| `/song?key=<id>` | Create a song (`key=0`) or edit one – editor |
| `/users`, `/user?id=<id>` | User management – admin |
| `/rooms` | List of rooms with *Change to*; editors create rooms here |
| `/room?id=<id>` | Create a room (`id=0`) or edit one – editor |
| `/rooms/<name>` | Song list of a room |
| `/rooms/<name>/wishes` | Wish list of a room |
| `/rooms/<name>/manage` | Manage the room's songs (selection from the master list) – editor |

Sorting, search and page are appended as query parameters
(`/wishes?sort=artist`), `?lang=<code>` switches the language. Addresses of
earlier versions (`index.php?p=wishes`) are redirected permanently (301) to
the new form.

The value governs everything that contains an address: links and form
targets, `assets/style.css` and `assets/app.js`, the redirects after every
action, the `fetch` endpoint for drag & drop and the scope of the session
cookie (with a sub-path `path=/songliste/` – two applications on the same
domain thus share no session).

Only absolute paths are generated (`/songliste/wishes?…`), never relative
ones. That is why the address works with and without a trailing slash.

For a sub-path there are two modes of operation, both with the same value:

* **The reverse proxy strips the prefix.** This is what the Docker stack does:
  the Traefik routers match on `Host(...) && PathPrefix(/songliste)`, the
  `stripprefix` middleware removes the prefix, and Apache in the container
  sees `/wishes`. The application puts it back in front when generating
  addresses.
* **The files sit in a sub-folder** named `songliste` in the document root.
  Then the prefix reaches Apache anyway; nothing needs stripping.

With a sub-path nothing redirects from the domain root –
`https://songwunsch.localhost/` answers 404 as long as nothing else is mounted
there; the root belongs to another application. If it should point to the
song list, that is an additional Traefik router on `Path(`/`)` with a
`redirectregex` middleware – deliberately not included.

## Installation without Docker

1. Put the files into the web directory: either directly into the document
   root (then the default fits) or into a sub-folder such as `songliste` –
   then set `'base_path' => '/songliste'` in `config.php`. See
   [Base path](#base-path). The bundled `.htaccess` files must come along,
   see [Web server](#web-server).
2. Create the configuration:

   ```bash
   cp config.example.php config.php
   ```

   Enter the database credentials. `config.php` is excluded from version
   control through `.gitignore`.

3. Define the first admin:

   ```bash
   php tools/hash.php 'MyPassword'
   ```

   Put the printed hash into `config.php` under `auth.hash`, the username
   under `auth.user`. From these the admin account is created on the first
   sign-in; every further user is created by the admin inside the
   application. The defaults are `Administrator` / `Administrator` –
   **change them before the first use.**

4. Create the database and its user. The tables `songs` and `song_wishes` are
   created by the application on the first request; to have them beforehand,
   run `php tools/install.php`. If the database user may not `CREATE TABLE`,
   import `sql/schema.sql` instead. See [Database](#database).

5. For a first test without your own data, import the 50 demo titles:

   ```bash
   php tools/demo.php
   ```

   The script also creates missing tables and only fills an empty `songs`
   table; with `--force` it adds the titles regardless. If you prefer the
   MySQL client: `mysql <db> < sql/demo.sql`.

## Web server

To the outside exactly one file exists: `index.php`. Everything below the
base path lands there – unknown addresses are answered with 404 –, only
`assets/` is served directly by the web server. All other PHP files,
`config.php`, `sql/`, `lang/` and this README are blocked from outside (403;
on Apache the block applies before the rewrite).

**Apache** (2.4, the usual case on hosted servers): the `.htaccess` in the
application folder handles both – the rewrite to `index.php` and the block on
the remaining files. In addition there are `.htaccess` files in `src/`,
`templates/`, `tools/`, `sql/` and `lang/` that block these folders even when
`mod_rewrite` is missing. The vhost must allow `.htaccess` (`AllowOverride
All` or at least `FileInfo Limit`). A 500 right after uploading is almost
always exactly that or a missing `mod_rewrite`.

**nginx** knows no `.htaccess`; the same rules belong in the `server` block
(application at the domain root, PHP-FPM through a socket; for a sub-path put
`/songliste` in front of every address):

```nginx
root /var/www/html;

location ^~ /assets/ {
    expires 7d;
}

location / {
    # Only the front controller exists; everything else is routed to it.
    rewrite ^ /index.php last;
}

location = /index.php {
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root/index.php;
    fastcgi_pass unix:/run/php/php8.3-fpm.sock;
}
```

Since only `index.php` is handed to PHP-FPM, all other PHP files are
automatically unreachable. In the Docker stack `mod_rewrite` and
`AllowOverride All` are already enabled; the same `.htaccess` files apply
there.

## Version and cache

The application appends `'version'` from `config.php` (or `APP_VERSION` from
the environment) as `?v=…` to `assets/style.css` and `assets/app.js`. Browsers
and proxies therefore fetch the files anew after a deployment instead of
showing the cached copy. Raise the value with every release – it may look like
anything, `1.4.0` as well as `2026-09-03`. Without a value the suffix is
omitted.

## Deployment

`tools/deploy.sh` syncs the application folder to the host via `rsync` over
SSH – everything needed to run, without `config.php`, Docker files,
`.env`/`sample.env`, editor files and all git metadata (`.git/`, `.gitignore`,
`.gitattributes`, `.gitmodules`, `.github/`). Target host and directory are
read from the `.env`: `DEPLOY_HOST` (SSH host or alias from `~/.ssh/config`,
required) and `DEPLOY_DIR` (default `public_html`), see `sample.env`. Both can
be overridden as environment variables, e.g.
`DEPLOY_HOST=other-host tools/deploy.sh -n`. Without `DEPLOY_HOST` the script
stops with a message.

```bash
tools/deploy.sh -n         # dry run: shows what would change, including the version
tools/deploy.sh            # the real thing
tools/deploy.sh --no-bump  # without raising the version
```

After the sync the script raises `'version'` in `config.php` **on the
server**, see [Version and cache](#version-and-cache): `1.0.4` becomes
`1.0.5`, anything else becomes today's date with a counter (`2026-09-03.1`).
`--no-bump` leaves the version untouched; if the entry is missing on the
server, the script says so and does nothing else. The sync runs with
`--delete`: files that no longer exist locally disappear on the server as
well. `config.php` is exempt – it is neither transferred nor deleted – so on a
fresh host create it once from `config.example.php`. Permissions are set to
`755` (folders) and `644` (files); some hosters reject group-writable files.
The files sit directly in `public_html`, which matches the default
`'base_path' => '/'`.

## Database

The application works with seven fixed tables. Names and columns are a
prerequisite; there is no detection or mapping of foreign tables.

| Table | Columns | Purpose |
| --- | --- | --- |
| `songs` | `id`, `artist`, `title`, `length_sec` (seconds, `NULL` = unknown), `genre` | Repertoire |
| `song_wishes` | `id`, `song_id`, `artist`, `title`, `length_sec`, `genre`, `created_at`, `position`, `room_id` | Wish list, `room_id` 0 = main room |
| `settings` | `name`, `value`, `updated_at` | Pause switch per room, marker of the admin's pause, daily secrets |
| `wish_throttle` | `id`, `sender`, `created_at` | Rate limiting, see [Protecting the wishing](#protecting-the-wishing) |
| `users` | `id`, `username`, `password_hash`, `is_admin`, `role_moderator`, `role_editor`, `active`, `created_at`, `updated_at` | Staff accounts, see [Users and roles](#users-and-roles) |
| `rooms` | `id`, `slug`, `name`, `active`, `created_at`, `updated_at` | Rooms, see [Rooms](#rooms) |
| `room_songs` | `room_id`, `song_id` | A room's song selection from `songs` |

A wish copies artist, title, length and genre; `song_id` is deliberately not a
foreign key so that a deleted song does not take its wishes with it.

**Creation.** The definition lives in one place, `src/Schema.php`. On every
request, before the first data access, `Schema::ensure()` checks with one
query against the `INFORMATION_SCHEMA` whether the tables exist and creates
the missing ones. This happens regardless of the environment – Docker, shared
host, local. The database user needs `CREATE TABLE` once for that, and
`SELECT`, `INSERT`, `UPDATE`, `DELETE` in operation. The database itself must
exist.

Three ways lead to the same result:

```bash
php tools/install.php        # beforehand, without a web server (exit code 0 = all there)
# or: open the first page in the browser
# or: mysql songwunsch < sql/schema.sql   (if the web user may not CREATE)
```

`sql/schema.sql` contains the same statements as `src/Schema.php`; whoever
changes one changes both.

**Existing tables** are checked: if one of the expected columns is missing
from an existing table, the application stops with a clear message instead of
an SQL error in the middle of operation. The only exception are columns a
newer version added (`Schema::ADDITIONS`, currently `song_wishes.room_id` and
`rooms.active`): `ensure()` creates those itself via `ALTER TABLE`, for which
the database user needs `ALTER` once. Without that right the statement is
available as a comment in `sql/schema.sql`. Tables from earlier versions
(`musik_repertoire`, `song_wishes` with `song_ref` and `length_raw`) are not
compatible – rename them, then the application creates the new ones.

## Languages

English is the source language: every text in the code is English and at the
same time the key (`msgid`). Translations live as GNU gettext files in
`lang/<code>.po`, read by `src/PoFile.php` – a small parser of its own,
because neither the gettext nor the intl extension should be required. No
compiled `.mo` files and no system locales are needed.

**Choosing a language.** Top right, a globe icon with the current language
code opens a menu of all available languages (a `<details>`, works without
JavaScript). A click sets `?lang=<code>`; the choice is kept in the session
and in a cookie valid for one year (`songwunsch_lang`, only the language code,
no personal data). Without a choice the browser's `Accept-Language` header
decides, otherwise English.

**Adding a language.** Dropping in a file is enough:

```bash
cp lang/songwunsch.pot lang/fr.po      # file name = language code
```

Fill in three lines in the file header:

```
"Language: fr\n"
"Plural-Forms: nplurals=2; plural=(n > 1);\n"
"X-Native-Name: Français\n"
```

`X-Native-Name` is the name shown in the language switcher, `Plural-Forms`
the plural rule in gettext notation (the expression is evaluated by a small
interpreter, not via `eval`). Then translate the `msgstr` lines, done – on the
next request the language appears in the switcher. Missing or empty
translations fall back to English. Poedit and similar tools can edit the file
directly.

**Placeholders** are written in curly braces (`{title}`, `{n}`) and may be
reordered in the translation. A few technical entries carry a `msgctxt`: the
thousands separator (`,`) and the date formats for PHP's `date()` (`H:i:s`,
`M j, H:i`) – here the format is translated, not the text.

**Template and completeness.** `tools/extract-strings.php` collects every
`t()` and `tn()` call from the code into `lang/songwunsch.pot` and reports
missing and obsolete entries per `.po` file:

```bash
php tools/extract-strings.php          # write the template, print a report
php tools/extract-strings.php --check  # check only, exit code 1 on gaps
```

In the code: `t('Save')`, `t('Page {page} of {pages}', ['page' => 1, 'pages' => 3])`,
`tn('{n} wish', '{n} wishes', $count)`; a third or fifth parameter sets the
context. Output always goes through `Format::e()`; only where a translation
deliberately contains HTML placeholders (links, `<strong>`) are those inserted
pre-escaped and the rest printed without escaping.

## Users and roles

All operating functions sit behind a sign-in. Users are stored in the `users`
table and managed on the **Users** page.

| Role | May |
| --- | --- |
| **Admin** | Create, edit, lock and delete users – and everything editors and moderators may do |
| **Editor** | Maintain the song list: add, edit, delete titles; create, edit, delete rooms and manage their songs |
| **Moderator** | Edit the wish list: sort, reorder, delete, clear, pause wishing (everyone may view it) |

Editor and moderator can be combined; a user without a role can sign in but
only sees the public song list.

**Exactly one admin.** The database allows only one: `is_admin` is `1` or
`NULL`, and a unique index permits only a single `1`. The admin role is
therefore not assigned but **handed over** – on the edit page of another
active user, with a confirmation. The previous admin keeps their other roles
but loses user management. The admin can neither be deleted nor locked while
they are admin.

**First admin.** As long as the table is empty, the application creates the
admin from `auth.user` and `auth.hash` in `config.php` on the first sign-in
(or via `php tools/install.php`). After that only the table counts; the values
in `config.php` have no effect any more. While the admin is still signed in
with the shipped default password, every page shows a red notice with a link
to their own user form. This is checked at sign-in (or once for an existing
session) and remembered in the session; changing the password in the own form
removes the notice.

**Sessions.** The session holds only the user ID; the record is loaded on
every request. Changed roles apply immediately, a locked or deleted user is
signed out with the next click. Passwords are stored with `password_hash()`
(bcrypt), at least 8 characters; the sign-in verifies a hash even for an
unknown name so the response time does not reveal whether a name exists.

**Data minimisation.** Only username and password hash are stored – no
e-mail, no real name, no sign-in timestamps. Whoever names accounts after real
people processes personal data by doing so; role or function names (`dj1`,
`bar`) avoid that.

## Maintaining the song list

With the editor role (or as admin) every row of the song list additionally
carries *Edit* and *Delete*, and *Add song* stands above the list.

**Entering the length.** The field accepts `3:45`, `1:02:03` or a plain number
of seconds (`225`); it is stored in seconds, left empty it becomes `NULL`.
Artist and title are required (up to 255 characters), genre is optional (up to
128 characters) with a suggestion list of the values already in use.

**Deleted songs and the wish list.** A wish stores a copy of artist, title,
length and genre. If a song is deleted from the song list, wishes already
received for it remain fully readable.

## Rooms

A room is a capsule of song list and wish list with its own address, for
instance for two stages or two evenings. `/` and `/wishes` are technically the
**main room**: always there, without a record, with the whole song list.

**Creating.** On the **Rooms** page (`/rooms`) editors create rooms. A room
has a display name (up to 128 characters, free) and a **machine name** for the
address: 2 to 64 characters, lowercase `a–z`, digits and single hyphens,
checked in `RoomRepository::validate()` against `SLUG_PATTERN`; uppercase is
converted to lowercase beforehand. A room `sommerfest-2026` is then reachable
at `/rooms/sommerfest-2026`, its wish list at `/rooms/sommerfest-2026/wishes`.
Changing the machine name changes the address – links already handed out stop
working.

**Managing songs.** A room's song list is a selection from the master list
(the song list of the main room), table `room_songs`. Under
`/rooms/<name>/manage` there are two columns: on the left the master list
without the songs already in the room, on the right the room's list. An arrow
to the right takes a song into the room, an arrow to the left removes it
again; a search field filters both columns, *Add all …* and *Remove all …*
move the whole search result. On narrow screens the columns stack. In the
room's song list the editor's delete button reads *Remove* – the song only
leaves the room. Songs are edited exclusively in the master list; a song
deleted there disappears from all rooms.

**Archiving.** Every room is *active* or *archived* (column `active`,
checkbox in the edit form). Archived rooms vanish from the room switcher and
from the list guests see under `/rooms`, but remain reachable through their
address. Archiving automatically pauses wishing in the room; whoever
reactivates the room resumes it on the room's own wish list. Editors see every
room under `/rooms`, archived ones tagged, and filter by *All*, *Active* and
*Archived*; a search field finds rooms by display or machine name, the list
paginates like the song list. From seven rooms on, the room switcher's overlay
shows a filter field that hides entries as you type (JavaScript; without it
the full list stays).

**Wish list per room.** Wishes carry `room_id` (0 = main room); order,
clearing, deleting and the pause switch act only within the respective room.
Only what is in the room can be wished for. Moderation applies to all rooms.
When a room is deleted, its song selection and its wishes go with it.

**Pausing all rooms.** Under `/rooms`, left of *Add room*, the admin has the
switch *Pause wishing in all rooms*. It closes wishing in the main room and in
every room, archived ones included, and remembers each room's previous state
while doing so (key `wishes_paused_all` in `settings`). While the pause is in
force the switch reads *Resume wishing in all rooms*; it restores the
remembered state: rooms that were open reopen, rooms the moderator had paused
themselves stay paused. Rooms created in the meantime are left unchanged.
Single rooms can be switched on their own wish list at any time.

**Switching.** As soon as a room exists, the **room switcher** stands at the
far left of the navigation: a tab with the name of the current room that, like
the language menu, opens an overlay with all rooms (without JavaScript,
`<details>`). The **Rooms** tab (`/rooms`) appears only for editors and admin;
the page itself with the *Change to* button remains reachable for everyone.
Inside a room its name stands in the header, and all links of the application
stay within the room – generated by `url()` in `src/bootstrap.php`, which
prefixes room-bound pages with `/rooms/<name>`.

## Protecting the wishing

Wishing is public and needs no sign-in – so it is the target for scripts that
flood the list. `src/WishGuard.php` puts several layers in front of it, all
without third-party services and without plain-text IPs:

| Layer | What happens | Setting in `config.php` |
| --- | --- | --- |
| Pause | The moderator closes wishing; the song list stays visible, the *Wish* buttons disappear, a notice with a pause icon stands in the header of every page | Button on the wish list |
| Global limits | At most N open wishes, at most M wishes per minute across all visitors | `wish_limits.max_open`, `wish_limits.per_minute_total` |
| Limit per sender | At most X per minute and Y per hour from the same address | `wish_limits.per_minute_sender`, `wish_limits.per_hour_sender` |
| Brake per session | Minimum gap between two wishes in the same browser | `wish_cooldown_sec` |
| Bot trap | Invisible form field; if it is filled, the wish is silently discarded and the sender sees a success message | – |
| Minimum time | Signed timestamp in the form; submitting less than 2 s after the page load is rejected, the form expires after 6 h | `wish_min_form_sec` |

A limit of `0` disables it. The defaults are 200 open wishes, 30 per minute in
total, 3 per minute and 20 per hour per sender.

**Senders without storing IPs.** The per-sender limit needs one attribute per
visitor. What is stored is not the IP address but an HMAC-SHA256 of the
address with a secret that is regenerated daily (table `settings`,
`secret:<date>`; only today's and yesterday's are kept). The entries in
`wish_throttle` are deleted after an hour. As long as the daily secret exists,
the value is a pseudonym in the sense of the GDPR; afterwards it can no longer
be attributed to anyone. The secret also signs the timestamp in the form.

**Behind a reverse proxy** the visitor's address is in `X-Forwarded-For`. The
application uses the last entry – the proxy itself appends that one, anything
before it can be invented by the client – but only if `trust_proxy` is set
(`TRUST_PROXY=1`, the default in the Docker stack). If the web server is also
reachable directly, leave the value at `0`, otherwise a forged header bypasses
the per-sender limit.

**What the application cannot do:** a flood of requests that overwhelms the
web server itself belongs to the layer in front – Traefik's rate-limit
middleware or the hoster.

## Usage

| What | How |
| --- | --- |
| Search | Field at the top; several terms are combined with AND; `/` jumps into the search field |
| Switch language | Language menu (globe) top right; the choice is remembered |
| Sign in / sign out | Account menu (person icon) top right next to the language menu; when signed in it shows the name and *Log out* |
| See the site as a guest | Signed in: account menu → *View as guest*; a notice in the header and *End guest view* lead back. Meanwhile pages, controls and actions behave exactly as for a visitor without a login |
| Sort | Sort bar above the list, a second click reverses the direction |
| Wish | *Wish* button in the row (or a click on the row) |
| Change the order | Wish list → drag the row (drag & drop) or ▲/▼ in the first column |
| Delete a wish | Wish list → *Delete* in the row |
| Delete everything | Wish list → *Clear list* |
| Pause wishing | Moderator: wish list → *Pause wishing* / *Resume wishing* |
| Pause everywhere | Admin: Rooms → *Pause wishing in all rooms* / *Resume …* |
| Add a song | Editor: song list → *Add song* |
| Change a song | Editor: *Edit* in the row |
| Delete a song | Editor: *Delete* in the row (with confirmation) |
| Change room | Room switcher at the far left of the navigation (shows the current room, opens the list) or Rooms → *Change to* |
| Create a room | Editor: Rooms → *Add room*, then *Manage* |
| Manage a room's songs | Editor: *Manage* in the room or under Rooms |
| Create a user | Admin: Users → *Add user* |
| Hand over the admin role | Admin: Users → *Edit* → *Hand over admin role* |
| Switch off delete confirmations | Signed in: Settings → *Delete confirmations*, per account and separately for songs, wishes and rooms, each only with the matching role (all on by default; *Clear list* always asks) |

The wish list starts in manual order – initially this equals the order of
arrival, oldest on top. Sorting by a column is only a view; the stored order
is kept and reachable again via *#* or *Manual order*.

Sorting, searching, wishing, reordering and deleting work without JavaScript –
the ▲/▼ switches are ordinary forms and at the same time the way for keyboard
and touch. `assets/app.js` adds drag & drop, row click, confirmations and the
`/` key.

There is a single layout for all screen sizes: the compact card layout that
used to be the phone view only. There is no separate desktop table view any
more; on wide screens the shell is centred and limited to 1180 px. In the
header the word mark, the language menu and the account menu (person icon,
opens *Log in* or name, *View as guest* and *Log out* as a popout like the
language menu) share the first row, the navigation is right-aligned below. The popouts (language,
account, room switcher) are `<details>` and work without JavaScript; with
JavaScript they additionally close on a click outside or on Escape, and
opening one menu closes the others.

All lists share the same action pattern: a card's buttons stand at the right
in a vertical stack, each as wide as the widest. *Edit* and *Delete* share one
line of the stack as an icon pair (pencil / bin, 50 % each); their text remains
as a tooltip and for screen readers.

* **Song list** – title, below it artist · length · genre; *Wish* on the
  right, when signed in the pair *Edit* / *Delete* below it.
* **Rooms** – name, below it the address, below that the counts spelled out
  ("50 songs · 7 wishes"); *Change to* on the right, for editors *Manage* and
  the pair *Edit* / *Delete* below it.
* **Users** – name, below it roles · status; the pair *Edit* / *Delete* on the
  right (the admin only has *Edit*).
* **Wish list** – position on the left, title, below it artist · length ·
  genre; on the right the time received and next to it ▲/▼ above *Delete*
  (bin, as wide as the arrow pair). On phones the time received moves below
  the meta line. The relative time ("5 min ago") is dropped; overlong names
  are truncated with an ellipsis instead of pushing the buttons.

Input fields are 16 px so iOS does not zoom in.

## Structure

```
index.php              Front controller: routing, actions, post/redirect/get
.htaccess              Apache: everything to index.php, other files blocked (also in src/, templates/, tools/, sql/, lang/)
config.example.php     Template for config.php (credentials, base path, first admin)
src/bootstrap.php      Autoloader and helpers (base_path, url, asset, icon, redirect, flash)
src/Database.php       PDO connection, prepared statements only
src/Schema.php         Fixed table definition, creates missing tables
src/SongRepository.php Song list: search, sort, paginate, maintain
src/WishRepository.php Wish list: create, read, sort, delete
src/WishGuard.php      Protection of wishing: limits, bot trap, pause
src/Settings.php       Key/value store in the settings table
src/Security.php       Session, login against users, roles, CSRF, wish brake
src/UserRepository.php Users: create, edit, delete, hand over admin
src/RoomRepository.php Rooms: create, edit, delete, song selection from the master list
src/Format.php         Escaping and formatting (length, timestamps, numbers)
src/Translator.php     Discover languages, choose one, t()/tn()
src/PoFile.php         .po parser including the Plural-Forms interpreter
templates/             layout, home, wishes, song, users, user, rooms, room, room_songs, login, error, _sortbar
assets/                style.css (dark interface), app.js
lang/                  songwunsch.pot (template), de.po (German), fr.po (French), further <code>.po
sql/                   schema.sql (all tables), demo.sql (test data)
tools/hash.php         Create a password hash
tools/install.php      Create the tables beforehand, set up the first admin (CLI)
tools/demo.php         Import the demo repertoire from sql/demo.sql (CLI)
tools/extract-strings.php  Generate the translation template, check .po files (CLI)
tools/deploy.sh        Sync to the web host via rsync, see Deployment
compose.yml            Docker stack: web, db, optional traefik
sample.env             Template for .env
docker/                Dockerfile, php.ini, entrypoint, Traefik configuration
```

## Security and data protection

* All database values go through prepared statements; table and column names
  are fixed in the code, sort parameters pass a whitelist.
* Output consistently through `Format::e()` (`htmlspecialchars`).
* Session cookie `HttpOnly` + `SameSite=Lax`, `Secure` as soon as HTTPS is
  active, `path` limited to the base path; `session_regenerate_id()` on
  sign-in.
* Every writing action is a POST form with a CSRF token.
* Every operating function checks sign-in **and** role in the front controller
  (`require_role`), not only in the view; JSON calls (drag & drop) receive
  401/403 instead of a redirect.
* `UPDATE` and `DELETE` always address by primary key and are limited to one
  row.
* The return address from the `back` form field is only accepted if it starts
  with the application's own front controller – no redirect to the outside.
* Database credentials live exclusively in the unversioned `config.php`; user
  passwords exist only as bcrypt hashes in `users`.
* Only `index.php` is reachable from outside; `src/`, `templates/`, `tools/`,
  `sql/`, `lang/` and `config.php` are blocked by the web server, see
  [Web server](#web-server).
* For production set `'show_errors' => false` – technical details are then
  shown only to signed-in users, everything else goes to the error log.
* For every wish only artist, title, length, genre and the timestamp are
  stored – **no** name, **no** IP address, **no** user agent. The wish list
  therefore contains no personal data. The rate limiting keeps a daily
  changing pseudonym of the address for at most one hour, see
  [Protecting the wishing](#protecting-the-wishing).

## Accessibility

Semantic tables with `<caption>` and `scope`, `aria-sort` on the column
headers, meaningful names on all buttons, skip link, visible focus ring,
contrasts to WCAG 2.2 AA, fully operable by keyboard (including reordering via
the ▲/▼ switches), `prefers-reduced-motion` is respected.
A test with a screen reader and keyboard before going live is still
worthwhile.
