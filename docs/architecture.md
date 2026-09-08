# Architecture

Four files decide what happens on a request, and nothing outside them does:

| File | Answers |
|---|---|
| `config/routes.php` | every address, and the controller behind it |
| `config/access.php` | which role area an address needs |
| `config/services.php` | what the application is built from |
| `src/Kernel.php` | the order the pieces run in |

There is no framework and no dependency: the router, the container and the
response object are about 700 lines of hand-written PHP in `src/Routing`,
`src/DependencyInjection` and `src/Http`. What is borrowed from Symfony is
the shape, not the code.

## The request

`index.php` loads `config.php`, builds the container and hands the request
to the kernel. The kernel does four things, in this order:

1. **Start the session.** Its cookie is scoped to the base path, so several
   applications on one domain do not share a session.
2. **Match the route** (`RouteMatcher`). The route's own values -- an id, a
   machine name, the room -- go onto the request as attributes, together
   with the route's name and its page key.
3. **Walk the listeners**, in the fixed order below. Any of them may answer
   the request and end it there.
4. **Call the controller.** It is handed the request and returns a `View`
   (which the renderer turns into a page) or a `Response`.

A controller never writes a header, never echoes and never calls `exit`.
`Response::send()` in `index.php` is the only place that writes output --
apart from `render_fatal()`, for a request that fails before there is a
configuration to answer with.

### The listeners

| Listener | Does |
|---|---|
| `RoomListener` | works out which room this request is in |
| `LiveUpdateListener` | answers `?poll=1` and stops here |
| `LanguageListener` | `?lang=<code>`: remember it, drop it from the address |
| `CsrfListener` | every POST carries the session's token |
| `AccessListener` | who may open this address |

The order is the design. The room comes first because everything is
room-scoped. The poll comes second because a poll is not a page: it builds
no view, takes no message off the session and -- the container being lazy --
never constructs the repositories, the page shell or a parsed translation
catalogue. Measured locally, a poll costs about half of what the same page
costs in PHP; the far bigger saving is still the static doorbell described
under *Live updates* below, which most polls never get past.

## Routing

`config/routes.php` is the single source of truth for **both** directions:
`RouteMatcher` reads it to find the controller behind a path, `UrlGenerator`
reads it to build the address of a route by name. Nothing in the application
writes a path by hand -- a template says `url('room_edit', ['id' => 7])` and
a controller `$this->url(...)`, the way a Symfony template says `path()`.

Two directions out of one declaration is the point: they used to be two
hand-kept copies of the same table, a `preg_match` chain for one and an
`if/elseif` chain in `url()` for the other, with nothing checking them
against each other. `tools/check-routes.php` now does: it generates every
address and matches it back, and the result must be the same route with the
same values.

```bash
docker compose exec -T web php tools/check-routes.php          # the round trip
docker compose exec -T web php tools/check-routes.php --list   # every address
```

### Addresses

Every GET address is the one the application has always had, so bookmarks,
printed QR codes and search engines keep working. Every mutation is a POST
route with an address of its own -- `/wishes/add`, `/wishes/12/delete`,
`/rooms/7/pause`. Where the action concerns one record, its id is part of
the path; a form that serves both "new" and "edit" posts to a `/save`
address and names the record in its body.

### Rooms, and the two passes

