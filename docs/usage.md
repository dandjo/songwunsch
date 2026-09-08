# Usage

## Help behind the "?"

Every page explains itself in a line or two. That text sits behind the *?*
at the top right of the header, next to the language and account menus. It
is a popout like those two: a `<details>` element, so it works without
JavaScript. With JavaScript it also closes on a click elsewhere or on
Escape.

The templates hand the text up in `$help`. The layout renders the page
first and the header after it. The list or form therefore starts as high as
possible. The pages the main navigation leads to (repertoire, wish list,
suggestions, rooms) show no visible title. The active tab names them, and
the `<h1>` is left to screen readers. Other pages keep their title.

The number of archived rooms and what a search found are part of the help
text. The number of rooms, songs, open wishes and open suggestions stands
on the tabs. The header's notice shows whether the room is closed.

## Everyday tasks

| What | How |
| --- | --- |
| Search | Field at the top of the list; several words are combined with AND (at most six words count); `/` jumps into the search field |
| Switch language | Language menu (globe) top right; the choice is remembered |
| Give or change your name | Asked on the first visit; later account menu (person icon) top right → *Change name* / *Set name*, or `/name`. An empty name removes it |
| Sign in / sign out | Account menu (person icon) top right next to the language menu; when signed in it shows the name and *Log out*. After signing in, moderators land on the wish list, everyone else on the repertoire |
| See the site as a guest | Signed in: account menu → *View as guest*; a notice in the header and *End guest view* lead back. Meanwhile pages, controls and actions behave exactly as for a visitor without a login |
| Sort | Sort bar above the list; a second click on the active choice reverses the direction. On phones the bar is a popout, *Sort: <current>*, with the same choices |
| Wish | *Wish* button in the row, or a click on the row while the room is open. A song that is already on the wish list is not added twice: the wish counts on the existing entry, and everyone sees the number on its card (*3×*) |
| Change the order | Wish list → drag the row (drag & drop) or the buttons on the right: to the top, ▲, ▼, to the bottom. The list is paged; a drag reorders the shown page within the places its wishes hold, the buttons move across pages |
| Delete a wish | Wish list → *Delete* in the row |
| Delete everything | Wish list → *Clear list* |
| Close or open a room | Moderator: Rooms → *Close room* / *Open room* in the row, or the same button on the room's edit form (no wishes and no suggestions while closed); a closed room's header notice has *Open room* as well |
| Close all rooms | Admins: Rooms → *Close all rooms* / *Lift the closing of all rooms* |
| Suggest a song | Suggestions → artist and title → *Suggest* (everyone, also without a login) |
| Adopt a suggestion | Editor: Suggestions → *Adopt* in the row → add length and genre, choose the top or the bottom of the wish list → *Add*. A song that is on the repertoire already is reused, no second copy is made |
| Delete a suggestion | Editor: Suggestions → *Delete* in the row; *Clear list* deletes all |
| Add a song | Editor: repertoire → *Add song* |
| Change a song | Editor: *Edit* in the row |
| Delete a song | Editor: *Delete* in the row (asks for confirmation unless switched off). In a room the same bin reads *Remove* and only takes the song out of the room |
| Change room | Room switcher in the navigation (*You are here: <room>*, opens the list). It shares the tabs' row while everything fits, otherwise it takes a row of its own above the tabs. Or Rooms → click the room's name. The choice is remembered in a cookie, see [Rooms](rooms.md) |
| Create a room | Editor: Rooms → *Add room* → *Create*; the new room leads on to *Manage* |
| Manage a room's songs | Editor: *Manage* above the room's repertoire, on the room's row under Rooms, or on its edit form |
| Create a user | Admins: Users → *Add user* |
| Make a user admin | Admins: Users → *Edit* → tick *Admin* (this also ticks the other roles); untick it to take the role away (the only active admin keeps it) |
| Switch off delete confirmations | Signed in: account menu → User settings → *Delete confirmations*, per account and separately for songs, suggestions, wishes and rooms, each only with the matching role (all on by default; *Clear list* always asks) |
| Change your own password | Signed in (admins included): account menu → User settings → *Change password* (current password plus the new one twice) |
| See your own roles | Signed in: account menu → User settings, box *Your account* |
| Put a logo in the header | Admins: *Administration → Logos* (`/admin/logos`): upload, *Switch live*, see [Logo](logo.md) |
| Change the footer line | Admins: *Administration → Footer* (`/admin/footer`), *Your own line* below the picker |
| Change the fallback order of the languages | Admins: *Administration → Languages* (`/admin/languages`), drag a row or use its arrows |
| Change the interface | Admins: *Administration → Interface* (`/admin/ui`): pick or type a colour per area (*Default* brings the built-in one back), the seconds a message stays, the live-update interval per case, see [Interface](interface.md) |
| Change the wish and suggestion limits | Admins: *Administration → Limits* (`/admin/limits`): open wishes per room, per-minute and per-hour limits, seconds between two wishes or suggestions and after the page load, rows per page, see [Protecting the wishing](wish-protection.md) |

