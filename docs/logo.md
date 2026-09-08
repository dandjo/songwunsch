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

Pale lettering drawn for the dark ground disappears on a white one, so each
design has a choice of its own: a logo, or the word mark (see
[Interface](interface.md) for the designs themselves). An operator with a
second version of their logo puts it up for the light design; one without
can leave the light design at the word mark rather than show a pale logo on
white.

**Dark design.** A logo is live, or the word mark. That is what a visitor
sees unless they switch.

**Light design.** Three states, and the difference between two of them is
the point:

| State | What it means |
| --- | --- |
| *follows the dark design* | it shows whatever the dark design shows, **now and later** – a logo that goes live for the dark design goes live here with it |
| a logo of its own | that logo, and a change to the dark design leaves it alone |
| the word mark | the word mark, whatever the dark design shows |

A fresh site follows, which is why an existing one looks unchanged. Choosing
anything in the light design – a logo *or* the word mark – takes it out of
following, and **from then on the dark design's changes stay out of it**.
*Same as dark*, at the right of the sentence above the list, puts it back to
following.

The two designs are otherwise independent: putting a logo up for the dark
design never reaches into the light one's own choice.

The `settings` table holds `logo_id` for the dark design – no entry, or `0`,
means the word mark – and `logo_id_light` for the light one, where the three
states are **no entry** (follows), `0` (the word mark) and an id.

Why one design at a time: two buttons per row named after the designs said
nothing about what pressing them would do, and the row also had to carry a
reset whose meaning changed with the state. Naming the design once, in a
sentence above the list, gives every row a single verb back.

### How both reach the page

While the two designs show the same thing, the header carries one brand
block, as it always did. When they differ it carries **both** – each one a
logo, or the word mark with the claim under it – and CSS shows the one the
scheme calls for: by `data-theme` on the root element, plus the
`prefers-color-scheme` media query for a visitor who follows their device.

That is deliberate. The switch in the header changes the design in the
browser without asking the server again, so anything that had not been sent
could not follow. Only a site whose designs differ pays for the second
image, and it is cached for a year (below).

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
