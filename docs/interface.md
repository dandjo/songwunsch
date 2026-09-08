# Interface

Under *Administration → Interface* (`/admin/ui`, admins only) admins set how
the interface looks and behaves. The settings apply to every visitor and
every room alike. There are three groups: the colours, how long a message
stays, and how often the lists look for changes.

The values are kept in the `settings` table: `colors.<area>` for the colours
(`src/Colors.php`), `ui.<name>` for the numbers (`src/Ui.php`). They survive
a deployment and travel with a database backup.

## Colours

The interface is dark. Gold marks actions, violet marks tags and counters,
red marks danger, green marks success. Each of these areas has one base
colour. Admins can replace it. Every shade and tint the interface needs –
hover states, frames, notices – is derived from the base colour.

| Area | Used for | Default |
| --- | --- | --- |
| Accent | Buttons, links, the active tab and focus rings, “wunsch” in the word mark, the room name in the header, gold tags and notices | `#e6b450` |
| Secondary | Genre and role tags, the counters on the tabs, the frame of info notices | `#8d7ce0` |
| Danger | Closed rooms, delete buttons, warnings, errors | `#ff6f85` |
| Success | The frame of confirmation notices, the tick on a saved language tab, the confirm buttons in the page editor's dialogs | `#4ed08c` |
| Background | Page ground; shell, panels, fields and lines are lightened steps of it, as is the text on gold buttons and counters | `#0d0e13` |
| Text | Text; the muted text is a step towards the background | `#e9ebf1` |

Each area has a colour picker and a text field for the hex value. Both
follow each other. A *Default* button brings the built-in colour back. The
picker and the *Default* button need JavaScript; without it the text field
alone does the job. The built-in value is shown as a hint under each field.

A value is written as `#rrggbb` or `#rgb`; the `#` may be left out. The value
is stored in lower case as `#rrggbb`. An empty field means the built-in
colour; its entry is then removed from the `settings` table.

Keep the contrast readable. Check the result with the accessibility tools of
the browser after a change – in both schemes, see *Light and dark* below and
[Accessibility](accessibility.md).

A saved colour behaves the same way: every page that is open picks it up at
its next check.

### How the colours reach the page

The stylesheet (`assets/style.css`) keeps every colour as a custom property
on `:root`: the six base colours and the shades derived from them. When an
admin sets a colour, `src/Colors.php` derives the same shades from the new
base colour with the same ratios and the layout prints one
`<style>:root{…}</style>` block that overrides the stylesheet. Only the
areas that are set are printed. While nothing is set, nothing is printed and
the stylesheet's own values apply.

What each area derives:

- Accent: `--gold`, a brighter and a deeper shade, and four transparent tints
  (row highlight, hover, notices, chips inside notices) plus the focus glow.
- Secondary: `--violet`, a brighter shade, a soft tint and a line tint.
- Danger: `--danger`, a brighter and a deeper shade, a line colour and three
  transparent tints.
- Success: `--ok` only.
- Background: `--ink` (the ground) and the lightened steps `--surface`,
  `--shell`, `--base`, `--panel` and `--line`.
- Text: `--text` and the muted text (`--text-muted`), mixed towards the
  background.

The page editor's colours follow the site's colours too. CKEditor reads the
same custom properties, see the end of `assets/style.css`.

## Light and dark

The interface is dark. Every visitor may read it light instead: the sun in the
header switches over, the crescent switches back. The choice is that visitor's
own – it is kept in a cookie (`songwunsch_theme`, one year) and applies to
every room and every page they open, on that device and in that browser.

Nobody is switched over without asking. `prefers-color-scheme` is deliberately
not consulted: whoever says nothing keeps the dark interface the site has
always had. There are two schemes, not three, and no setting for admins to
preselect one.

The two palettes live next to each other in `assets/style.css`: the dark one
on `:root`, the light one on `:root[data-theme="light"]`. They hold the same
token names with different values, which is why no rule further down the
stylesheet ever names a scheme – and why a new colour has to be added to both
blocks.

### What the admins' colours do in the light scheme

The colours above are the operator's, for everyone; the scheme is the
visitor's. Where the two meet:

* **Accent, secondary, danger, success apply in both schemes.** A base colour
  was picked against the dark ground, so on the pale one it is first darkened
  – as far as it has to be to reach the contrast WCAG asks for body text
  (4.5:1), and no further. A pale yellow that shines on black arrives as a
  dark ochre on white. The colour is left exactly as typed in for the dark
  scheme.
* **Background and text shape the dark scheme only.** The light scheme keeps
  its own ground, its own surfaces and its own text. A ground the admins set
  dark would otherwise make the light scheme dark again, and the switch in the
  header would look broken.
* The shades follow the scheme: "brighter" means away from the ground, so on
  the light scheme the hover shade is *darker*; a frame is the base colour
  moved towards the ground, so it turns pale there.

`src/Colors.php` derives all of that, and writes its block with the selector
the scheme needs (`:root` or `:root[data-theme="light"]`) – with the plain
selector the light scheme's own tokens would win on specificity, however late
the block comes.

A logo made for the dark ground may look flat on the pale one; there is one
logo per site, not one per scheme, see [Logo](logo.md).

## Messages

The result of an action – a wish is in, a song was added, a row was deleted –
pops up at the bottom edge and disappears after *Seconds a message is shown*.
The default is 5 seconds; the range is 0 to 60. `0` keeps the message until
it is dismissed. Error messages behave the same way. Notices that belong to
the page one lands on stand at the top of the content and stay.

## Live updates

Lists keep themselves current without a reload, and the counters on the tabs
follow on every page. Each case has its own interval, in seconds between two
checks. The range is 0 to 300. `0` switches that case off; the page is then
current only after a reload. See *Live updates* under [Usage](usage.md) for
what each case does.

The interval belongs to the page one is looking at, not to what changed: a
page checks at its own pace for everything, its own list as well as the
header.

A change here reaches the pages that are already open. They check once more
at the old pace, take on the new colours, the new message duration and the
new interval with their next check, and go on from there – nobody has to
reload.

| Setting | Default | What it checks |
| --- | --- | --- |
| Wish list | 4 | How often the wish list looks for changes |
| Suggestions | 4 | How often the suggestions look for changes |
| Room state, rooms and songs | 10 | How often the repertoire, the room's song picker, the list of rooms and every page that is no list look for changes |

The numbers are stored even when they are left at their default. A later
change of a built-in default therefore does not change a site whose admins
have looked at the page and saved it.
