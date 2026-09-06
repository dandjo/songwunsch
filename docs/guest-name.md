# The guest's name

A wish is more useful when the band knows who it is for. So the site asks a
guest for a name. Giving one is optional. Wishing works either way; a wish
without a name simply shows none.

## When the site asks

On the first visit, a dialog asks *Welcome! What is your name?* It appears
when all of this is true:

* the visitor is not signed in (staff in the guest view count as not signed
  in, that is what the view is for),
* no name cookie is set yet,
* the visitor has not declined in this browser session,
* the page is the repertoire, the wish list, the suggestions or the rooms
  page.

The dialog explains what happens with the name: it appears publicly on the
wish list and among the suggestions, next to every song the guest wishes for
or suggests; anyone who opens the site can see it. Wishes and suggestions
already made keep the old name if the name is changed later. The name is kept
in this browser for a year and can be changed any time in the account menu.

* *Save name* stores the name.
* *Not now* – or Escape, with JavaScript – closes the dialog. The site does
  not ask again in this browser session. A returning guest with a new session
  is asked again.

The dialog is a `<dialog>` element. With JavaScript it is modal over the
darkened page. Without JavaScript the same element stands as a card at the top
of the page. The forms work either way.

## Where the name goes

The name lives in a cookie and nowhere else until the guest wishes or suggests
a song. Then it is copied:

* into the wish (`song_wishes.wisher`). The wish list shows it in the
  *Received, From* column, for guests and moderators alike. Moderators can sort
  the list by it (*From*).
* into the suggestion (`song_suggestions.suggester`). The suggestions list
  shows it next to the time received. When an editor adopts the suggestion,
  the name goes onto the wish that is created for it.

A name saved or changed later does not change wishes and suggestions already
made.

The cookie:

| Property | Value |
| --- | --- |
| Name | `songwunsch_name` |
| Lifetime | one year |
| Path | the same as the session cookie – the application's base path (see [Base path](base-path.md)) |
| Flags | `HttpOnly`, `SameSite=Lax`, `Secure` when the site is served over HTTPS |
| Content | the name and nothing else |

## Changing or removing the name

The account menu (person icon, top right) heads with the guest's name, or
with *No name yet*. Below it stands *Change name* – or *Set name* when there is
none yet. It leads to `/name`, the same form as a page. There, an empty name
removes the cookie; wishes then carry no name.

Signed-in users are never asked by the dialog, but they wish like anyone else
and their wishes carry the cookie's name. They find the same entry in the
account menu as *Wishing as …*, or *Name for wishes* while there is none.

## What is stored

Names are tidied on the way in: control characters are replaced by a space,
runs of whitespace are collapsed to one space, leading and trailing spaces are
cut, and the name is cut to at most 40 characters. The input field has the same
limit. On the way out the name is escaped like every other value.

## Data protection

The name is personal data the guest chooses to give.

* It is stored only with the wish and the suggestion, and it is deleted with
  them (*Delete*, *Clear list*, deleting a room).
* Nothing links it to an IP address. The session only remembers that the
  question was put, not the answer.
* The cookie holds nothing but the name. It is set at the guest's explicit
  request and the guest can remove it at any time.
* Nothing else about the guest is recorded.
