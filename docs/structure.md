# Structure

The application has no framework and no build step. `index.php` is the only
entry point; the classes in `src/` are loaded by a small autoloader
(`src/bootstrap.php`). The list below names every file and folder in the
repository and what it does.

```
index.php              Front controller: routing, actions, post/redirect/get
.htaccess              Apache: everything to index.php, other files blocked (also in src/, templates/, tools/, sql/, lang/)
config.example.php     Template for config.php (database, base path, first admin, version, trust_proxy, show_errors)
README.md              Overview and entry to the documentation
docs/                  Topic documentation, one file per topic (this folder)
LICENSE                Licence of the project
robots.txt             What search engines may index: the start page and the pages, not the rooms, lists and operating pages
src/bootstrap.php      Autoloader and helpers (t/tn, base_path, url, asset, icon, redirect, flash, require_login/require_role, safe_target)
src/Database.php       PDO connection, prepared statements only
src/Schema.php         Fixed table definition: creates missing tables, checks columns, lists missing indexes
src/SongRepository.php Repertoire: search, sort, paginate, maintain
src/WishRepository.php Wish list of a room: create, count repeated wishes, read, sort, reorder, delete
src/SuggestionRepository.php  Song suggestions of a room: validate, store, search, delete
src/WishGuard.php      Protection of wishing: limits, bot trap, signed form token, pause per room, revision counter
src/NumberSettings.php Whole-number settings under one prefix: defaults, ranges, validate, save (base of Limits and Ui)
src/Limits.php         The limits on wishing and suggesting and the page size the admins set (Administration -> Limits)
src/Ui.php             Message duration and live-update intervals the admins set (Administration -> Interface)
src/Colors.php         The colours the admins set (Administration -> Interface): shades, the :root block
src/GuestName.php      The guest's name for the wish list: cookie, tidying, first-visit question
src/QrCode.php         QR codes of the room addresses, made here: encoding, Reed-Solomon, masks, SVG and PNG
src/RoomMemory.php     The room chosen last and the unlisted rooms a guest entered: two cookies
src/Settings.php       Key/value store in the settings table, per-user settings
src/Uploads.php        The header logos: check, store, deliver (uploads table)
src/PageRepository.php Pages in several languages: validate, store, list; fallback order of the languages; footer links and footer line
src/Html.php           Reduce a page's HTML to the allowed elements and attributes
src/Security.php       Session, login against users, roles, guest view, CSRF, per-session cooldown
src/UserRepository.php Users: validate, create, edit, delete, count the active admins, first admin
src/RoomRepository.php Rooms: create, edit, archive, delete, song selection from the main list, start room
src/Format.php         Escaping and formatting (length, timestamps, numbers)
src/Translator.php     Discover languages, choose one, remember the choice, t()/tn()
src/PoFile.php         .po parser including the Plural-Forms interpreter
templates/             layout, home, wishes, suggestions, song, users, user, rooms, room, room_songs, room_qr, login, settings, logos, ui, limits, pages, page_edit, page, footer, languages, name, _name_form, _room_switches, error, _sortbar, _pager
assets/                style.css (dark interface), app.js, vendor/ckeditor5 (the page editor, see Pages and footer)
lang/                  songwunsch.pot (template), de.po (German), fr.po (French), further <code>.po
sql/                   schema.sql (all tables), demo.sql (test data)
tools/hash.php         Create a password hash (CLI)
tools/install.php      Create the tables beforehand, add missing indexes, set up the first admin (CLI)
tools/demo.php         Import the demo repertoire from sql/demo.sql (CLI)
tools/import-csv.php   Import songs from a CSV file, optionally replacing the list (CLI)
tools/extract-strings.php  Generate the translation template, check .po files (CLI)
tools/deploy.sh        Sync to the web host via rsync and raise the version, see Deployment in installation.md
compose.yml            Docker stack: web, db, traefik (profile "standalone")
sample.env             Template for .env (Docker and deployment settings)
docker/                Dockerfile, php.ini, entrypoint.sh, traefik/ (traefik.yml, dynamic/tls.yml, certs/, make-cert.sh)
```

Templates whose name starts with `_` are partials included by other
templates. Every template is rendered inside `layout.php`, which draws the
header, the menus, the messages and the footer.

Files that are not in the repository: `config.php` and `.env` (credentials,
see `.gitignore`).
