# Song suggestions

A guest who misses a song can suggest it. The editors decide whether it joins
the repertoire.

## Where to find it

The **Suggestions** tab (light bulb, right of the wish list) is open to
everyone, signed in or not. Below the form, everyone sees the room's open
suggestions: oldest first, with the time received and the name of whoever
suggested it. Everyone can search them by artist, title or name. Several
search terms must all match (AND), like the song search; up to six terms are
used. Long lists are paged. The page size is *Rows per page* under
*Administration → Limits* (default 50). Only editors and admins get the
buttons.

## Suggesting a song

The guest enters artist and title. Both are required, each at most 255
characters. Control characters are removed and runs of blanks are collapsed.
If the guest has given a name (see [The guest's name](guest-name.md)), it
travels with the suggestion, so the editors know who asked. The form says so
above the button.

Before a suggestion is stored, the application checks, in this order:

1. The room is open. In a closed room the form is not shown and a late
   submission is turned away (see [Rooms](rooms.md)). Editors still see the
   list.
2. The bot hurdles pass: the honeypot field and the signed timestamp, the
   same as on the wish form (see [Protecting the wishing](wish-protection.md)).
3. The session cooldown has passed: 10 s between two suggestions from the
   same browser session by default. It is a separate clock from the wish
   cooldown.
4. Artist and title are valid.
5. The room's suggestion box is not full: 200 open suggestions by default,
   `0` = no cap.
6. The song is not on the repertoire already. If it is, the guest is told to
   simply wish for it.
7. The song has not been suggested in this room already. A suggestion of the
   same song in another room does not count.

Both comparisons ignore case. Admins set the cooldown and the cap under
*Administration → Limits*. Suggestions do not count against the wish limits
per sender.

## Suggestions belong to the room

Like the repertoire and the wish list, the suggestions belong to the room.
`/rooms/<name>/suggestions` lists what was suggested in that room and nothing
else; `/suggestions` lists the main room's. The tab leads to the current
room's list. The room switcher keeps one on the suggestions page when changing
rooms. Deleting a room deletes its suggestions along with its wishes.

The adopted song always goes onto the main list, which every room picks from,
and is offered in the suggestion's room right away.

## The counters on the tabs

Every tab carries a badge:

* *Repertoire*: the room's songs.
* *Wishes*: the room's open wishes.
* *Suggestions*: the room's open suggestions.
* *Rooms* (editors and admins only): the active rooms besides the main one.

The first three are shown to everyone, guests included.

Open pages poll for changes and redraw the list when something happened; how
often is set under *Administration → Interface*.

## What editors do

Editors and admins get two buttons on every row and *Clear list* above the
list.

### Adopt

*Adopt* opens the *Add song* form as *Adopt suggestion*:

* Artist and title are filled in. The cursor waits in the length field, so the
  editor adds what is missing: length and genre.
* Below the genre the editor chooses where the wish queues: at the **top** of
  the room's wish list or at the **bottom**. The top is preselected: a
  suggestion is usually adopted the moment it comes up, and the audience
  should see it played soon.
* *Add* does everything in one go: it creates the song, puts it into the
  suggestion's room, places it on that room's wish list in the name of
  whoever suggested it (the suggestion was a wish, after all), and deletes
  the suggestion.
* *Cancel* leaves everything as it was.

Special cases:

* The song is on the repertoire already – suggested in another room and
  adopted there, say. The form says so. *Add* creates no second copy. The
  existing song joins the room and its wish list. If it is open on that wish
  list already, it is counted once more instead of getting a second row. The
  length and genre typed into the form are not applied to the existing song.
* The suggestion was deleted while the form was open. The song is added all
  the same, but nothing goes onto a wish list.
* The suggestion's room was deleted meanwhile. The wish goes onto the main
  room's wish list.

### Delete and Clear list

* **Delete** drops one suggestion. It asks for confirmation unless the editor
  switched that off under *User settings*.
* **Clear list** deletes every open suggestion of the room. It always asks.