## The wish list's order

The wish list starts in manual order. At first this equals the order of
arrival, oldest on top. Sorting by a column only changes the view; the
stored order is kept. Guests always see the manual order. Moderators get
back to it via *#* or *Manual order*.

## Live updates

The lists keep themselves current without a reload, and the counters on the
tabs follow on every page. Every change raises a revision counter in the
`settings` table:

- `wishes_rev` (main room) and `wishes_rev:<room id>`: a wish coming in,
  deleted or moved, the list cleared, the room closed or opened.
- `wishes_all_rev`: the same for any room at once, for the list of rooms.
- `suggestions_rev`: a suggestion made, adopted or deleted.
- `catalog_rev`: a room created, renamed, archived or deleted, the main room
  renamed, the start room chosen, a song added, edited or removed, a room's
  selection changed.
- `ui_rev`: something the page shell shows – the colours, the message
  duration, the polling intervals (*Administration → Interface*) and the logo
  in the header (*Administration → Logos*).

The counters wrap at a million; only the difference matters.

Every page has two tokens, and both start with the room's state, closed or
open.

The **head token** stands for the header, which every page carries: it holds
`catalog_rev` behind the room switcher and the counters on the *Rooms* and
*Repertoire* tabs, the room's wish revision behind the counter on *Wishes*,
`suggestions_rev` behind the one on *Suggestions* and `ui_rev` behind the
look of the shell. When it moves, the header alone is drawn anew – on a form
as on a list, and the colours come with it. So the counters, the closed-room
notice, the logo, the message duration and the polling pace itself follow
everywhere while a form being filled in, or a search term just typed, keeps
its input.

An admin who changes a polling interval therefore changes it for the pages
that are already open: they renew their header at their old pace one last
time and go on at the new one.

The **page token** stands for the content, and only a list has one. When it
moves, the whole page is drawn anew and a search term stays in place. The
repertoire and the room's song picker follow `catalog_rev`; the list of rooms
follows `wishes_all_rev` as well, because it counts the wishes of every room
and marks the closed ones; the wish list follows its own wish revision, the
suggestions theirs plus the room's wish revision, since closing the room
hides their form as well. When a moderator closes the room, the *Wish*
buttons disappear and the closed-room notice appears, and both come back when
it opens. The announcement for screen readers tells a closing or opening
apart from any other change.

### What a page actually asks for

An open page does not ask PHP whether something happened. It asks the web
server for one small file, `assets/state/live.txt`, whose content the application
rewrites whenever anything in the `settings` table changes – and every change
above raises a counter there. The web server hands that file out by itself:
no PHP process, no database query, and because it carries an ETag, a page
that asks again is answered with *304 Not Modified* and an empty body. In a
quiet room that is all that ever happens.

