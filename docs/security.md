# Security and data protection

## Database

* Every value reaches the database through a prepared statement. PDO runs with
  `ATTR_EMULATE_PREPARES` off, so the server does the binding.
* Table and column names are fixed in the code (`src/Schema.php` and the
  repositories). Sort parameters from the address are looked up in a fixed
  list of allowed fields; anything else falls back to the default sort.
  Wildcards in search terms (`%`, `_`, `\`) are escaped before a `LIKE`.
* Changes to a single record address it by its id (and, for wishes and
  suggestions, by its room) and are limited to one row. Only actions that mean
  a whole set delete more than one row: clearing a wish list, clearing the
  suggestions, deleting a room (its wishes, suggestions and song selection go
  with it) and the `--replace` import.
* The connection error hides the credentials: the message carries only the
  error code.

## Output

* Output goes through `Format::e()` (`htmlspecialchars` with `ENT_QUOTES`).
  Three deliberate exceptions:
  * The body of a page and the footer line. Admins write them in the editor.
    On saving, `src/Html.php` reduces the HTML to text structure (headings,
    paragraphs, lists, links, tables, quotes, emphasis). Scripts, styles,
    frames and forms are dropped with their content. A link may only point to
    `http(s)://`, `mailto:`, `tel:`, an anchor or a path of this site; a link
    with `target="_blank"` gets `rel="noopener"`. See
    [Pages and footer](pages.md).
  * The colours admins set under *Interface*, printed as a `<style>` block.
    They are validated as `#rrggbb` before they are stored.
  * Translations that contain HTML placeholders (a link, `<strong>`). The HTML
    parts are escaped before they are inserted.
* Uploaded logos and QR images are delivered with
  `X-Content-Type-Options: nosniff` and a Content Security Policy of
  `default-src 'none'; style-src 'unsafe-inline'` – an SVG may draw, and style
  what it draws, and nothing else. A logo's type is detected from its content, not its
  file name. Raster logos are re-encoded as WebP; an SVG is stored as it is and
  only ever shown through `<img>`, where scripts do not run.

## Cookies and session

* The session cookie is named `songwunsch`. Its lifetime is the browser
  session. Flags: `HttpOnly`, `SameSite=Lax`, `Secure` as soon as HTTPS is
  active – the server's own `HTTPS`, or port 443, and `X-Forwarded-Proto` only
  where `trust_proxy` names a proxy –, `path`
  limited to the base path, so several applications on one domain do not share
  a session. Signing in calls `session_regenerate_id(true)`; signing out
  empties the session and deletes the cookie.
* The session holds the user's id, never the password. The user record is
  loaded on every request, so a locked or deleted user is signed out with the
  next click.
* The other cookies carry the same flags and are valid for one year:

  | Cookie | Content |
  | --- | --- |
  | `songwunsch_lang` | The chosen language code |
  | `songwunsch_name` | The name a guest gave for the wish list (at most 40 characters), see [The guest's name](guest-name.md) |
  | `songwunsch_room` | The machine name of the room chosen last, or `-` for the main room |
  | `songwunsch_rooms` | Up to five machine names of unlisted rooms a guest entered through their address |
  | `songwunsch_theme` | `system`, `light` or `dark`, the design the visitor chose, see [Interface](interface.md) |

  None of them holds anything but these values. None of them identifies a
  visitor, and none is set before the visitor does something that needs it:
  the scheme is written when the switch in the header is used, not on arrival.

## Forms and actions

* Every writing action is a POST form with a CSRF token. The token is 32
  random bytes, kept in the session and compared with `hash_equals()`. A POST
  without a valid token is answered with a notice and a redirect; a JSON call
  gets 403.
* Every operating address names the role it needs in `config/access.php`,
  which `AccessListener` enforces before the controller runs -- not only in
  the templates. A public GET page needs no entry; a POST route or an address under
  `/admin` does — see *Who may open an address* below. A missing
  sign-in leads to the login page, a missing role to the repertoire with a
  notice. JSON calls (drag & drop) receive 401 or 403 instead of a
  redirect. The roles are described in [Users and roles](users-and-roles.md).
* The return address in the `back` form field (or `?back=` parameter) is only
  accepted when it starts with the application's base path followed by `/`. A
  value beginning with `//` or `/\` (which browsers read as another host) or
  containing a line break is rejected. Nothing redirects to the outside.
* Form input that is kept for redisplay after a validation error is stored in
  the session without the password fields.

## Files and configuration

* Database credentials live only in the unversioned `config.php` (listed in
  `.gitignore`, together with `.env`). Every value can also come from an
  environment variable. User passwords exist only as hashes in `users`.
* Only `index.php`, the `assets/` folder and `robots.txt` are reachable from
  outside. The root `.htaccess` denies every file ending in `.php` (except
  `index.php`), `.sql`, `.po`, `.pot`, `.md`, `.ini`, `.log` and `.env`, and
  sends every other address to `index.php`, which answers 404 for what it does
  not know. `src/`, `config/`, `templates/`, `tools/`, `sql/` and `lang/` each carry an
  `.htaccess` with `Require all denied` as well. The command-line tools exit
  when they are called through the web. For nginx see
  [Web server](installation.md#web-server).
* `robots.txt` asks search engines to stay out of the rooms, the lists, the
  name and login pages, the settings and everything below `/admin`, `/users`
  and `/song`.
* Behind a reverse proxy, set `'trust_proxy' => true` only when the proxy is
  the only way in. The visitor's address is then taken from the last entry of
  `X-Forwarded-For`; otherwise senders could make up their address and bypass
  the per-sender wish limit. The same switch decides whether
  `X-Forwarded-Proto` may say that a request arrived over https – it is a
  header like any other, and it sets the `Secure` flag of the cookies and the
  scheme of the absolute addresses the application builds, so without a
  trusted proxy it is ignored and only the server's own `HTTPS` (or port 443)
  counts.

## Who may open an address

* `config/access.php` maps a route to a role area. For a **POST route and
  everything under `/admin`** an entry is required: a route of those classes
  that nobody classified is refused, not served. Where such an address really
  is meant for everyone – wishing, suggesting, the login, the theme switch –
  it says `AccessListener::OPEN`, so "public" and "forgotten" cannot look the
  same. `php tools/check-routes.php` fails on an unclassified one.
* Public GET pages need no entry; the repertoire and the wish list are open by
  design.

## The database account

* Schema changes belong to `tools/install.php` (or `sql/schema.sql`). Once the
  tables exist, set `'schema_ddl' => false` and give the account the site runs
  as `SELECT`, `INSERT`, `UPDATE` and `DELETE` only – no `CREATE`, no `ALTER`.
  A missing table is then reported instead of created.
* Uniqueness that carries meaning is a constraint and not only a check in the
  controller: one wish per song and room, one suggestion per song and room.
  Two requests arriving together therefore cannot produce two rows. The caps
  on open wishes and suggestions are applied by the write as well. The wish
  cooldown and the per-minute and per-sender limits stay best-effort: they
  dampen a flood, and buying exactness for them would mean a lock on the one
  path that has to stay quick.

## Errors

* `'show_errors' => false` is the default, and production should keep it.
  Technical details (table and column names, SQL messages, file paths) then
  reach signed-in users only; everyone else sees a generic sentence. The
  detail goes to the PHP error log in either case, so switching the option on
  buys nothing but exposure unless the installation is one nobody else can
  reach.

## Data stored about guests

* For every wish, artist, title, length, genre, the timestamp, how often the
  song was wished while it was open and – if the guest gave one – their name
  are stored. **No** IP address, **no** user agent. The name is the only
  personal data on the wish list. It is given voluntarily and goes with the
  wish; deleting the wish deletes the name. See
  [The guest's name](guest-name.md).
* A song suggestion stores artist, title, the timestamp and likewise the
  guest's name if given. It is deleted when the suggestion is adopted or
  dropped.
* The rate limiting keeps no plain IP address. It stores an HMAC of the address
  with a secret that changes daily. These entries live for one hour; the
  secrets of today and yesterday are kept, older ones are deleted. After a day
  nothing can be attributed to a person any more. See
  [Protecting the wishing](wish-protection.md).
* The per-session cooldown for wishing and suggesting stores only a timestamp
  in the session.
* QR codes are made on this server. No address is sent to a third party.
