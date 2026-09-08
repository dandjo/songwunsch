# Running with Docker

The stack needs one thing prepared once: a Docker network called `proxy`.
The reverse proxy reaches the application through this network. Then copy
the environment file and change the passwords in it.

```bash
docker network create proxy   # first time only
cp sample.env .env            # then change the passwords in .env
```

Both options below use the same Traefik labels on the `web` service. The only
question is which Traefik reads them.

## Option A – an existing Traefik

If a Traefik already runs on the machine and is attached to the `proxy`
network, this is enough:

```bash
docker compose up -d
```

That Traefik must use the Docker provider and must have two entry points
named `web` (HTTP) and `websecure` (HTTPS). The labels in `compose.yml` refer
to these names. The application is then reachable at
<https://songwunsch.localhost/> (or at the `DOMAIN_NAME` and `BASE_PATH` set
in the `.env`).

How that Traefik gets a locally trusted certificate for the domain depends
on its own setup. With the setup this project was developed against, it is one
command in the Traefik directory:

```bash
./add-domain-cert.sh songwunsch.localhost
```

## Option B – without an external Traefik

The `standalone` profile starts the project's own Traefik (`traefik:v3.7`):

```bash
./docker/traefik/make-cert.sh songwunsch.localhost   # optional, see below
docker compose --profile standalone up -d
```

This Traefik listens on ports 80 and 443 and redirects HTTP to HTTPS. If
those ports are taken, change `TRAEFIK_HTTP_PORT` and `TRAEFIK_HTTPS_PORT` in
the `.env`. Its dashboard is at <http://127.0.0.1:8081/dashboard/> (port
`TRAEFIK_DASHBOARD_PORT`, bound to localhost only).

`make-cert.sh` needs [mkcert](https://github.com/FiloSottile/mkcert). It
creates `docker/traefik/certs/<domain>.crt` and `.key` and writes
`docker/traefik/dynamic/tls.yml`, which points Traefik at them. The
certificate files are excluded from version control. Without them Traefik
uses its own self-signed certificate, and the browser shows a warning.

## What the stack contains

| Service | Content |
| --- | --- |
| `web` | PHP 8.3 with Apache (`php:8.3-apache`). The project folder is mounted into `/var/www/html`, so changes to the code take effect at once, without a rebuild. |
| `db` | MySQL 8 (`mysql:8`) with `utf8mb4` as server character set. Data lives in the volume `db_data`. |
| `traefik` | Only with the `standalone` profile, see Option B. |

**Container names.** The containers are called `<PROJECT_NAME>_web`,
`<PROJECT_NAME>_db` and `<PROJECT_NAME>_traefik`; the default is
`songwunsch`. The `web` service waits until the database answers its health
check before it starts.

**The one writable folder.** The application writes a single file at
runtime: the live update's signal, `assets/state/live.txt` (see *Live
updates* under [Usage](usage.md)). On the server that needs no permission at
all, because PHP runs as the account that owns the files. In the container
Apache runs as `www-data`, while the mounted project folder belongs to the
user on the host – so the image puts `www-data` into that user's group
(`docker/Dockerfile`), and the group may write the folder already. Nothing is
opened up for anyone else: only the host account's own group gains access,
and only inside the mount. The application still writes exactly one file –
`assets/state/.htaccess` refuses to serve anything but `live.txt`, which is
what keeps the target small.

The group comes from `HOST_GID` in the `.env`, and `1000` is right for a
first user account on Linux. If `id -g` on your host says something else, put
that number there and rebuild:

```bash
docker compose build web && docker compose up -d web
```

Without a writable folder nothing breaks – every poll then goes to PHP, which
is what happened before the signal file existed – but the local stack no
longer behaves like the server.