Only when the content differs from the value the page was given does it ask
`?poll=1` on its own address for the two tokens – a JSON object of a few
bytes – and only when a token has moved does it fetch itself again and swap
in what changed. Once a minute the tokens are fetched anyway, so a page
cannot fall behind if the file stops being written.

The file says *something changed*, never what: it is readable by anyone, and
a room is often named after the hosts of a private party. Room names, room
ids and how busy a room is stay out of it.

If the file cannot be written – `assets/` not writable for the web server –
nothing breaks: every poll then goes to PHP, as it did before. See
[Installation](installation.md).

Focus and scroll position stay through a swap. A drag in progress or an open
menu postpones it. Hidden tabs do not poll. After an error the wait doubles,
up to 16 times the interval.

How often a page asks is set under *Administration → Interface*
(`/admin/ui`), one interval per case in seconds: wish list (default 4),
suggestions (default 4), room state with rooms and songs (default 10), each
0 to 300. 0 switches that case off; its pages then do not poll at all.

A WebSocket would need a long-running server process, which shared hosting
does not offer. Polling a counter costs one tiny request per open page and
interval. Without JavaScript the page is current after the next reload.

## With and without JavaScript

Sorting, searching, wishing, reordering and deleting work without
JavaScript. The ▲/▼ switches are ordinary forms and at the same time the
way for keyboard and touch. `assets/app.js` adds drag & drop, the row
click, confirmations, the `/` key, the pop-up messages and the live
updates.

With JavaScript, links and forms inside the page's content do not reload
the page. The result is fetched and only the content is swapped in: no
white flash, the scroll position stays, the focus returns to the control
that was used, and the address bar follows, so back, forward and reload
keep working. After paging the view starts at the top. Links to another
page (a form to fill in, an admin page), the header's menus and the name
dialog load the normal way.

## Light and dark

The menu in the header – a sun, a crescent or a half circle – offers three
designs: *Follow my device*, which takes the light or dark setting of one's
own system, *Light* and *Dark*. Choosing takes effect at once, without a
reload, and a form being filled in keeps what is in it.

The choice belongs to the visitor, not to the site: it is kept in a cookie
on that device and holds for every room and every page. Whoever never opens
the menu gets the design the admins set as the default – *Follow my device*,
unless they changed it.

The menu works without JavaScript as well; the page then reloads and comes
back where it was. The two colour sets and the default are under
[Interface](interface.md).

## Layout and screen sizes

There is a single layout for all screen sizes, a compact card layout. On
wide screens the shell is centred and limited to 1180 px.

The header has two rows. The word mark (or the logo), the *?*, the language
menu, the design menu and the account menu share the first row. The account menu (person
icon) opens the guest's name with *Change name* and *Log in*, or for staff
the username, *Name for wishes*, *User settings*, for admins
*Administration* with its pages as sub-entries, *View as guest* and *Log
out*. The navigation stands below, right-aligned, with the room switcher at
its left. From 721 px on the header sticks to the top while the page
scrolls; on phones it scrolls away. Up to 560 px the language menu shows
the globe alone.

On phones, up to 560 px wide, the tabs always stack icon over word like an
app's tab bar (`nav--stacked`), the counter sitting in the tab's top right
corner, and the room switcher has a row of its own above them. The layout
is fixed there, so the bar does not flip when a counter grows or the room
changes. The tabs share the row as equal columns as long as every label
fits into an equal share; a label that needs more keeps its width and the
other tabs share what remains, so no label wraps or is cut short. Above that width the tabs stand side by side while they fit on one
row. `assets/app.js` measures the row, so any language, number of tabs and
font size counts. The room switcher shares the row while everything fits.
When it does not fit, the switcher first moves to a row of its own above the
tabs (`nav--rows`), and the tabs stay side by side. Only if the tabs still
do not fit side by side on the full width do they stack; the switcher then
has its own row as well. Without JavaScript the CSS fallback gives the
switcher its own row up to 720 px and stacks the tabs up to 560 px.

