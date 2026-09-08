# Structure

The application has no framework, no dependencies and no build step.
`index.php` is the only entry point; the classes in `src/` are loaded by a
small autoloader (`src/bootstrap.php`). How the pieces fit together is
described in [Architecture](architecture.md); the list below names every
file and folder in the repository and what it does.

```
index.php              Front controller: load the configuration, build the container, hand the request to the kernel
.htaccess              Apache: everything to index.php, other files blocked (also in src/, templates/, tools/, sql/, lang/, config/; assets/ refuses to execute PHP)
config.example.php     Template for config.php (database, base path, first admin, version, trust_proxy, schema_ddl, show_errors)
README.md              Overview and entry to the documentation
.gitignore             What never goes into the repository (config.php, .env, the runtime signal)
docs/                  Topic documentation, one file per topic (this folder)
LICENSE                Licence of the project
robots.txt             What search engines may index: the start page and the pages, not the rooms, lists and operating pages

config/routes.php      Every address of the application and the controller behind it -- read by the matcher and the generator alike
config/access.php      Which role area an address needs; anything not listed is public
config/services.php    What the application is built from: a service id and the factory that makes it

src/Kernel.php         Request in, response out: session, route, listeners, controller
src/bootstrap.php      Autoloader, the container's global handle, and the view helpers (t/tn, url, asset, icon, base_path)
src/Http/Request.php   The incoming request, read once from the superglobals and then read-only
src/Http/Response.php  The answer: status, headers, body -- sent in one place
src/Http/Exception/    NotFoundException, AccessDeniedException
src/Routing/Route.php  One address: path, controller, name, room scope
src/Routing/RouteCollection.php  Every route, in the order they are declared
src/Routing/RouteMatcher.php     Which route a path belongs to (two passes, see Architecture)
src/Routing/RouteMatch.php       What the matcher found: the route and the values its path carried
src/Routing/UrlGenerator.php     The address of a route by name -- and the check on a return address
src/DependencyInjection/Container.php  Service ids, factories, and the objects they made; lazy
src/EventListener/     The steps between route and controller: room, live update, language, CSRF, access
src/Controller/        One controller per area; a method takes the request and returns a View or a Response
src/Template/Renderer.php     Renders a template into a response, in a scope of its own
src/Template/ShellContext.php Everything the page shell shows around a page
src/Template/View.php         What a controller hands back: template, title, values

src/Database.php       PDO connection, prepared statements only
src/Schema.php         Fixed table definition: creates missing tables (unless schema_ddl forbids it), checks their columns
src/SongRepository.php Repertoire: search, sort, paginate, maintain
src/WishRepository.php Wish list of a room: create, count repeated wishes, read, sort, reorder, delete
src/SuggestionRepository.php  Song suggestions of a room: validate, store, search, delete
src/WishGuard.php      Protection of wishing: limits, bot trap, signed form token, pause per room, revision counters
src/RoomServices.php   Builds the room-scoped services for a given room -- for the places that reach into another room
src/RoomContext.php    The room this request is in, worked out once by RoomListener
src/LiveTokens.php     The two tokens an open page polls with, and how often it asks
src/NumberSettings.php Whole-number settings under one prefix: defaults, ranges, validate, save (base of Limits and Ui)
src/Limits.php         The limits on wishing and suggesting and the page size the admins set (Administration -> Limits)
src/Ui.php             Message duration and live-update intervals the admins set (Administration -> Interface)
src/LiveSignal.php     The live update's doorbell: assets/state/live.txt, rewritten on every change, polled instead of PHP
src/Colors.php         The colours the admins set (Administration -> Interface): two sets of six, shades per scheme, the block over the stylesheet
src/GuestName.php      The guest's name for the wish list: cookie, tidying, first-visit question
src/Theme.php          Which scheme a page is drawn in: the visitor's cookie, the admins' default (ui.theme), the three values
src/QrCode.php         QR codes of the room addresses, made here: encoding, Reed-Solomon, masks, SVG and PNG
src/RoomMemory.php     The room chosen last and the unlisted rooms a guest entered: two cookies
src/Settings.php       Key/value store in the settings table, per-user settings
src/Uploads.php        The header logos: check, store, deliver (uploads table); one live per design, see Logo
src/PageRepository.php Pages in several languages: validate, store, list; fallback order of the languages; footer links and footer line
src/Html.php           Reduce a page's HTML to the allowed elements and attributes
src/Security.php       Session, login against users, roles, guest view, CSRF, per-session cooldown
src/UserRepository.php Users: validate, create, edit, delete, count the active admins, first admin
src/RoomRepository.php Rooms: create, edit, archive, delete, song selection from the main list, start room
src/Pagination.php     One page cut out of a list, and the fallback when the last page has gone
src/FlashBag.php       One message for the next page: the result of an action, or a notice that stays
src/FormMemory.php     What was typed and what was wrong with it, across the redirect
src/ErrorPresenter.php What a visitor is told about a failure
src/Format.php         Escaping and formatting (length, timestamps, numbers)
src/Translator.php     Discover languages, choose one, remember the choice, t()/tn()
src/PoFile.php         .po parser including the Plural-Forms interpreter

templates/             layout, home, wishes, suggestions, song, users, user, rooms, room, room_songs, room_qr, login, settings, logos, ui, limits, pages, page_edit, page, footer, languages, name, _name_form, _room_switches, error, _sortbar, _pager
assets/                style.css (the dark and the light palette), app.js, vendor/ckeditor5 (the page editor, see Pages and footer)
assets/state/          The only folder the application writes into: the live signal, and its .htaccess serves nothing else
lang/                  songwunsch.pot (template), de.po (German), fr.po (French), further <code>.po
sql/                   schema.sql (all tables), demo.sql (test data)
tools/hash.php         Create a password hash (CLI)
tools/install.php      Create the tables beforehand and set up the first admin (CLI)
tools/demo.php         Import the demo repertoire from sql/demo.sql (CLI)
tools/import-csv.php   Import songs from a CSV file, optionally replacing the list (CLI)
tools/check-routes.php Generate every address and match it back: the route table checked against itself (CLI)
tools/check-colors.php The three colour palettes in style.css against each other and against Colors (CLI)
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
see `.gitignore`) and `assets/state/live.txt`, which the application writes itself
at runtime (`src/LiveSignal.php`).
