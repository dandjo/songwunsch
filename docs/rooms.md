# Rooms

A room is a bundle of repertoire and wish list with its own address. Use one
room per stage or per evening, for example. `/` and `/wishes` are the **main
room**: it is always there, it has no database row, and it offers the whole
repertoire. Visitors see it under the name "General" ("Allgemein" in German,
"Général" in French).

## Creating a room

Editors create rooms on the **Rooms** page (`/rooms`). A room has two names:

- A display name of up to 128 characters, free text.
- A **machine name** for the address: 2 to 64 characters, lower-case `a–z`,
  digits and single hyphens. `RoomRepository::validate()` checks it against
  `SLUG_PATTERN`; upper-case letters are turned into lower-case first. `new`
  and `main` are reserved, because `/rooms/new` and `/rooms/main/edit` are
  addresses of their own.

While the machine name field is empty, the browser proposes one from the
display name (`app.js`): lower-case, German umlauts spelled out (ä becomes
ae), accents dropped, everything else becomes a hyphen. A machine name that
is already in the field, typed or saved, is left alone. Empty the field and
it follows the display name again. The address preview under the field
follows what is typed.

A room `sommerfest-2026` is reachable at `/rooms/sommerfest-2026`, its wish
list at `/rooms/sommerfest-2026/wishes`, its suggestions at
`/rooms/sommerfest-2026/suggestions`. Changing the machine name changes the
address. Links already handed out then stop working.

A new room starts active, unlisted and empty. After *Create* the editor lands
on *Manage* to pick the room's songs.

## Managing a room's songs

A room's repertoire is a selection from the main list, the repertoire of the
main room (table `room_songs`). Songs are edited in the main list only. A
song deleted from the main list disappears from every room.

*Manage* (`/rooms/<name>/manage`) shows two columns. On the left is the main
list without the songs already in the room. On the right is the room's list.
An arrow to the right takes a song into the room. An arrow to the left takes
it out again. One search field filters both columns. *Add all …* and *Remove
all …* move the whole search result; *Remove all* without a search asks for
confirmation. Each column is paged on its own (`page` for the main list,
`rpage` for the room; *Rows per page* under *Administration → Limits*). A
move comes back to the same pages. On screens up to 720 px wide the columns
stack.

In a room's repertoire the editor's bin button reads *Remove* instead of
*Delete*. It only takes the song out of the room; the main list keeps it.

## Archiving

Every room is *active* or *archived* (column `active`, checkbox *Active* in
the edit form). Archived rooms leave the room switcher and the room list.
Only signed-in users can still open them through their address. A guest who
follows the address, the QR code or the remembered room lands on the start
page with a short notice.

A signed-in user standing inside an archived room still sees it in the room
switcher, tagged *archived* and marked as the current room. So the way to
every other room stays open. The header shows the *archived* tag to editors
as well.

Archiving closes the room (see *Open and closed* below). Reactivating does
not open it again; a moderator does that in the room list or in the header
notice.

Editors see every room under `/rooms`, archived ones tagged, and filter by
*All*, *Active* and *Archived*. Other signed-in users see the active rooms.
A search field finds rooms by display name or machine name. The list is
paged like the repertoire.

## Listed and unlisted

Every room is also *listed* or *unlisted* (column `listed`, checkbox
*Listed* in the edit form). Guests, that is visitors who are not signed in,
see only listed rooms in the room switcher and under `/rooms`. An unlisted
room is reached through its address or QR code alone. Signed-in users see
every active room.

A new room starts unlisted. This is the right setting for private events: a
room is often named after the hosts. `robots.txt` keeps crawlers away from
everything under `/rooms`, but the room switcher on the start page names
every listed room. The main room can be unlisted as well (below).

## QR code

Every room's address is available as a QR code for table cards, posters or a
slide. Editors find *QR code* on the room's row under *Rooms* and on its edit
form. It leads to `/rooms/<name>/qr`; the main room's code is at
`/rooms/main/qr`. The page shows the code with the address beneath it, a
print button (JavaScript only; in print only the code and the address
remain) and downloads as SVG (`/rooms/<name>/qr.svg`) and PNG (`.png`). The files are
named `songwunsch-<name>.svg` and `.png`, `songwunsch-main` for the main
room. PNG needs the `gd` extension; without it the page offers SVG alone.
*Back* returns to where one came from, the list or the edit form.

The code is made by `src/QrCode.php` on this server, so the address is
passed to no third-party service. Byte mode, error correction level M,
versions 1 to 10 (up to 213 bytes). The address carries the request's
scheme and host.

Looking at a QR code does not enter the room: the remembered room stays as
it was. The room switcher on the QR page opens the QR page of the room
chosen.

## The main room's name and listing

The main room has no row and no address part of its own, but it can be
renamed and unlisted. Editors find *Edit* on its row under *Rooms*
(`/rooms/main/edit`). The name is kept in `settings` under `main_room_name`
and shows wherever the main room is meant: header, room switcher, room list,
notices. An empty name restores the default, "General" in the visitor's
language. The name may be up to 128 characters long.

*Listed* works as for any other room: unlisted, guests see the main room
neither in the room switcher nor in the list of rooms and reach it through
the root address only -- for an event where every party has a room of its
own. A guest standing in the unlisted main room still sees it in the
switcher, as the place they are at. The switch is kept in `settings` under
`main_room_listed` (`0`; absent means listed). The main room cannot be
archived.

## The start room