Every page's head puts the title at the left – the description sits behind
the *?* – and the page actions (*Add room*, *Close all rooms*, *Manage*,
*Clear list*, …) at the top right beside it from 721 px on. On narrower screens the actions
drop below the text, right-aligned.

The popouts (help, language, account, room switcher, the sort menu on
phones) are `<details>` and work without JavaScript. With JavaScript they
also close on a click outside or on Escape, and opening one menu closes the
others.

Input fields are 16 px so iOS does not zoom in.

## Back where one came from

Every form (song, room, the main room's name, user, page, the guest's name),
the QR page and *Manage* know where the visitor came from. The link into
them passes the current address as `back`: the list with its search, filter
and page; a page's own address for its *Edit*; the repertoire for a song.
*Cancel* and *Back* lead there, and so does the save action once the form
is through, like Drupal's `destination`.

The form carries the address in a hidden field, so it survives a validation
error, a language change and a room switch. Without a usable `back` the
form falls back to its list (`Controller::destination()` in
`src/Controller/Controller.php`; `UrlGenerator::safeTarget()` only accepts
addresses of this site). One exception: a
newly created room leads on to *Manage*, since it has no songs yet. *Back*
there leads to where *Add room* was clicked. On *Manage* the address
survives every move, search and page change.

## How lists look

All lists share the same action pattern. A card's buttons stand at the
right in a vertical stack, each as wide as the widest. *Edit* and *Delete*
share one line of the stack as an icon pair (pencil and bin, 50 % each).
Their text remains as a tooltip and for screen readers. The stack never
stretches the text: every card grid has a flexible empty row above and
below its text lines, so a tall stack only adds height there and the text
stays a tight, centred block.

* **Repertoire** – title, below it artist · length · genre; *Wish* on the
  right, for editors the pair *Edit* / *Delete* below it (*Remove* in a
  room). A row whose song is already on the room's wish list is marked – a
  veil in the accent colour with an edge in it – and its *Wish* button says
  so to a screen reader; wishing again counts up on the entry instead of
  adding a second one.
* **Rooms** – name with its tags (*always there*, *archived*, *unlisted*,
  *closed*, *start room*, *(current)*), below it the address, below that
  the counts spelled out ("50 songs · 7 wishes"; the wishes only for
  moderators). The name is the link into the room. On the right, in the
  order of the edit form: for editors *As start room*, for moderators
  *Close room* / *Open room*, for editors *QR code*, then *Manage* and the
  pair *Edit* / *Delete*. The main room's row carries the same switches but
  neither *Manage* nor *Delete*: *As start room* while another room is the
  start room, *Close room* / *Open room*, *QR code* and *Edit* (its name and
  its listed switch). Guests see no buttons.
* **Users** – name, below it roles · status (*active* or *locked*); the pair
  *Edit* / *Delete* on the right (no *Delete* for yourself).
* **Suggestions** – title, below it the artist; on the right the time
  received above who suggested, next to it *Adopt* above *Delete* for
  editors. On phones time and name move to a third line, like on the wish
  list.
* **Wish list** – position on the left, title, below it artist · length ·
  genre; on the right, right-aligned in one column, the time received
  (clock glyph and stamp) above who wished (person glyph and name, if
  given); a disc in the secondary colour with how often the song was wished (*3×*, from the
  second wish on, for everyone), and for moderators next to it the four
  move buttons (to the top, ▲, ▼, to the bottom) above *Delete* (bin, as
  wide as the button row). On phones the four become a 2×2 block, "to the
  top" under ▲ and "to the bottom" under ▼, and the time received moves to
  a third line under the artist, the name right of it. The relative time
  ("5 min ago") is not shown; overlong names are cut with an ellipsis
  instead of pushing the buttons.

Below a list with more than one page stands the pager: *first*, *back*,
"Page x of y", *next*, *last*. Links that lead nowhere are left out. On
phones only the arrows remain visible; the words stay for screen readers.
