# Logo

Admins can put a logo in the header. It takes the place of the word mark
“Songwunsch” and the claim below it. Inside a room, the room's name stays
beside the logo. On narrow phone screens (up to 560 px) the room's name is
hidden while a logo is shown; the room switcher below the header still names
the room.

## The Logos page

Logos are managed under *Administration → Logos* (`/admin/logos`, admins
only). The page lists every logo ever uploaded, newest first, with a preview
at the header's size, and is paged like the other lists.

Above the list stand two links, *Dark design* and *Light design*: **the page
shows one design at a time**, and everything below belongs to the one that is
marked. A sentence under the links says what that design shows right now, so
each row needs no more than *Switch live* and, where it is live, the tag
*live*. Every row also offers *Delete* (a bin icon, like in every list);
deleting a live logo makes the header fall back to what it showed before.

A new upload goes live right away unless the box *Switch it live right away*
is unticked – live in the design one uploaded from, and the message says
which that was.

## One logo per design

Pale lettering drawn for the dark ground disappears on a white one, so there
are two slots: one logo for the dark design and, for operators who have a
second version, one for the light design (see [Interface](interface.md) for
the designs themselves).

**Dark design.** One logo is live, or none. With none, the word mark shows.
The word mark heads the list on its first page as the choice “no logo” – and
only here, because it is not one choice per design but the absence of a logo
altogether. Switching to it therefore clears the light slot as well.

**Light design.** Its slot may stay empty, and then it shows the dark
design's logo – which is what an existing site does without anyone touching
anything. The sentence above the list names that logo. Switching one live
here gives the light design its own, and *Same as dark*, next to that
sentence, empties the slot again. While no logo is live at all, this design
has nothing to offer and the sentence says to set a dark logo first.

The ids are kept in the `settings` table: `logo_id` for the dark design – no
entry, or `0`, means the word mark – and `logo_id_light` for the light one.
**No entry there means the light design shows `logo_id`**; `0` never stands
in that row.

Why one design at a time: two buttons per row named after the designs said
nothing about what pressing them would do, and the row also had to carry a
reset whose meaning changed with the state. Naming the design once, in a
sentence above the list, gives every row a single verb back.

### How both reach the page

When the two designs share a logo, one `<img>` is in the page, as before.
When they differ, **both** are, and CSS shows the one the scheme calls for –
by `data-theme` on the root element, plus the `prefers-color-scheme` media
query for a visitor who follows their device. That is deliberate: the switch
in the header changes the design in the browser without asking the server
again, and a logo that had not been sent could not follow. Only the site that
uses two logos pays for the second image, and it is cached for a year
(below).

## What happens to an uploaded image

Any image size works. The accepted types are PNG, JPEG, WebP, GIF and SVG.
The type is read from the file's content, not from its name.

A raster image (PNG, JPEG, WebP, GIF) is processed with the GD extension:

- It is scaled down to 144 px in height if it is taller. The width follows.
  The header shows the logo 48 px high (40 px on very small phones), so
  144 px is three times that and stays sharp on high-resolution screens. An
  image that is shorter than 144 px is not scaled up, so it should be at
  least that high itself.
- It is stored as WebP with quality 90. That is about a third of a PNG's
  size. Transparency is kept. An animated GIF loses its animation.
- A WebP that is already no taller than 144 px is stored as it is.
- An image with more than 50 million pixels is refused before it is decoded.
  This protects the server's memory.

An SVG is stored unchanged; it scales by itself. An SVG that contains a
`<script>` tag, a `javascript:` address or an `on…=` event attribute is
rejected. The logo is only ever shown through `<img>`, where scripts do not
run anyway.

Without WebP support in GD, a JPEG stays JPEG and every other raster image is
stored as PNG. Without the GD extension, the original file is stored
unchanged and only CSS scales it in the header.

The size limit is the server's `upload_max_filesize`. In the Docker stack it
is 20 MB (`docker/php.ini`, `post_max_size` 21 MB). A file over the limit
gets an error message that names the limit.

## Drawing for both designs

With one logo for both designs, draw one that reads on either ground: a
logo with its own light-coloured plate does, pale lettering on a
transparent background does not. With two, draw each for its own ground –
that is what the second slot is for.

## Where the files live

The files live in the `uploads` table, not on disk (see
[Database](database.md)). Three reasons: the deployment syncs the code with
`--delete` and must not touch them, a shared host may have no writable
folder, and a database backup carries the logos along.

A logo is served under `/logo/<id>`. The bytes behind an id never change, so
the browser may cache them for one year (`Cache-Control: public, max-age=31536000, immutable`).
A browser that asks again gets a `304 Not Modified` from the ETag, and the
bytes do not leave the database. The response carries
`X-Content-Type-Options: nosniff` and a strict Content Security Policy, so an
SVG opened directly in a browser tab can only draw.
