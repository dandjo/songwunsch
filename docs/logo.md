# Logo

Admins can put a logo in the header. It takes the place of the word mark
“Songwunsch” and the claim below it. Inside a room, the room's name stays
beside the logo. On narrow phone screens (up to 560 px) the room's name is
hidden while a logo is shown; the room switcher below the header still names
the room.

## The Logos page

Logos are managed under *Administration → Logos* (`/admin/logos`, admins
only). The page lists every logo ever uploaded, newest first, with a preview
at the header's size. The list is paged like the other lists. Exactly one
logo is *live* at a time, or none. With none, the word mark shows. The word
mark heads the list on its first page as the choice “no logo”.

Each row offers *Switch live* and *Delete* (a bin icon, like in every list).
A new upload goes live right away unless the box *Switch it live right away*
is unticked. Deleting the live logo brings the word mark back.

The id of the live logo is kept in the `settings` table under `logo_id`. No
entry, or `0`, means the word mark.

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

## Light and dark

There is one logo per site, not one per scheme. A logo drawn for the dark
ground – pale lettering, a transparent background – still reads on the light
one, but flatly; a logo with its own light-coloured plate reads on both. If
the site's visitors mostly read it light, draw for that. See
[Interface](interface.md) for the two schemes.

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