**The `web` image** (`docker/Dockerfile`) adds the PHP extensions
`pdo_mysql`, `opcache` and `gd` (with JPEG and WebP support, used to scale
uploaded logos and to draw PNG QR codes). It enables the Apache modules
`rewrite` and `headers` and sets `AllowOverride All`, so the bundled
`.htaccess` files work in the container just as on an ordinary host. See
[Web server](installation.md#web-server). `docker/php.ini` sets the
development defaults: errors go to the container log (`display_errors = Off`,
`log_errors = On`), uploads up to 20 MB, opcache with revalidation on every
request.

**`config.php`.** On the first start the entrypoint copies
`config.example.php` to `config.php` if there is none. The file stays on the
host (bind mount) and stays editable. It reads its values from environment
variables, and `compose.yml` passes those in from the `.env`: the database
credentials (`DB_HOST` is fixed to `db`, `DB_PORT` to `3306`), `AUTH_USER`,
`AUTH_HASH`, `BASE_PATH`, `SHOW_ERRORS`, `SCHEMA_DDL` and `TZ`. `TRUST_PROXY` is fixed to
`1`, because Traefik is the only way into the container and its
`X-Forwarded-For` (the sender of a wish) and its `X-Forwarded-Proto` (the
secure flag on the cookies) can both be trusted (see
[Protecting the wishing](wish-protection.md)). After a change to the `.env`,
run `docker compose up -d` again so the containers get the new values.

**Tables and demo data.** The tables are created by the application itself,
not by the stack – see [Database](database.md). Only on the very first start
of the database (empty volume) MySQL runs `sql/schema.sql` and then
`sql/demo.sql`, so the demo repertoire (50 songs) is there right away. Later
the demo data can be imported with `docker compose exec web php tools/demo.php`
(with `--force` to add it to a non-empty `songs` table).

**Database access from the host.** The database port is published on
`127.0.0.1:${DB_PORT_HOST}` (default `3399`) for tools such as DBeaver or
PhpStorm. It is not reachable from other machines.

## The `.env`

`sample.env` documents every variable. In short:

| Variable | Meaning |
| --- | --- |
| `PROJECT_NAME` | Prefix of the container, router and middleware names (`songwunsch`) |
| `TZ` | Time zone of both containers (`Europe/Vienna`) |
| `DOMAIN_NAME` | Host name the Traefik routers match (`songwunsch.localhost`) |
| `BASE_PATH` | Path below the domain, `/` for the root or e.g. `/songliste`; see [Base path](base-path.md) |
| `DB_NAME`, `DB_USER`, `DB_PASSWORD`, `DB_ROOT_PASSWORD` | Database name, application user and the two passwords |
| `DB_PORT_HOST` | Host port for database clients, on `127.0.0.1` only (`3399`) |
| `AUTH_USER`, `AUTH_HASH` | The first admin, see below |
| `HOST_GID` | Group of the account that owns the working copy (`id -g`), so PHP in the container may write `assets/state/` (`1000`) |
| `SHOW_ERRORS` | `0` (the default) shows technical error messages to signed-in users only, `1` to everyone |
| `SCHEMA_DDL` | `1` lets a request create a missing table, `0` requires `tools/install.php` and needs no CREATE rights |
| `TRAEFIK_HTTP_PORT`, `TRAEFIK_HTTPS_PORT`, `TRAEFIK_DASHBOARD_PORT` | Ports of the standalone Traefik (`80`, `443`, `8081`) |
| `DEPLOY_HOST`, `DEPLOY_DIR` | Only for `tools/deploy.sh`, see [Deployment](installation.md#deployment) |

The default `AUTH_HASH` belongs to the password `Administrator`. Change it
before the first use. To create a hash for a new password:

```bash
docker compose exec web php tools/hash.php 'MyPassword'
```

Put the result into the `.env` as `AUTH_HASH`, in single quotes, because the
hash contains `$` signs. These two values are only used while the `users`
table is empty; afterwards the accounts in the table count, see
[Users and roles](users-and-roles.md).

## Everyday commands

```bash
docker compose up -d                                   # start (with --profile standalone for Option B)
docker compose logs -f web                             # PHP and Apache log
docker compose exec web php tools/install.php          # create the tables now
docker compose exec web php tools/demo.php --force     # import the demo repertoire again
docker compose down                                    # stop, keep the data
docker compose down -v                                 # stop and delete the database volume
```

`docker compose down -v` deletes everything in the database: songs, wishes,
rooms, users, settings and logos. On the next start the tables and the demo
data are created again as on the first start.