The start room is where a visitor without any remembered room lands when
opening a bare address (`/`, `/wishes`, `/suggestions`). By default that is
the main room. Editors can mark another room with *As start room*, on its
row under *Rooms* or on its edit form. The room's id is kept in `settings`
under `start_room`, and the list tags the room *start room*.

Marking the main room clears the setting. An archived room cannot become
the start room, and an archived start room receives no visitors: they stay
in the main room. Deleting the start room drops the setting.

The start room only applies to a first visit. Once a room is remembered,
the main room included, that memory wins.

## The remembered room

The room a visitor chose last is kept in a cookie: `songwunsch_room`, one
year, containing nothing but the room's machine name (or a dash for the
main room). Every page inside a room writes it.

Pages without a room in their address (`/rooms`, `/admin/users`, the forms,
`/settings`) read it. Header, room switcher and the *Repertoire*, *Wishes*
and *Suggestions* tabs stay in that room.

A room-bound address that names no room (`/`, `/wishes`, `/suggestions`,
also with query parameters) redirects into the remembered room. This
happens every time, so a bookmark, a typed address or a return visit never
drops the visitor out of their room.

The main room is chosen on purpose: its entry in the room switcher and its
name in the room list are small forms (action `room_switch`) that remember
the main room as such. Only that, or an address naming another room (for
example from a QR code), changes the memory.

If the remembered room has been deleted, the cookie is dropped. For a guest
the same happens when the remembered room has been archived.

## Your rooms

Guests are offered listed rooms only. A guest who entered an unlisted room
through its address or QR code and then switched rooms would have no way
back except that link. So the unlisted rooms a guest has entered are kept in
a second cookie: `songwunsch_rooms`, one year, containing nothing but up to
five machine names, the most recent first. The room switcher shows them
below the offered rooms under *Your rooms*.

A room that is deleted, archived or listed in the meantime leaves the
cookie on the next page view. Signed-in users see every active room anyway
and get no such group.

## Wishes and suggestions per room

Wishes carry `room_id` (0 for the main room). Ordering, clearing and
deleting act only within the room. Only songs that are in the room can be
wished for there. The suggestions belong to the room as well: a suggestion
made in a room is listed there, and an adopted song joins that room.

Moderators and editors act in every room; the roles are not bound to a room.

When a room is deleted, its song selection, its wishes and its suggestions
go with it. Its open/closed switch and its revision counter are removed from
`settings`. If it was the start room, that setting is dropped.

## Open and closed

Every room, the main room included, is *open* or *closed*. Moderators (and
admins) switch it under *Rooms*: every row carries *Close room* or *Open
room* at the right, and a closed room is tagged *closed*. The room's edit
form has the same button. The repertoire page itself carries no switch.

While a room is closed, its repertoire stays visible, but the audience can
neither wish nor suggest a song there. The *Wish* buttons disappear, a click
on a row does nothing, and the suggestion form is gone. A notice stands in
the header of every page of the room, with an *Open room* button for
moderators.

Internally this is the former pause switch: key `wishes_paused` in
`settings` for the main room, `wishes_paused:<room id>` for every other
room.

## Closing all rooms

Under `/rooms`, left of *Add room*, admins have the switch *Close all
rooms*. It closes the main room and every room, archived ones included. It
remembers each room's previous state under `wishes_paused_all` in
`settings`. While that is in force the switch reads *Lift the closing of all
rooms*. Lifting restores the remembered states: rooms that were open reopen,
rooms a moderator had closed stay closed. Rooms created in the meantime are
left unchanged; rooms deleted in the meantime are skipped. Single rooms can
still be switched in the same list at any time.

## Switching rooms

As soon as there are two rooms to choose from, the **room switcher** stands
in the navigation. It is a button labelled *You are here: <room>* that opens
an overlay with the rooms, like the language menu. It is a `<details>`
element, so it works without JavaScript. The overlay lists the main room --
for guests only while it is listed or while they stand in it -- and every
room offered to the visitor; a guest's unlisted rooms follow under *Your
rooms*. When the switcher offers more than six rooms, a filter field hides
entries as you type (JavaScript; without it the full list stays).

On a page inside a room (repertoire, wish list, suggestions, *Manage*) the
entries link to the same page of the chosen room. On every other page (the
room list, the admin pages, a form) they only change the remembered room and
come back to the same page. Two exceptions: on a room's edit form the
switcher opens the edit form of the chosen room, and on the QR page its QR
page.

On phones, up to 560 px wide, the switcher always has a row of its own
above the tabs, which stack icon over word. Above that width the switcher
shares the tabs' row while everything fits on one row; on wide screens it
stands at the far left, apart from the tabs. When the row is too narrow, the
switcher first moves to a row of its own above the tabs (class `nav--rows`,
set by `app.js`), and the tabs stay side by side. Only if the tabs still do
not fit side by side on the full width do they stack (`nav--stacked`); the
switcher then has its own row as well. Without JavaScript the CSS fallback
gives the switcher its own row on screens up to 720 px wide and stacks the
tabs up to 560 px.

The **Rooms** tab (`/rooms`) appears only for editors and admins, with a
badge counting the active rooms besides the main one. The page itself,
where every room's name leads into the room, is reachable for everyone.

Inside a room its name stands in the header. With a logo in place the name
is hidden on phones; the room switcher below still names the room. All
links of the application stay within the room: a route declared
`roomScoped()` in `config/routes.php` exists twice -- at the bare path for
the main room and below `/rooms/<name>` for every other -- and the URL
generator puts the room the visitor is in into every address it builds.
