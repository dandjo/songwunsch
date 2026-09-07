# Songwunsch

**Let the audience pick the songs.** Songwunsch is a wish list for live bands,
DJs and party hosts. Guests open the page on their phone, browse what you can
play and tap a song. It lands on your list in the order you will play it. No
app to install, no account for the guests, no third-party service in between.

<p align="center">
  <img src="docs/demo.gif" width="500" alt="A guest on a phone gives their name, browses the repertoire, the wish list and the suggestions, opens the language and account menus and switches to another room.">
</p>

## Why bands and DJs use it

* **A QR code is the whole setup.** Print it on a table card or show it on a
  slide. Guests scan it and are in the room.
* **The repertoire is the menu.** Title, artist, length and genre as cards, a
  search field and a sort bar. One tap makes a wish. A song wished twice is
  counted, not listed twice.
* **The wish list is your set list.** Drag wishes into the order you want,
  delete what you will not play, or clear the list between sets. Guests see
  the same list live, with their name next to their wish.
* **Missing a song? Suggest it.** Guests name artist and title. You adopt a
  suggestion into the repertoire and onto the list in one step, or drop it.
* **Rooms for stages and evenings.** Every room has its own song selection,
  wish list, suggestions and address. Close a room during the break, open it
  again with one click, or close all rooms at once.
* **Your look, your languages.** Your logo in the header, your colours, your
  imprint and FAQ pages. English, German and French are included, another
  language is one text file away. Guests get their browser's language.
* **Built for the stage.** A dark interface that does not blind anyone, large
  touch targets, one layout from a 360 px phone to a wide screen, fully usable
  by keyboard and screen reader.
* **Runs anywhere PHP runs.** No framework, no Composer, no build step, no
  CDN. Copy the files to any hosting with PHP 8.1 and MySQL, or start the
  Docker stack.
* **Respects your guests.** No accounts, no trackers, no IP addresses on
  record: the rate limiting works with a pseudonym that changes daily. The
  only personal data is the name a guest chooses to give, and it goes when
  the wish goes.

## Quick start

```bash
docker network create proxy   # first time only
cp sample.env .env            # set the passwords
docker compose --profile standalone up -d
```

Open <https://songwunsch.localhost/>. The stack ships a demo repertoire of
50 songs, so the first wish is a click away. Sign in with the admin account
from your `.env` to manage the list. Details, and the way without Docker, are
in [Running with Docker](docs/docker.md) and
[Installation without Docker](docs/installation.md).

## Documentation

**Setting up**

* [Running with Docker](docs/docker.md) – the stack, with or without your own Traefik
* [Installation without Docker](docs/installation.md) – any web host, Apache and nginx, cache busting, deployment by rsync
* [Base path and addresses](docs/base-path.md) – domain root or sub-path, every address the application answers
* [Database](docs/database.md) – the tables, how they are created, what to run after an update
* [Architecture](docs/architecture.md) – routing, services, the request's way through
* [Structure](docs/structure.md) – what each file and folder does

**Running the show**

* [Usage](docs/usage.md) – the everyday tasks, live updates, how the pages are laid out
* [Rooms](docs/rooms.md) – rooms, QR codes, open and closed, the remembered room
* [Maintaining the repertoire](docs/repertoire.md) – adding songs, importing a CSV
* [Song suggestions](docs/suggestions.md) – how guests suggest and editors adopt
* [The guest's name](docs/guest-name.md) – the name dialog and where the name goes
* [Users and roles](docs/users-and-roles.md) – admin, editor, moderator

**Making it yours**

* [Logo](docs/logo.md) – your logo in the header
* [Interface](docs/interface.md) – colours, message duration, live-update intervals
* [Pages and footer](docs/pages.md) – imprint, FAQ, privacy notice, in several languages
* [Languages](docs/languages.md) – choosing, adding and translating a language

**Behind the scenes**

* [Protecting the wishing](docs/wish-protection.md) – limits, bot traps and rate limiting without storing IPs
* [Security and data protection](docs/security.md)
* [Accessibility](docs/accessibility.md)

## Licence

Songwunsch is free software under the GNU General Public License, version 3.
The bundled CKEditor 5 is licensed under the GPL 2 or later. See
[LICENSE](LICENSE).
