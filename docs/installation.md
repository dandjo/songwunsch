# Installation without Docker, web server and deployment

## Installation without Docker

**Requirements.** PHP 8.1 or newer (the Docker image uses 8.3) with the
extensions `pdo_mysql` and `mbstring`. The `gd` extension is optional: with it
uploaded logos are scaled down and QR codes are also offered as PNG; without
it logos are stored as uploaded and QR codes come as SVG only. A MySQL 8 (or
compatible) database. No Composer, no build step: the files run as they are.

1. Put the files into the web directory. Either directly into the document
   root (then the default `base_path` fits) or into a sub-folder such as
   `songliste` – then set `'base_path' => '/songliste'` in `config.php`. See
   [Base path](base-path.md). The bundled `.htaccess` files must come along,
   see [Web server](#web-server).

2. Create the configuration:

   ```bash
   cp config.example.php config.php
   ```

   Enter the database credentials (`db.host`, `db.port`, `db.name`,
   `db.user`, `db.pass`). Every value in `config.php` can also come from an
   environment variable (`DB_HOST`, `DB_NAME`, …; the names are in the file).
   `config.php` is excluded from version control through `.gitignore`.

   Two more values matter later: `trust_proxy` (set to `true` only behind a
   reverse proxy that is the only way in, see
   [Protecting the wishing](wish-protection.md)) and `show_errors`, which is
   `false` and should stay that way – technical error messages then reach
   signed-in users only, and the error log; `true` shows them to every
   visitor.

3. Define the first admin:

   ```bash
   php tools/hash.php 'MyPassword'
   ```

   The script prints a bcrypt hash and warns if the password has fewer than
   10 characters. Put the hash into `config.php` under `auth.hash` and the
   username under `auth.user`. The first admin account is created from these
   two values on the first sign-in (or by `tools/install.php`), and only
   while the `users` table is empty. Every further user is created by an
   admin inside the application; a later change of `auth.*` has no effect.
   The defaults are `Administrator` / `Administrator` – **change them before
   the first use.**

4. Create the database and its user. The database must exist; the tables are
   created by the application on the first request. To have them beforehand,
   run `php tools/install.php`. If the database user may not `CREATE TABLE`,
   import `sql/schema.sql` instead. See [Database](database.md).

5. For a first test without your own data, import the 50 demo titles:

   ```bash
   php tools/demo.php
   ```

   The script creates missing tables first and only fills an empty `songs`
   table; with `--force` it adds the titles regardless. If you prefer the
   MySQL client: `mysql <db> < sql/demo.sql`. Your own repertoire comes in
   through the application or through `tools/import-csv.php`, see
   [Maintaining the repertoire](repertoire.md).

## Web server

To the outside exactly one PHP file exists: `index.php`. Every address below
the base path lands there; unknown addresses are answered with 404. Only
`assets/` and `robots.txt` are served directly by the web server. All other
PHP files, `config.php`, `sql/`, `lang/`, `tools/`, `templates/`, `src/` and
the Markdown files (README, `docs/`) are blocked from outside (403).

**One writable folder.** `assets/state/` should be writable for the user the
web server runs PHP as – that folder alone, and it holds exactly one file:
`assets/state/live.txt`, which the application rewrites whenever anything
changes. Its own `.htaccess` there refuses to serve anything but that one
name, so the place the application writes into is as small a target as it can
be made; open pages ask the web server for
that file instead of asking PHP, which is what keeps the live updates cheap
(see *Live updates* under [Usage](usage.md)). On ordinary hosting, where PHP
runs as the account that owns the files, this is already the case and there
is nothing to do. If it is not writable, nothing breaks – every poll then
goes to PHP, as it did before – and no error is shown.

What matters is the folder, not the file: the signal is written to a
neighbouring name and moved into place, so one left behind by a command-line
tool run under another login is simply replaced.

**robots.txt.** Search engines may index the start page (the repertoire) and
the pages – imprint, privacy notice, FAQ. Everything that carries names is
disallowed: the rooms (`/rooms…`; a room is often named after the hosts of a
private event), the wish lists and suggestions (`/wishes`, `/suggestions`;
they show the names guests gave), the name form (`/name`) and the operating
pages behind the sign-in (`/login`, `/settings`, `/users`, `/admin`,
`/song`). This keeps well-behaved crawlers away; it is not access control.
The start page still shows the room switcher, so keep private rooms
*unlisted* (see [Rooms](rooms.md)). A `robots.txt` is always read from the
domain root, so the file only works with the application at the root. Under
a sub-path, merge its lines into the domain's own `robots.txt` with the
sub-path in front of every address.

**Apache** (2.4, the usual case on hosted servers): the `.htaccess` in the
application folder does both. It blocks every file ending in `.php`, `.sql`,
`.po`, `.pot`, `.md`, `.ini`, `.log` or `.env` and then allows `index.php`
again; and it rewrites every address except `assets/` and `robots.txt` to
`index.php`. In addition there are `.htaccess` files in `src/`,
`templates/`, `tools/`, `sql/` and `lang/` with `Require all denied`, so
these folders stay blocked even when `mod_rewrite` is missing. The vhost
must allow `.htaccess`: `AllowOverride All`, or at least `FileInfo` (for the
rewrite rules) and `AuthConfig` (for the `Require` blocks). A 500 right
after uploading usually means the vhost does not allow one of these
directives – the Apache error log names it. If only the start page works and
every other address gives Apache's own 404, `mod_rewrite` is missing or the
`.htaccess` is ignored.

**nginx** knows no `.htaccess`; the same rules belong in the `server` block.
The example assumes the application at the domain root and PHP-FPM through a
socket; for a sub-path put `/songliste` in front of every address:

```nginx
root /var/www/html;

location = /assets/state/live.txt {
    # The live update's signal, rewritten on every change: never from a
    # cache without asking, see "Live updates" under Usage.
    add_header Cache-Control "no-cache";
}

location ^~ /assets/ {
    expires 7d;
}

location = /robots.txt {
    # Served as it is, see "robots.txt" above.
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
unreachable by themselves. Behind nginx or another proxy that terminates TLS,
the application reads `X-Forwarded-Proto` to mark its cookies as secure.

In the Docker stack `mod_rewrite` and `AllowOverride All` are already
enabled; the same `.htaccess` files apply there.

## Version and cache

The application appends `'version'` from `config.php` (or `APP_VERSION`
from the environment) as `?v=…` to `assets/style.css` and `assets/app.js`.
Browsers and proxies therefore fetch the files anew after a deployment
instead of showing the cached copy. Raise the value with every release – it
may look like anything, `1.4.0` as well as `2026-09-03`. `config.example.php`
starts with `1.0.0`. Without a value the suffix is omitted.

## Deployment

`tools/deploy.sh` syncs the application folder to the host with `rsync` over
SSH. It copies everything needed to run and leaves out what belongs to
development only: `config.php`, `.env` and `sample.env`, `compose.yml` and
`docker/`, all git metadata (`.git/`, `.gitignore`, `.gitattributes`,
`.gitmodules`, `.gitkeep`, `.github/`), `.idea/`, `*.log`, `.DS_Store` and
the script itself. It copies from the working folder, not from git: a file
that git ignores is copied too unless it is on this list.

Target host and directory are read from the `.env`: `DEPLOY_HOST` (SSH host
or alias from `~/.ssh/config`, required) and `DEPLOY_DIR` (relative to the
SSH login's home, default `public_html`), see `sample.env`. The `.env` is
read, not executed. Both values can be overridden as environment variables,
e.g. `DEPLOY_HOST=other-host tools/deploy.sh -n`. Without `DEPLOY_HOST` the
script stops with a message (exit code 2). `rsync` must be installed
locally.

```bash
tools/deploy.sh -n         # dry run: shows what would change, including the version
tools/deploy.sh --dry-run  # the same
tools/deploy.sh            # the real thing
tools/deploy.sh --no-bump  # without raising the version
```

The sync runs with `--delete`: files that no longer exist locally disappear
on the server as well. `config.php` is exempt – it is neither transferred
nor deleted – and so is `assets/state/live.txt`, which the application writes
on the server itself (see *Live updates* under [Usage](usage.md)). On a fresh host create it once from `config.example.php`, which
is deployed; the message at the end of the script reminds you. Permissions
are set to `755` for folders and `644` for files; some hosters reject
group-writable files. Owner and group are not transferred. The files sit
directly in `public_html`, which matches the default `'base_path' => '/'`.

After the sync the script raises `'version'` in `config.php` **on the
server**, see [Version and cache](#version-and-cache). `1.0.4` becomes
`1.0.5`. Anything that is not purely numeric becomes today's date with a
counter: `2026-09-03.1`, and a second deployment on the same day
`2026-09-03.2`. The dry run shows the change without writing it. `--no-bump`
leaves the version untouched. If the server's `config.php` has no `version`
entry, the script says so and does nothing else.
