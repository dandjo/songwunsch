# Maintaining the repertoire

The repertoire is the list of songs the audience can wish for. Editors and
admins maintain it (see [Users and roles](users-and-roles.md)); everyone else
only reads it.

## What editors see

* On the main list, *Add song* stands above the list.
* In a room, the list shows the room's selection of the main list. The button
  above it is *Manage*, which picks songs from the main list (see
  [Rooms](rooms.md)). New songs are always added to the main list first.
* Every row carries *Edit* and *Delete*, on the main list and in a room alike.
  *Delete* asks for confirmation. Each user can switch that question off under
  *User settings*.
* While a room is closed, the *Wish* buttons are gone but *Edit* and *Delete*
  stay.

## The song form

| Field | Rule |
| --- | --- |
| Artist | Required. At most 255 characters. |
| Title | Required. At most 255 characters. |
| Length | Optional. `3:45`, `1:02:03` or a plain number of seconds (`225`). Stored in seconds. Left empty, it is stored as `NULL`. At most 23:59:59. Anything else is rejected. |
| Genre | Optional. At most 128 characters. The field offers the genres already in use (up to 200 of them), so the spelling stays the same. |

A length is shown as `m:ss`, or as `h:mm:ss` from one hour on.

## Edited and deleted songs and the wish list

A wish stores its own copy of artist, title, length and genre.

* If a song is edited, wishes already received keep the old wording. New
  wishes take the new one.
* If a song is deleted, wishes already received stay fully readable. The song
  is also removed from every room's selection.

## Importing a CSV

`tools/import-csv.php` reads a CSV file and adds its songs to the main list.
It runs on the command line only.

```bash
php tools/import-csv.php --dry-run songs.csv            # parse and report only
php tools/import-csv.php songs.csv                      # add the songs
php tools/import-csv.php --replace songs.csv            # delete every song first
php tools/import-csv.php --skip='KEIN SONG!' -  < songs.csv   # from stdin, skipping a placeholder title
docker compose exec -T web php tools/import-csv.php --replace - < songs.csv
```

Flags:

| Flag | Effect |
| --- | --- |
| `--dry-run` (or `-n`) | Parses the file, prints the number of songs and the first five, writes nothing. Needs no `config.php`. |
| `--replace` | Empties `songs` and `room_songs` before the import. |
| `--skip=<title>` | Leaves out every row with exactly this title. Can be given more than once. |
| `-` | Read the CSV from standard input instead of a file. |

The file:

* The first row is the header. Columns are found by name, so their order does
  not matter. Names are matched without regard to case; the first match wins.
  Other columns are ignored.
  * Title: *Songtitel*, *Titel*, *Title*, *Song*
  * Artist: *Künstler*, *Kuenstler*, *Interpret*, *Artist*
  * Genre (optional): *Attribute*, *Genre*, *Tags*
  * Length (optional): *Länge*, *Laenge*, *Length*, *Dauer* – `m:ss` or seconds
* The header must name a title and an artist column. Otherwise the tool stops.
* UTF-8, with or without a byte order mark. Comma or semicolon separated; the
  tool picks the separator that occurs more often in the header.
* Blank lines are skipped.

Genres:

* Several values in one cell, separated by `;` or `,` (`Oldie; PopSong`), are
  kept and joined with a comma: `Oldie, Pop`. A value repeated in the same
  cell is kept once.
* A few spellings from the streamersonglist export are tidied on the way,
  without regard to case: `PopSong` → `Pop`, `Rock Song` and `RockSong` →
  `Rock`, `RocknRoll` → `Rock 'n' Roll`, `X-Mas` → `Weihnachten`.

Writing:

* Every row is checked like the song form (artist and title required, length
  limits). If any row fails, the tool lists the failing rows and writes
  nothing.
* Rows already present – same artist and title, compared without regard to
  case – are skipped and counted as "already present".
* Everything is written in one transaction. Open pages notice the change and
  redraw their repertoire.
* `--replace` empties `songs` and `room_songs`. The rooms lose their song
  selection and must be filled again under *Manage*. Wishes keep their copies
  and stay readable. Suggestions are not touched.
