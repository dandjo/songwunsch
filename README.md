# Songwunsch

A small PHP 8 application with a sober, dark interface: neutral dark grey,
layered by brightness (`#0d0e13` ground, `#14161c` shell, `#1a1c24` content),
hairlines as edges, flat surfaces without gradients. Only the accents carry
colour – gold (`#e6b450`) for actions and the active menu item, violet
(`#8d7ce0`) as a secondary tone for genre chips and counters. Rounded corners
are functional rather than decorative: 8 px on buttons and inputs, 12 px on
surfaces, 16 px on the shell. System fonts only.

By default the application sits at the domain root: `https://example.org/` is
the repertoire, `/wishes` the wish list, `/login` the sign-in page. A sub-path
such as `/songliste` is possible, see [Base path](#base-path). In addition
there are **rooms** with their own song selection and their own wish list
under `/rooms/<name>`, see [Rooms](#rooms).

* **Start page** (public) – the repertoire as cards with title, artist, length
  and genre, a search field and a sort bar above. One click on a song puts it
  on the wish list. On the first visit the site asks for the guest's name; it
  is kept in a cookie and shown on the wish list next to their wishes, see
  [The guest's name](#the-guests-name).
* **Edit the repertoire** (editor role) – add, change and delete titles; see
  [Maintaining the repertoire](#maintaining-the-repertoire).
* **Wish list** (publicly readable) – received wishes in the order they will
  be played. Guests see the list without buttons and without sorting; the
  moderator role sorts, reorders, deletes single wishes or the whole list and
  closes or opens the room.
* **Song suggestions** (public) – whoever misses a song names artist and
  title; editors adopt a suggestion into the repertoire or delete it, see
  [Song suggestions](#song-suggestions).
* **Users** (admins) – create accounts, assign roles, lock them; see
  [Users and roles](#users-and-roles).
* **Pages and footer** (admins) – an imprint, FAQs or a privacy notice,
  written in CKEditor, public under `/pages/<name>`; the footer links the
  pages the admins put there, in their order. See
  [Pages and footer](#pages-and-footer).

No framework, no Composer dependencies, no external fonts or CDNs – the
application runs on any hosting with PHP 8.1+ and PDO/MySQL. The one bundled
library is CKEditor 5 for the page editor (`assets/vendor/ckeditor5`, loaded
on that one admin page only).

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

Below the base path these addresses exist; anything else is a 404. The one
exception is `/rooms/<name>` with an unknown name: a link to a room that has
been deleted or renamed leads to the start page with a notice, where the
remembered room or the start room takes over.

| Address | Page |
| --- | --- |
| `/` | Repertoire (start page) |
| `/wishes` | Wish list |
| `/suggestions` | Song suggestions: form and searchable list for everyone, buttons for editors |
| `/login` | Sign-in |
| `/name` | The guest's name for the wish list – change or remove it |
| `/song/new`, `/song/<id>` | Create or edit a song – editor; `/suggestions/<id>/adopt` adopts a suggestion into a new song |
| `/users/<id>/settings` | Personal settings of the signed-in user (own id only; `/settings` redirects there) |
| `/pages/<name>` | A page for everyone (imprint, FAQ, …), in the footer or not |
| `/rooms` | List of rooms, each name leads into its room; moderators close and open rooms, editors create them here |
| `/rooms/new`, `/rooms/<id>/edit`, `/rooms/main/edit` | Create or edit a room, rename the main room – editor |
| `/logo/<id>` | An uploaded logo, see [Logo](#logo) |
| `/rooms/<name>` | Repertoire of a room |
| `/rooms/<name>/wishes` | Wish list of a room |
| `/rooms/<name>/suggestions` | Suggest a song from inside a room – the adopted song joins the room |
| `/rooms/<name>/manage` | Manage the room's songs (selection from the master list) – editor |
| `/admin/users`, `/admin/users/new`, `/admin/users/<id>/edit` | User management – admins |
| `/admin/logos` | Header logos – admins, see [Logo](#logo) |
| `/admin/pages`, `/admin/pages/new`, `/admin/pages/<id>/edit` | The admins' pages: list, create, edit – admins, see [Pages and footer](#pages-and-footer) |
| `/admin/footer` | Which pages the footer links, in which order – admins |
| `/admin` | Leads to `/admin/users` |

Sorting, search and page are appended as query parameters
(`/wishes?sort=artist`), `?lang=<code>` switches the language. Lists and their
records share a prefix: `/rooms` lists, `/rooms/new` creates, `/rooms/<id>/edit`
edits, and `/rooms/<name>` is the room itself (so `new` and `main` are not
available as room names). Everything the *Administration* menu leads to sits
below `/admin` – users, logos, design, limits, pages, footer – while a page's
public address stays `/pages/<name>`. Anything else is a 404; there are no redirects from
addresses of earlier versions.

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
repertoire, that is an additional Traefik router on `Path(`/`)` with a
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
   under `auth.user`. From these the first admin account is created on the
   first sign-in; every further user is created by an admin inside the
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

## Logo

Admins can put a logo in the header instead of the word mark
“Songwunsch” and the claim; the room's name keeps its place beside it. Logos
are managed on their own page, **Logos** (`/admin/logos`, in the *Administration*
menu, admins only): every logo ever uploaded is listed there with a preview at the
header's size, and exactly one is *live* at a time – or none, then the word
mark shows. Upload, *Switch live*, *Delete* (bin icon, like in every list); a
new upload goes live right away unless the box is unticked. The live logo is remembered in `settings`
under `logo_id`.

Any image size works: on upload a raster image (PNG, JPEG, WebP, GIF) is
scaled down to 144 px in height with GD – three times the 48 px the header
shows (40 px on phones), so it stays sharp on every screen – and stored as
WebP (quality 90, about a third of a PNG's size); transparency is kept, an
animated GIF loses its animation. A WebP that is already no taller than
144 px is stored as it is. An SVG is stored as it is. The type is checked
from the file's content; an SVG with scripting is rejected (it is only ever
shown through `<img>`, where scripts do not run). Without WebP support in GD
the image is stored as PNG (JPEG stays JPEG); without the GD extension the
original is stored unchanged and scaled by CSS alone. The size limit is the
server's `upload_max_filesize` (20 MB in the Docker stack).

The files live in the `uploads` table, not on disk: the deployment syncs the
code with `--delete`, a shared host may have no writable folder, and a
database backup carries the logos along. A logo is served under
`/logo/<id>` with a long cache lifetime – the bytes of an id never change.

## Colours

The interface is dark with gold for actions, violet for tags and counters,
red for danger and green for success. Each of these areas has one base colour
that admins replace under **Design** (`/admin/theme`, in the *Administration*
menu): a colour picker and a hex field per area that follow each other, a
*Default* button that brings the built-in colour back. The values are kept in
the `settings` table (`theme.<area>`), so they survive a deployment and travel
with a database backup.

| Area | Used for | Default |
| --- | --- | --- |
| Accent | Buttons, active tab, links, "wunsch" in the word mark, gold notices | `#e6b450` |
| Secondary | Tags, the counters on the tabs, chips | `#8d7ce0` |
| Danger | Closed rooms, delete buttons, warnings, errors | `#ff6f85` |
| Success | Confirmations | `#4ed08c` |
| Background | Page ground; shell, panels, fields and lines are lightened steps of it | `#0d0e13` |
| Text | Text; the muted text is a step towards the background | `#e9ebf1` |

Values are `#rrggbb` (or `#rgb`, the `#` may be left out); an empty field
means the built-in colour. The stylesheet keeps every colour as a custom
property on `:root` – the base colours and the shades derived from them
(bright, deep, frame, hover tint, notice tint). `src/Theme.php` derives those
shades from a configured base colour with the same ratios and the layout
prints one `<style>:root{…}</style>` block that overrides the stylesheet;
nothing is printed while nothing is configured. Keep the contrast readable –
gold on a light background, say, will not do – and check with the
accessibility tools of the browser after a change.

## Pages and footer

Admins write **pages** – an imprint, FAQs, a privacy notice – under
*Administration → Pages* (`/admin/pages`). A page has a title, a machine name
that becomes its address, `/pages/<name>` (lower-case letters, digits, hyphens,
like a room's), and a body written in **CKEditor 5**: headings, paragraphs, bold
and italic, lists, links, quotes, tables, horizontal lines, code, plus a
source view. Every page is public under its address as soon as it is saved
and may link to any other page by that address – the footer is not what makes
a page reachable. Admins see an *Edit* button on the page itself.

The **footer** at the bottom of every screen links the pages the admins put
there, under *Administration → Footer* (`/admin/footer`). The page is built like a
room's song picker: two columns, left the pages the footer does not link,
right the footer in its order; an arrow moves a page across (→ in, ← out; a
page taken out keeps its address). On the right the order is changed by
dragging a row – the same drag & drop as on the wish list, saved in the
background – or with the four move buttons of every row (to the top, up,
down, to the bottom), which also work by keyboard and without JavaScript.
The page being read is highlighted among the links. With no page in the
footer and no `footer` value (below) there is no footer at all.

The body is stored as HTML, but not as it arrives: on save `src/Html.php`
reduces it to an allowed set of elements (`p`, `br`, `h2`–`h4`, `strong`,
`em`, `u`, `s`, `sub`, `sup`, `code`, `pre`, `hr`, `blockquote`, `ul`, `ol`,
`li`, `a`, `table` and its parts, `figure`, `figcaption`). Unknown elements
lose their tags and keep their text; scripts, styles, frames, forms and
pictures go with their content; attributes are dropped except `href` and
`target="_blank"` on links (which gets `rel="noopener"`), `start` on lists
and `colspan`/`rowspan` on cells. A link may point to a web, mail or phone
address, an anchor or a path of this site – `javascript:` and `data:` are
refused, `//host` as well. `h1` becomes `h2`, since the page's title is the
`h1`. What visitors get is exactly what is stored, printed unescaped inside
`<div class="prose">`. Pages live in the `pages` table, their texts per
language in `page_translations`; `footer_position` says whether and where a
page is linked in the footer (`NULL` = not linked).

CKEditor is bundled, not loaded from a CDN, so visitors' browsers talk to no
third party: `assets/vendor/ckeditor5/` holds the browser build
(`ckeditor5.umd.js`, about 1.9 MB, `ckeditor5.css`) and the German and French
interface translations; a language without a translation file gets the
English interface. Only the page form loads it. CKEditor 5 is licensed under
the GPL 2 or later (the editor is configured with the `GPL` licence key),
compatible with this project's GPL 3; the licence files sit next to it, the
README there says how to update it. The editor's colours follow the site's
theme through its custom properties (see the end of `style.css`).

### Pages in several languages

A page is its address plus a title and a body **per language of the
switcher** – every language equal, none the original. The form shows a row of
tabs, one per language (English, Deutsch, Français, … whatever `lang/*.po`
provides), from the very first save on; each tab holds a title and a content
field. A language left empty is simply not part of the page, a language with
only one of the two filled in is an error, and a page needs at least one
language. Tabs mark their state: a tick where the page is saved in that
language, *missing* where it is not yet, an alert where the fields need a
look. *Remove <language>* on a tab takes that language off the saved page at
once, after a confirmation – nothing else of the form is saved by it, and the
last language of a page cannot be removed. Without JavaScript all panels are
on the page, each headed by its language.

Readers get a page in the language chosen in the switcher. Where the page
lacks it, they get the first language of the **fallback order** the page has
– the order is set at the bottom of the Pages list (admins), by dragging a
row or with its arrow buttons, the same as the footer's order; a language
that arrives later (a new `.po` file) joins the end. The title and body then
carry `lang="…"` so screen readers and hyphenation know. The footer links, the
Pages list and the admins' messages pick the title the same way. On the
public page, an admin's *Edit* button opens the tab of the admin's interface
language – the one to fill in when the page fell back to another. The Pages
list shows a chip per language and page, filled where the page has it and
dashed where not, each leading to that tab.

`pages` holds the address and the footer position, `page_translations` one
row per page and language (`page_id`, `lang`, `title`, `body`). The fallback
order is `pages_languages` in the `settings` table, codes separated by
commas.

The operator's own line remains: `'footer'` in `config.php` (or `FOOTER_HTML`
from the environment) is printed below the page links – credits, an external
link. The value is HTML and goes out **unescaped**, so it is the operator's
own markup only, never anything a visitor typed.

```php
'footer' => '<p>Powered by <a href="https://example.org" rel="noopener">example.org</a></p>',
```

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

The application works with eleven fixed tables. Names and columns are a
prerequisite; there is no detection or mapping of foreign tables.

| Table | Columns | Purpose |
| --- | --- | --- |
| `songs` | `id`, `artist`, `title`, `length_sec` (seconds, `NULL` = unknown), `genre` | Repertoire |
| `song_wishes` | `id`, `song_id`, `artist`, `title`, `length_sec`, `genre`, `wisher`, `created_at`, `position`, `room_id` | Wish list, `wisher` = the guest's name if given, `room_id` 0 = main room |
| `song_suggestions` | `id`, `artist`, `title`, `suggester`, `created_at`, `room_id` | Open song suggestions, see [Song suggestions](#song-suggestions); `suggester` = the guest's name if given, `room_id` = the room it was made in (0 = main room) |
| `settings` | `name`, `value`, `updated_at` | Open/closed switch per room, marker of *Close all rooms*, daily secrets, personal settings |
| `wish_throttle` | `id`, `sender`, `created_at` | Rate limiting, see [Protecting the wishing](#protecting-the-wishing) |
| `users` | `id`, `username`, `password_hash`, `role_admin`, `role_moderator`, `role_editor`, `active`, `created_at`, `updated_at` | Staff accounts, see [Users and roles](#users-and-roles) |
| `rooms` | `id`, `slug`, `name`, `active`, `created_at`, `updated_at` | Rooms, see [Rooms](#rooms) |
| `room_songs` | `room_id`, `song_id` | A room's song selection from `songs` |
| `uploads` | `id`, `kind`, `mime`, `data`, `width`, `height`, `created_at` | The header logos, see [Logo](#logo) – in the database, so the deployment cannot lose them |
| `pages` | `id`, `slug`, `footer_position`, `created_at`, `updated_at` | The admins' pages: address and footer place, see [Pages and footer](#pages-and-footer); `footer_position` `NULL` = not linked in the footer |
| `page_translations` | `page_id`, `lang`, `title`, `body`, `updated_at` | A page's title and body per language, see [Pages in several languages](#pages-in-several-languages) |

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
an SQL error in the middle of operation – rename or recreate the table from
`sql/schema.sql`. There is no automatic migration of tables from earlier
versions.

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
decides, otherwise English. The choice also picks the language of the admins'
pages, see [Pages in several languages](#pages-in-several-languages).

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
table and managed on the **Users** page (`/admin/users`). Admins reach their
pages – *Users*, *Logos*, *Design*, *Limits*, *Pages*, *Footer* – through the
**Administration** menu in the navigation, a tab that opens a list; all of
them live below `/admin`.

| Role | May |
| --- | --- |
| **Admin** | Create, edit, lock and delete users and hand out every role, the admin role included; manage the header logos, the pages and the footer – and everything editors and moderators may do |
| **Editor** | Maintain the repertoire: add, edit, delete titles; work the song suggestions; create, edit, delete rooms and manage their songs |
| **Moderator** | Edit the wish list: sort, reorder, delete, clear; close and open the room (everyone may view the list) |

The three roles are plain flags in the `users` table (`role_admin`,
`role_editor`, `role_moderator`); a user without a role can sign in but only
sees the public repertoire. Admin is a role like the others: any number of
users can hold it, and every admin may give it to or take it from any user by
ticking the box in the user form – themselves included. Admin includes editor
and moderator: ticking *Admin* ticks the other two boxes and locks them (the
server derives the roles as well, JavaScript or not), and they are stored
along, so an admin who gives the role up keeps them. Editor and moderator
combine freely.

**Always one admin.** Nobody can lock or delete themselves, and the last
active admin cannot give up the role – so somebody can always manage users.
(Whoever is editing is an active admin, so the last one can only ever be
oneself.) The user form fixes those boxes and says why, the list has no
*Delete* for yourself, and saving checks again. An admin who gives up their
own admin role is sent to the pages their remaining roles open; another admin
can hand the role back.

**First admin.** As long as the table is empty, the application creates the
first admin from `auth.user` and `auth.hash` in `config.php` on the first
sign-in (or via `php tools/install.php`). After that only the table counts;
the values in `config.php` have no effect any more. While a signed-in user
still has the shipped default password, every page shows a red notice with a
link to *User settings*. This is checked at sign-in (or once for an existing
session) and remembered in the session; changing the password removes the
notice.

**User settings.** Every signed-in user has a personal page under *User settings* in
the account menu (top right). It shows the own username and roles with what
each role may do, holds the password change and the delete confirmations;
Roles and status cannot be touched on that page – admins assign them in the
user form.

**Own password.** Under *User settings*, for admins as well: the current
password once, the new one twice, same rules as in the user form (where an
admin can also set another user's password).

**Sessions.** The session holds only the user ID; the record is loaded on
every request. Changed roles apply immediately, a locked or deleted user is
signed out with the next click. Passwords are stored with `password_hash()`
(bcrypt), at least 8 characters; the sign-in verifies a hash even for an
unknown name so the response time does not reveal whether a name exists.

**Data minimisation.** Only username and password hash are stored – no
e-mail, no real name, no sign-in timestamps. Whoever names accounts after real
people processes personal data by doing so; role or function names (`dj1`,
`bar`) avoid that.

## Maintaining the repertoire

With the editor role (or the admin role) every row of the repertoire additionally
carries *Edit* and *Delete*, and *Add song* stands above the list.

**Entering the length.** The field accepts `3:45`, `1:02:03` or a plain number
of seconds (`225`); it is stored in seconds, left empty it becomes `NULL`.
Artist and title are required (up to 255 characters), genre is optional (up to
128 characters) with a suggestion list of the values already in use.

**Deleted songs and the wish list.** A wish stores a copy of artist, title,
length and genre. If a song is deleted from the repertoire, wishes already
received for it remain fully readable.

**Importing a CSV.** `tools/import-csv.php` reads a CSV with a header row
and adds its songs; the columns are found by name (title: *Songtitel*,
*Titel*, *Title*, *Song*; artist: *Künstler*, *Interpret*, *Artist*;
optional genre: *Attribute*, *Genre*, *Tags*; optional length: *Länge*,
*Length*, *Dauer*), comma or semicolon separated, UTF-8 with or without BOM.
Several genre values in one cell (`Oldie; PopSong`) are kept, joined with a
comma; a few spellings from the streamersonglist export are tidied on the
way (`PopSong` → `Pop`, `Rock Song` → `Rock`, `RocknRoll` → `Rock 'n' Roll`,
`X-Mas` → `Weihnachten`). Rows are validated like the song form, rows
already present (same artist and title) are skipped, and everything is
written in one transaction.

```bash
php tools/import-csv.php --dry-run songs.csv            # parse and report only
php tools/import-csv.php songs.csv                      # add the songs
php tools/import-csv.php --replace songs.csv            # delete every song first
php tools/import-csv.php --skip='KEIN SONG!' -  < songs.csv   # from stdin, skipping a placeholder title
docker compose exec -T web php tools/import-csv.php --replace - < songs.csv
```

`--replace` empties `songs` and `room_songs`: the rooms lose their song
selection and must be filled again under *Manage*; wishes keep their copies.

## Song suggestions

The **Suggestions** tab (light bulb, right of the wish list) is open to
everyone, signed in or not. Whoever misses a song enters artist and title;
the guest's name, if they gave one (see [The guest's name](#the-guests-name)),
travels with the suggestion so the editors know who asked. Everyone sees the
open suggestions below the form – oldest first, with the time received, the
name of whoever suggested and the room's tag – and can search them by
artist, title or name (several terms are combined with AND, like the song
search). Long lists are paged like the repertoire (`per_page` in
`config.php`). Only editors get the buttons.

Suggestions aim at the master list, which every room picks from, and there
is one list for the whole site. A suggestion made inside a room
(`/rooms/<name>/suggestions`, where the tab leads while one is in the room)
remembers that room: the editor sees it tagged with the room's name, and the
adopted song is offered in that room right away, not only in the master
list. The room switcher keeps one on the suggestions page when changing
rooms. If the room is deleted meanwhile, its suggestions stay and fall back
to the main room. While a room is closed (see [Rooms](#rooms)), suggesting
is closed there as well: the form gives way to a notice, and a late
submission is turned away.

Before a suggestion is stored, the application checks that the song is not
already on the repertoire (then the guest is told to simply wish for it) and
that it has not been suggested already – both compared case-insensitively.
The form carries the same bot hurdles as wishing (honeypot, signed
timestamp), a per-session cooldown (10 s) and a cap on open suggestions (200;
0 = no cap); admins set both under *Administration → Limits*, see
[Protecting the wishing](#protecting-the-wishing).

**Editors** (and admins) additionally get a counter badge on the tab and
two buttons on every row:

* **Adopt** opens the *Add song* form as *Adopt suggestion*: artist and
  title are filled in, the cursor waits in the length field for what is
  missing – length and genre. *Add* creates the song, puts it into the
  suggestion's room if there was one, places it on that room's wish list in
  the name of whoever suggested it (the suggestion was a wish, after all)
  and deletes the suggestion, all in one go; *Cancel* leaves everything as
  it was.
* **Delete** drops the suggestion. It asks for confirmation unless the
  editor switched that off under User settings; *Clear list* above the list
  deletes every suggestion and always asks.

## Rooms

A room is a capsule of repertoire and wish list with its own address, for
instance for two stages or two evenings. `/` and `/wishes` are technically the
**main room**: always there, without a record, with the whole repertoire.
Visitors see it under the name "General" ("Allgemein", "Général").

**Creating.** On the **Rooms** page (`/rooms`) editors create rooms. A room
has a display name (up to 128 characters, free) and a **machine name** for the
address: 2 to 64 characters, lowercase `a–z`, digits and single hyphens,
checked in `RoomRepository::validate()` against `SLUG_PATTERN`; uppercase is
converted to lowercase beforehand. A room `sommerfest-2026` is then reachable
at `/rooms/sommerfest-2026`, its wish list at `/rooms/sommerfest-2026/wishes`.
Changing the machine name changes the address – links already handed out stop
working.

**Managing songs.** A room's repertoire is a selection from the master list
(the repertoire of the main room), table `room_songs`. Under
`/rooms/<name>/manage` there are two columns: on the left the master list
without the songs already in the room, on the right the room's list. An arrow
to the right takes a song into the room, an arrow to the left removes it
again; a search field filters both columns, *Add all …* and *Remove all …*
move the whole search result. On narrow screens the columns stack. In the
room's repertoire the editor's delete button (the same bin) reads *Remove* –
the song only leaves the room. Songs are edited exclusively in the master list; a song
deleted there disappears from all rooms.

**Archiving.** Every room is *active* or *archived* (column `active`,
checkbox in the edit form). Archived rooms vanish from the room switcher and
from the list guests see under `/rooms`, but remain reachable through their
address. Archiving automatically closes the room; whoever reactivates it
opens it again in the room list or the header notice. Editors see every
room under `/rooms`, archived ones tagged, and filter by *All*, *Active* and
*Archived*; a search field finds rooms by display or machine name, the list
paginates like the repertoire. From seven rooms on, the room switcher's overlay
shows a filter field that hides entries as you type (JavaScript; without it
the full list stays).

**The main room's name.** The main room has no row and no address part of
its own, but it can be renamed: editors find *Rename* on its row under
*Rooms*. The name is kept in `settings` (`main_room_name`) and shows
wherever the main room is meant – header, room switcher, room list,
notices. An empty name restores the default, "General" in the visitor's
language.

**The start room.** Where a visitor without any remembered room lands when
opening the bare address (`/`, `/wishes`, `/suggestions`): the main room by
default, or the room an editor marked with *As start room* on its row under
*Rooms* (key `start_room` in `settings`, tagged *start room* in the list).
Marking the main room clears the setting. An archived start room receives no
visitors, a deleted one drops the setting. The start room only applies to a
first visit: once a room is remembered – the main room included, chosen
through the switcher or the list – that memory wins.

**The remembered room.** The room a visitor chose last is kept in a cookie
(`songwunsch_room`, one year, nothing but the room's machine name). Every
page inside a room writes it. Pages without a room in their address
(`/rooms`, `/users`, `/settings`, the forms) read it: header, room switcher
and the *Repertoire*, *Wish list* and *Suggestions* tabs stay in that room. A
room-bound address that names no room (`/`, `/wishes`, `/suggestions`, also
with query parameters) redirects into the remembered room – every time, so
a bookmark, a typed address or a return visit never drops the visitor out
of their room. The main room is chosen explicitly: its entry in the room
switcher and its name in the room list are small forms (action
`room_switch`) that remember the main room as such; only that, or an address
naming another room (`/rooms/<name>`, a QR code for instance), changes the
memory.
If the remembered room has been deleted, the cookie is dropped.

**Wish list per room.** Wishes carry `room_id` (0 = main room); order,
clearing and deleting act only within the respective room. Only what is in
the room can be wished for. Moderation applies to all rooms. When a room is
deleted, its song selection and its wishes go with it.

**Open and closed.** Every room, the main room included, is *open* or
*closed*. Moderators (and admins) switch it under *Rooms*: every row
carries *Close room* / *Open room* at the right, and a closed room is
tagged *closed* there. While a room is closed the repertoire stays visible,
but the audience can neither wish nor suggest a song there: the *Wish*
buttons disappear, the suggestion form gives way to a notice, and a notice
stands in the header of every page of the room – for moderators with an
*Open room* button right in it. Internally this is the former pause switch
(keys `wishes_paused` and `wishes_paused:<room id>` in `settings`).

**Closing all rooms.** Under `/rooms`, left of *Add room*, admins have the
switch *Close all rooms*. It closes the main room and every room, archived
ones included, and remembers each room's previous state while doing so (key
`wishes_paused_all` in `settings`). While that is in force the switch reads
*Lift the closing of all rooms*; it restores the remembered state: rooms that were open
reopen, rooms the moderator had closed themselves stay closed. Rooms created
in the meantime are left unchanged. Single rooms can be switched in the same
list at any time.

**Switching.** As soon as a room exists, the **room switcher** stands at the
far left of the navigation, apart from the page tabs: a button labelled *You
are here: <room>* that, like the language menu, opens an overlay with all
rooms (without JavaScript, `<details>`). On phones it takes a row of its own
above the tabs. The **Rooms** tab (`/rooms`) appears only for editors and admins;
the page itself, where every name leads into its room, remains reachable for
everyone.
Inside a room its name stands in the header, and all links of the application
stay within the room – generated by `url()` in `src/bootstrap.php`, which
prefixes room-bound pages with `/rooms/<name>`.

## The guest's name

A wish is more useful when the band knows who it is for. On the first visit
(no name cookie yet, not signed in, on the repertoire, wish list, suggestions
or rooms page) a dialog asks for the guest's name and says what happens with
it: it appears on the wish list next to every song they wish for, visible to
everyone in the room, and is kept in this browser for a year. The same name
goes with every song suggestion the guest makes. *Save name* stores it,
*Not now* (or Escape) closes the dialog for the rest of the session – a
returning guest is asked again. Wishing works either way; a wish without a
name simply shows none.

The name lives in a cookie (`songwunsch_name`, one year, `HttpOnly`, same
scope as the session cookie) and nowhere else until a wish is made; then it is
copied into the wish (`song_wishes.wisher`) and shown in the *From* column of
the wish list, for guests and moderators alike. Moderators can sort by it.

The account menu (person icon, top right) heads with the name the way it heads
with the username for signed-in users, and offers *Change name* – or *Set
name* when there is none yet. That leads to `/name`, the same form as a page;
an empty name removes the cookie. Signed-in users find the entry as *Wishing
as …* / *Name for wishes*: their wishes carry the cookie's name like anyone
else's.

Names are tidied on the way in (control characters dropped, whitespace
collapsed, at most 40 characters) and escaped on the way out like every other
value. The dialog is a `<dialog>`: with JavaScript it is modal over the
darkened page, without it the same element stands as a card at the top of the
panel – the forms work either way.

**Data protection.** The name is personal data the guest chooses to give. It
is stored only with the wish and deleted with it (*Delete*, *Clear list*,
deleting a room); nothing links it to an IP address or a session. The cookie
holds nothing but the name, is set at the guest's explicit request and can be
removed by the guest at any time. Nothing else about the guest is recorded.

## Protecting the wishing

Wishing is public and needs no sign-in – so it is the target for scripts that
flood the list. `src/WishGuard.php` puts several layers in front of it, all
without third-party services and without plain-text IPs:

| Layer | What happens | Where it is set |
| --- | --- | --- |
| Closed room | The moderator closes the room; the repertoire stays visible, the *Wish* buttons and the suggestion form disappear, a notice stands in the header of every page | *Close room* under Rooms |
| Global limits | At most N open wishes per room, at most M wishes per minute across all visitors | *Limits*: open wishes per room, wishes per minute (everyone) |
| Limit per sender | At most X per minute and Y per hour from the same address | *Limits*: wishes per minute / per hour per sender |
| Brake per session | Minimum gap between two wishes in the same browser | *Limits*: seconds between two wishes |
| Duplicates | A song that is already open on the list cannot be wished again – unless the switch allows it | *Limits*: allow duplicates |
| Bot trap | Invisible form field; if it is filled, the wish is silently discarded and the sender sees a success message | – |
| Minimum time | Signed timestamp in the form; submitting less than 2 s after the page load is rejected, the form expires after 6 h | `wish_min_form_sec` in `config.php` |

Admins set the limits under **Limits** (`/admin/wish-limits`, in the
*Administration* menu), for every room alike; the same page carries the two
limits on [song suggestions](#song-suggestions). A limit of `0` disables it.
The defaults are 200 open wishes per room, 30 per minute in total, 3 per
minute and 20 per hour per sender, 5 s between two wishes; 200 open
suggestions and 10 s between two of them. The values live in the `settings`
table (`limits.<name>`, `src/Limits.php`).

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
| Give or change your name | Asked on the first visit; later account menu (person icon) top right → *Change name* / *Set name*, or `/name`. An empty name removes it |
| Sign in / sign out | Account menu (person icon) top right next to the language menu; when signed in it shows the name and *Log out* |
| See the site as a guest | Signed in: account menu → *View as guest*; a notice in the header and *End guest view* lead back. Meanwhile pages, controls and actions behave exactly as for a visitor without a login |
| Sort | Sort bar above the list, a second click reverses the direction |
| Wish | *Wish* button in the row (or a click on the row) |
| Change the order | Wish list → drag the row (drag & drop) or the buttons on the right: to the top, ▲, ▼, to the bottom |
| Delete a wish | Wish list → *Delete* in the row |
| Delete everything | Wish list → *Clear list* |
| Close or open a room | Moderator: Rooms → *Close room* / *Open room* in the row (no wishes and no suggestions while closed); a closed room's header notice has *Open room* as well |
| Close all rooms | Admins: Rooms → *Close all rooms* / *Lift the closing of all rooms* |
| Suggest a song | Suggestions → artist and title → *Suggest* (everyone, also without a login) |
| Adopt a suggestion | Editor: Suggestions → *Adopt* in the row → add length and genre → *Add* |
| Delete a suggestion | Editor: Suggestions → *Delete* in the row; *Clear list* deletes all |
| Add a song | Editor: repertoire → *Add song* |
| Change a song | Editor: *Edit* in the row |
| Delete a song | Editor: *Delete* in the row (with confirmation) |
| Change room | Room switcher at the far left of the navigation, on phones above it (*You are here: <room>*, opens the list) or Rooms → click the room's name; the choice is remembered in a cookie, see [Rooms](#rooms) |
| Create a room | Editor: Rooms → *Add room*, then *Manage* |
| Manage a room's songs | Editor: *Manage* in the room or under Rooms |
| Create a user | Admins: Users → *Add user* |
| Make a user admin | Admins: Users → *Edit* → tick *Admin*; untick it to take the role away (the only active admin keeps it) |
| Switch off delete confirmations | Signed in: account menu → User settings → *Delete confirmations*, per account and separately for songs, suggestions, wishes and rooms, each only with the matching role (all on by default; *Clear list* always asks) |
| Change your own password | Signed in (admins included): account menu → User settings → *Change password* (current password plus the new one twice) |
| See your own roles | Signed in: account menu → User settings, box *Your account* |
| Put a logo in the header | Admins: *Administration → Logos* (`/admin/logos`): upload, *Switch live*, see [Logo](#logo) |
| Change the colours | Admins: *Administration → Design* (`/admin/theme`): pick or type a colour per area, *Default* brings the built-in one back, see [Colours](#colours) |
| Change the wish and suggestion limits | Admins: *Administration → Limits* (`/admin/wish-limits`): open wishes per room, per-minute and per-hour limits, seconds between two wishes or suggestions, duplicates, see [Protecting the wishing](#protecting-the-wishing) |

The wish list starts in manual order – initially this equals the order of
arrival, oldest on top. Sorting by a column is only a view; the stored order
is kept and reachable again via *#* or *Manual order*.

**Live updates.** The wish list and the suggestions keep themselves current
without a reload: every change – a wish coming in, deleted or moved, the list
cleared, the room closed or opened, a suggestion made, adopted or deleted –
raises a revision counter in the `settings` table (`wishes_rev`,
`wishes_rev:<room id>`, `suggestions_rev`; the counters wrap at a million,
only the difference matters). An open page polls `?poll=1` on its own
address every four seconds, a JSON answer of a few bytes, and only when the
revision moved on does it fetch the page again and swap its content in –
focus and scroll position stay, a drag in progress postpones the swap, hidden
tabs do not poll, errors back the interval off up to a minute. A WebSocket
would need a long-running server process, which shared hosting does not
offer; polling a counter costs a tiny request per open page and interval.
Without JavaScript the page is current after the next reload, as before.

Sorting, searching, wishing, reordering and deleting work without JavaScript –
the ▲/▼ switches are ordinary forms and at the same time the way for keyboard
and touch. `assets/app.js` adds drag & drop, row click, confirmations, the
`/` key and the live updates.

There is a single layout for all screen sizes: the compact card layout that
used to be the phone view only. There is no separate desktop table view any
more; on wide screens the shell is centred and limited to 1180 px. In the
header the word mark, the language menu and the account menu (person icon,
opens the guest's name with *Change name* and *Log in*, or for staff the
username, *Name for wishes*, *View as guest* and *Log out*, as a popout like
the language menu) share the first row, the navigation is right-aligned below. The popouts (language,
account, room switcher) are `<details>` and work without JavaScript; with
JavaScript they additionally close on a click outside or on Escape, and
opening one menu closes the others.

All lists share the same action pattern: a card's buttons stand at the right
in a vertical stack, each as wide as the widest. *Edit* and *Delete* share one
line of the stack as an icon pair (pencil / bin, 50 % each); their text remains
as a tooltip and for screen readers. The stack never stretches the text: every
card grid has a flexible empty row above and below its text lines, so a tall
stack only adds height there and the text stays a tight, centred block.

* **Repertoire** – title, below it artist · length · genre; *Wish* on the
  right, when signed in the pair *Edit* / *Delete* below it.
* **Rooms** – name, below it the address, below that the counts spelled out
  ("50 songs · 7 wishes"); the name is the link into the room. On the right,
  for moderators *Close room* / *Open room*, for editors *Manage* and the
  pair *Edit* / *Delete*; guests see no buttons.
* **Users** – name, below it roles · status; the pair *Edit* / *Delete* on the
  right (no *Delete* for yourself).
* **Suggestions** – title, below it the artist and, if made in a room, the
  room as a tag; on the right the time received above who suggested, next
  to it *Adopt* above *Delete*. On phones time and name move to a third
  line, like on the wish list.
* **Wish list** – position on the left, title, below it artist · length ·
  genre; on the right, right-aligned in one column, the time received (clock
  glyph and stamp) above who wished (person glyph and name, if given), and
  next to it the four move buttons (to the top, ▲, ▼, to the bottom) above
  *Delete* (bin, as wide as the button row). On phones the four become a
  2×2 block, "to the top" under ▲ and "to the bottom" under ▼, and
  the time received moves to a third line under the artist, the name right of
  it. The relative time ("5 min ago") is dropped; overlong names
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
src/SongRepository.php Repertoire: search, sort, paginate, maintain
src/WishRepository.php Wish list: create, read, sort, delete
src/SuggestionRepository.php  Song suggestions: validate, store, list, delete
src/WishGuard.php      Protection of wishing: limits, bot trap, pause
src/Limits.php         The limits on wishing and suggesting the admins set (Administration -> Limits)
src/Theme.php          The colours the admins set (Administration -> Design): shades, the :root block
src/GuestName.php      The guest's name for the wish list: cookie, tidying, first-visit question
src/RoomMemory.php     The room chosen last: cookie, once-per-session restore
src/Settings.php       Key/value store in the settings table
src/Uploads.php        The header logos: check, store, deliver (uploads table)
src/PageRepository.php Pages: validate, store, list; which are in the footer, in which order
src/Html.php           Reduce a page's HTML to the allowed elements and attributes
src/Security.php       Session, login against users, roles, CSRF, wish brake
src/UserRepository.php Users: create, edit, delete, count the active admins
src/RoomRepository.php Rooms: create, edit, delete, song selection from the master list
src/Format.php         Escaping and formatting (length, timestamps, numbers)
src/Translator.php     Discover languages, choose one, t()/tn()
src/PoFile.php         .po parser including the Plural-Forms interpreter
templates/             layout, home, wishes, suggestions, song, users, user, rooms, room, room_songs, login, settings, logos, theme, limits, pages, page_edit, page, footer, name, _name_form, error, _sortbar, _pager
assets/                style.css (dark interface), app.js, vendor/ckeditor5 (the page editor, see Pages and footer)
lang/                  songwunsch.pot (template), de.po (German), fr.po (French), further <code>.po
sql/                   schema.sql (all tables), demo.sql (test data)
tools/hash.php         Create a password hash
tools/install.php      Create the tables beforehand, set up the first admin (CLI)
tools/demo.php         Import the demo repertoire from sql/demo.sql (CLI)
tools/import-csv.php   Import songs from a CSV file, optionally replacing the list (CLI)
tools/extract-strings.php  Generate the translation template, check .po files (CLI)
tools/deploy.sh        Sync to the web host via rsync, see Deployment
compose.yml            Docker stack: web, db, optional traefik
sample.env             Template for .env
docker/                Dockerfile, php.ini, entrypoint, Traefik configuration
```

## Security and data protection

* All database values go through prepared statements; table and column names
  are fixed in the code, sort parameters pass a whitelist.
* Output consistently through `Format::e()` (`htmlspecialchars`). Two
  deliberate exceptions: the footer line from `config.php`, which is the
  operator's own HTML, and the body of a page, which admins write and which
  is reduced to text-structure elements and safe links before it is stored,
  see [Pages and footer](#pages-and-footer).
* Session cookie `HttpOnly` + `SameSite=Lax`, `Secure` as soon as HTTPS is
  active, `path` limited to the base path; `session_regenerate_id()` on
  sign-in. The two further cookies – the guest's name (`songwunsch_name`) and
  the room chosen last (`songwunsch_room`) – carry the same flags and hold
  nothing but that one value each.
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
* For every wish artist, title, length, genre, the timestamp and – if the
  guest gave one – their name are stored; **no** IP address, **no** user
  agent. The name is the only personal data on the wish list, it is given
  voluntarily and goes with the wish, see [The guest's name](#the-guests-name).
  A song suggestion stores artist, title, the timestamp and likewise the
  guest's name if given; it is deleted when the suggestion is adopted or
  dropped.
  The rate limiting keeps a daily changing pseudonym of the address for at
  most one hour, see [Protecting the wishing](#protecting-the-wishing).

## Accessibility

Semantic tables with `<caption>` and `scope`, `aria-sort` on the column
headers, meaningful names on all buttons, skip link, visible focus ring,
contrasts to WCAG 2.2 AA, fully operable by keyboard (including reordering via
the ▲/▼ switches), `prefers-reduced-motion` is respected.
A test with a screen reader and keyboard before going live is still
worthwhile.