The main room has id 0, lives at the base path and has no machine name of
its own; every other room lives below `/rooms/<slug>`. A route declared
`roomScoped()` therefore exists twice, and both forms come from that one
declaration. `roomOnly()` is for a page that exists only inside a real room
(a room's song selection); it can name an address for the main room where
the bare path is not available, as the QR code does with `/rooms/main/qr`.

The matcher tries every route at its own address first and the room-prefixed
forms second. That is what makes `/rooms/new` the form for a new room rather
than a room called "new", and it is safe rather than lucky because both
`new` and `main` are reserved names (`RoomRepository::RESERVED_SLUGS`).

The matcher only matches: it asks no database, writes no cookie and
redirects nowhere. Which room the request is really in, whether that room is
archived, and where a visitor with no remembered room should land is
`RoomListener`'s business -- see [Rooms](rooms.md) for the rules.

### Page keys

Several routes share a screen: the song form is `song` whether it is reached
as `/song/new` or as `/suggestions/7/adopt`. A route's **page key** is what
the navigation, the templates and the live-update tokens know that screen
by; its **name** is what the URL generator knows it by. `Route::page()`
declares one where it differs from the other.

## Access

`config/access.php` maps a route name to the role area it needs, or to
`AccessListener::ANY` for "any signed-in user". A route that is not in the
table is public. This replaced 44 `require_role()` calls in the first line
of 44 branches, which meant that whether an address was protected could
only be answered by reading all of them; `check-routes.php` also verifies
that every name in the table is a route, so a typo cannot silently leave an
address open. See [Users and roles](users-and-roles.md).

## Services

`config/services.php` names every service and what it is built from. There
is no autowiring: at this size an explicit list is shorter to read than the
reflection that would work it out, it needs no cache directory on a host
that may not have a writable one, and it puts every dependency of every
class on one screen.

`Settings` reads its table in one query on first access and answers from
memory afterwards; a write empties that again, so a value that was just
saved is never served stale. It is a small table by construction -- a live
installation has some 40 rows -- and the values are read from all over while
a page is assembled, which used to cost eleven queries against it. Measured
against the general log, one page is now nine to twelve queries and a
live-update poll is two.

Everything is lazy. The database connection is opened on first use, the
tables are checked once per request by whoever needs them, and a service
nobody asks for is never made. The room-scoped services -- the wish list,
the suggestions, the wish protection -- are built from `RoomContext`, so
they may not be asked for before `RoomListener` has run. The few places
that must reach into *another* room (closing a room from the room list,
archiving one, adopting a suggestion whose song joins the wish list of the
room it was suggested in) go through `RoomServices`.

## Controllers and views

One controller per area, in `src/Controller`. A method takes the `Request`
and returns a `View` -- template, title, values -- or a `Response`. The base
class provides the small vocabulary every controller needs: `url()`,
`redirect()`, `back()`, `destination()`, `view()`, `json()`, `flash()`,
`notice()`. Its `Support` object carries the five things all of them use
(the request, the URL generator, the flash bag, the form memory, and who is
asking), so a controller's own constructor lists only its repositories.

Everything the page shell shows around a page -- the header with its tab
counters, the room switcher, the closed-room notice, the logo, the colours,
the footer, the pop-up message, the first-visit name question, the live
address -- is added by `ShellContext`, so no controller repeats any of it. A
value a view provides wins over the shell's: that is how the wish list
reports the count it has just read instead of making the shell ask again.

`Renderer::capture()` includes a template in a method whose only local
variables are `$__file` and `$__vars`. The front controller used to
`extract()` its view array in its own scope, where a local with the same
name as a view value quietly won over it -- a trap that had bitten the
footer and the room-songs page more than once. There is no scope left to
collide with.

## Live updates

Unchanged in substance, and described in full under
[Interface](interface.md): there is no push. Every open page carries two
tokens and asks whether they moved on -- the head token for the header, the
content token for a list, and only a list has one. Both are built in
`LiveTokens`, which is also what the poll listener answers from. Before
asking PHP at all, `app.js` fetches `assets/state/live.txt`, a static file the
web server answers with a 304 when nothing changed.

What to keep in mind when adding a feature:

* If a change should reach open pages, it must raise a revision counter --
  `catalog_rev` (rooms and songs), `wishes_rev[:id]` + `wishes_all_rev`
  (`WishGuard::touch()`), `suggestions_rev`, or `ui_rev` (anything the page
  shell shows). Most of the admin surface deliberately raises none.
* Put a value in the **head** token only if the header shows it, and in a
  **content** token only if that list shows it. Putting shell values into a
  content token throws away half-typed input on every save.
* `assets/state/live.txt` is world-readable: it must stay an opaque value that
  says *something changed*, never what, where or when.

## Verification

There is no test suite. What there is:

```bash
docker compose exec -T web php -l <file>                          # syntax
docker compose exec -T web php tools/check-routes.php             # the route table against itself
docker compose exec -T web php tools/check-colors.php             # the three colour palettes against each other
docker compose exec -T web php tools/extract-strings.php --check  # 0 missing for de and fr
```

and driving the running app. Requests must go **through** Traefik, not
straight at the container -- the session cookie is scoped to the base path
and the token check fails otherwise:

```bash
curl -sk --resolve songwunsch.localhost:443:127.0.0.1 -c jar -b jar https://songwunsch.localhost/
```
