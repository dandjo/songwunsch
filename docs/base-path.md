# Base path

The path the application lives under is set in one place: `BASE_PATH` in the
`.env` (or `base_path` in `config.php`; the value in `config.php` wins when it
is set). The default is `/`, the domain root.

```
BASE_PATH=/              ->  https://songwunsch.localhost/
BASE_PATH=/songliste     ->  https://songwunsch.localhost/songliste/
```

Write the value with a leading slash and without a trailing one. The
application tidies the value anyway: `/songliste/`, `songliste` and
`//songliste` all become `/songliste`; an empty value and `/` mean the domain
root. Only letters, digits, `/`, `_`, `.` and `-` are kept. Any other
character is dropped, so a typo never ends up in a redirect.

## Addresses

Below the base path these addresses exist. Anything else answers 404.

| Address | Page |
| --- | --- |
| `/` | Repertoire (start page) |
| `/wishes` | Wish list |
| `/suggestions` | Song suggestions of the room: form and searchable list for everyone, buttons for editors |
| `/login` | Sign-in |
| `/name` | The guest's name for the wish list – change or remove it |
| `/song/new`, `/song/<id>` | Create or edit a song – editors |
| `/suggestions/<id>/adopt` | Adopt a suggestion into a new song – editors |
| `/settings` | Redirects to the signed-in user's own settings |
| `/users/<id>/settings` | Personal settings of the signed-in user (own id only) |
| `/pages/<name>` | A page for everyone (imprint, FAQ, …), in the footer or not, see [Pages and footer](pages.md) |
| `/rooms` | List of rooms, each name leads into its room; moderators close and open rooms, editors create them here |
| `/rooms/new`, `/rooms/<id>/edit` | Create or edit a room – editors |
| `/rooms/main/edit` | Edit the main room (name, listed) – editors |
| `/rooms/<name>` | Repertoire of a room |
| `/rooms/<name>/wishes` | Wish list of a room |
| `/rooms/<name>/suggestions` | Song suggestions of a room – the adopted song joins the room |
| `/rooms/<name>/manage` | Manage the room's songs (selection from the main list) – editors |
| `/rooms/<name>/qr`, `/rooms/main/qr` | The room's address as a QR code – editors; `/rooms/main/qr` is the main room's code, see [Rooms](rooms.md) |
| `/rooms/<name>/qr.svg`, `/rooms/<name>/qr.png` | The same QR code as an image (`/rooms/main/qr.svg`, `.png` for the main room). PNG needs the `gd` extension; without it the page offers SVG only |
| `/logo/<id>` | An uploaded logo, see [Logo](logo.md) |
| `/admin` | Redirects to `/admin/users` |
| `/admin/users`, `/admin/users/new`, `/admin/users/<id>/edit` | User management – admins, see [Users and roles](users-and-roles.md) |
| `/admin/logos` | Header logos – admins, see [Logo](logo.md) |
| `/admin/ui` | The interface: colours, message duration, live updates – admins, see [Interface](interface.md) |
| `/admin/limits` | Limits on wishing and suggesting – admins, see [Protecting the wishing](wish-protection.md) |
| `/admin/pages`, `/admin/pages/new`, `/admin/pages/<id>/edit` | The admins' pages: list, create, edit – admins, see [Pages and footer](pages.md) |
| `/admin/footer` | Which pages the footer links, in which order – admins |
| `/admin/languages` | The fallback order of the languages – admins, see [Languages](languages.md) |

Pages marked *editors*, *moderators* or *admins* ask for a sign-in with that
role. Admins may open everything.

A room's or a page's `<name>` is its machine name: lower-case letters, digits
and single hyphens (`sommerfest-2026`). `new` and `main` are not available as
room names, because `/rooms/new` and `/rooms/main/…` are taken. A room's edit
form lives at `/rooms/<id>/edit`, so it cannot be mistaken for a room.

There is one exception to the 404 rule: `/rooms/<name>` with an unknown name.
A link to a room that has since been deleted or renamed leads to the start
page with a short notice. There the remembered room or the start room takes
over. An archived room is treated the same way for guests; signed-in users can
still open it. See [Rooms](rooms.md).

A trailing slash is ignored: `/wishes` and `/wishes/` are the same page.
Sorting, search and page number are query parameters
(`/wishes?sort=artist`, `/rooms?q=fest&page=2`). `?lang=<code>` switches the
language. Every page also answers `?poll=1` with two small JSON tokens for the
live updates, see [Interface](interface.md).

Lists and their records share a prefix: `/rooms` lists, `/rooms/new`
creates, `/rooms/<id>/edit` edits, and `/rooms/<name>` is the room itself.
Everything the *Administration* entry of the account menu leads to sits
below `/admin` – users, logos, interface, limits, pages, footer, languages –
while a page's public address stays `/pages/<name>`.

## What the value affects

The base path is put in front of every address the application generates:
links and form targets, the address of `assets/style.css` and
`assets/app.js`, the redirect after every action, the address `app.js` posts
to for drag & drop and the poll address for live updates. It also sets the
scope of the cookies: the session cookie, the guest's name, the remembered
room and the language cookie all carry `path=/songliste/` for a sub-path.
Two applications on the same domain therefore share no session.

Only absolute paths are generated (`/songliste/wishes?…`), never relative
ones.

The routing itself does not use the configured value. `Request::fromGlobals()` takes the
prefix from the script's own location (`SCRIPT_NAME`), so a request finds its
page in both modes below. A wrong `base_path` therefore does not break the
first request – but every link, redirect and cookie on the page is wrong.

The `BASE_PATH` environment variable is read even before `config.php` is
loaded, so the error page for a missing `config.php` links correctly, too.

## Two modes for a sub-path

Both modes use the same value:

* **The reverse proxy strips the prefix.** This is what the Docker stack
  does: the Traefik routers match on `Host(...) && PathPrefix(/songliste)`,
  the `stripprefix` middleware removes the prefix, and Apache in the
  container sees `/wishes`. The application puts the prefix back in front of
  every address it generates. See [Running with Docker](docker.md).
* **The files sit in a sub-folder** named `songliste` in the document root.
  Then the prefix reaches Apache as part of the path; nothing needs stripping.
  See [Installation without Docker](installation.md).

With a sub-path nothing redirects from the domain root.
`https://songwunsch.localhost/` answers 404 as long as nothing else is
mounted there; the root belongs to another application. If it should lead to
the repertoire, add a Traefik router on ``Path(`/`)`` with a `redirectregex`
middleware. That is deliberately not part of the stack.
