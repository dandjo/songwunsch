# Users and roles

All operating functions sit behind a sign-in. Users are stored in the `users`
table and managed on the **Users** page (`/admin/users`). The page is
searchable and paged.

The admins' pages sit in the **account menu** (the person icon, top right)
under **Administration**: *Languages*, *Users*, *Logos*, *Interface*,
*Limits*, *Pages*, *Footer*. All of them live below `/admin`; `/admin` itself
leads to the users. The menu is a nested `<details>` and works without
JavaScript.

## The three roles

| Role | May |
| --- | --- |
| **Admin** | Create, edit, lock and delete users and hand out every role, the admin role included; manage the header logos, the interface (colours, messages, live updates), the limits, the pages, the footer and the fallback order of the languages; close every room at once – and everything editors and moderators may do |
| **Editor** | Maintain the repertoire: add, edit, delete titles; work the song suggestions: adopt, delete, clear; create, edit, archive and delete rooms, pick their songs, set the start room, show a room's QR code |
| **Moderator** | Edit the wish list: sort, reorder, delete, clear; close and open a room (everyone may view the list) |

The three roles are plain flags in the `users` table (`role_admin`,
`role_editor`, `role_moderator`). A user without a role can sign in but only
sees the public pages.

Admin is a role like the others. Any number of users can hold it. Every admin
may give it to or take it from any user by ticking the box in the user form,
themselves included. Admin includes editor and moderator: ticking *Admin*
ticks the other two boxes and locks them. The server derives the roles as well,
with or without JavaScript, and stores them along. So an admin who gives the
role up keeps editor and moderator. Editor and moderator combine freely.

In the code, the checks are areas of `Security::can()`: `wishes` (moderator),
`songs`, `suggestions` and `rooms` (editor), `users` (admin). An admin passes
every check. Every page and every POST action that needs a role is checked in
`index.php` (`require_role`), not only in the templates.

## What each role may open

| Who | Pages and actions |
| --- | --- |
| Everyone, without sign-in | The repertoire `/`, the wish list `/wishes` (read only, always in play order), the suggestions `/suggestions` (read and suggest), the room list `/rooms` (active, listed rooms), the pages `/pages/<slug>`, the name form `/name`, `/login`; wishing |
| Every signed-in user | All of the above; also unlisted and archived rooms; *User settings* (`/users/<own id>/settings`, `/settings` leads there); the guest view; log out |
| Moderator | On the wish list: sort, drag & drop, arrow buttons, delete, clear; *Close room* / *Open room* in the header notice and in the room list |
| Editor | The *Rooms* tab with its counter; `/song/new` and `/song/<id>`; `/suggestions/<id>/adopt`; delete and clear suggestions; `/rooms/new`, `/rooms/<id>/edit`, `/rooms/main/edit`; `/rooms/<slug>/manage` (pick the room's songs); `/rooms/<slug>/qr` (also `/rooms/main/qr`); *As start room*; delete a room |
| Admin | Everything below `/admin`; the switch that closes every room at once; the *Edit* link on a public page |

After signing in, users who may edit the wish list (moderators and admins)
land on the wish list. Everyone else lands on the repertoire. A signed-in user
who opens `/login` is sent to the same place.

## Always one admin

Nobody can lock or delete themselves. The last active admin cannot give up the
role. So somebody can always manage users. (Whoever is editing is an active
admin, so the last one can only ever be oneself.) The user form fixes those
boxes and says why. The list has no *Delete* for yourself. Saving checks again.

An admin who gives up their own admin role is sent to the pages their remaining
roles open: the wish list for a moderator, otherwise the repertoire. Another
admin can hand the role back.

## First admin

As long as the `users` table is empty, the application creates the first admin
from `auth.user` and `auth.hash` in `config.php`. This happens on the first
sign-in, or when `php tools/install.php` runs. The first admin holds all three
roles. After that only the table counts; the values in `config.php` have no
effect any more.

Create the hash for `config.php` with `php tools/hash.php 'MyPassword'`. The
tool prints a note when the password is shorter than 10 characters.

The example configuration ships with the password `Administrator`. While a
signed-in user still has this password, every page shows a warning notice at
the top with a link to *User settings*. The check runs at sign-in (or once for
an existing session) and is remembered in the session. Changing the password
removes the notice. The notice also appears for a user to whom an admin handed
the default password.

## User settings

Every signed-in user has a personal page under *User settings* in the account
menu. It shows the own username and roles, with what each role may do. It holds
the password change and the delete confirmations. Roles and status cannot be
changed there; admins assign them in the user form.

**Delete confirmations.** Deleting a single song, suggestion, wish or room
asks for confirmation. Every user can switch this off per kind, for their own
account only, and only for the kinds their roles may delete. Bulk actions
(clear the wish list, clear the suggestions) always ask. The choice is stored
in the `settings` table under `user.<id>.confirm_delete_<kind>` and is deleted
with the user.

## Passwords

A password has at least 8 characters and is typed twice. The same rule applies
in the user form, where an admin sets or resets another user's password, and
under *User settings*, where every user, admins included, changes their own:
the current password once, the new one twice. The current password must be
right; a forgotten unlocked screen is not enough. The new password applies
from the next sign-in; the running session stays. Passwords are never written
to the session, not even after a failed form.

Passwords are stored as hashes made by PHP's `password_hash()` with the
default algorithm (bcrypt today). The sign-in verifies a hash even for an
unknown name, so the response time does not reveal whether a name exists. The
sign-in form says only that username or password is wrong.

Usernames have 2 to 64 characters: letters, digits, dot, underscore, hyphen
or `@`. A username must be unique.

## Sessions

The session cookie is named `songwunsch` and ends with the browser session.
The session stores the user's id; the user record is loaded from the table on
every request. Changed roles apply immediately. A locked or deleted user is
signed out with the next click. Signing in regenerates the session id; signing
out empties the session and deletes the cookie. The session also holds
non-personal state: the CSRF token, the chosen language, one-time messages,
form input after a validation error, the guest view switch, the cooldown
timestamps for wishing and suggesting, and whether the default password is in
use. See [Security and data protection](security.md) for the cookie flags.

## Guest view

A signed-in user can look at the site the way a visitor without a sign-in
sees it: *View as guest* in the account menu. While the view is on, every
page, control and action behaves as for a stranger; a notice in the header
says so and offers *End guest view*. Switching the view on while standing on a
page a guest may not open leads to the repertoire. The account menu still
shows who is signed in.

## Data minimisation

Only username and password hash are stored – no e-mail, no real name, no
sign-in timestamps. Whoever names accounts after real people processes personal
data by doing so. Role or function names (`dj1`, `bar`) avoid that.
