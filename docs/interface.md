# Interface

Under *Administration → Interface* (`/admin/ui`, admins only) admins set how
the interface looks and behaves. The settings apply to every visitor and
every room alike. There are five groups: the colours of the dark design, the
colours of the light one, which of the two a visitor gets by default, how
long a message stays, and how often the lists look for changes.

The values are kept in the `settings` table: `colors.<area>` and
`colors.light.<area>` for the two colour sets (`src/Colors.php`), `ui.theme`
for the default design (`src/Theme.php`), `ui.<name>` for the numbers
(`src/Ui.php`). They survive a deployment and travel with a database backup.

## Colours

Gold marks actions, violet marks tags and counters, red marks danger, green
marks success. Each of these areas has one base colour, and it has it twice:
once for the dark design and once for the light one. Admins can replace
either. Every shade and tint the interface needs – hover states, frames,
notices – is derived from the base colour of the scheme in question.

The table below names the **dark** defaults; the light ones stand under
*Two sets of six*.

| Area | Used for | Default (dark) |
| --- | --- | --- |
| Accent | Buttons, links, the active tab and focus rings, “wunsch” in the word mark, the room name in the header, gold tags and notices | `#e6b450` |
| Secondary | Genre and role tags, the counters on the tabs, the frame of info notices | `#8d7ce0` |
| Danger | Closed rooms, delete buttons, warnings, errors | `#ff6f85` |
| Success | The frame of confirmation notices, the tick on a saved language tab, the confirm buttons in the page editor's dialogs | `#4ed08c` |
| Background | Page ground; shell, panels, fields and lines are steps away from it, as is the text on gold buttons and counters | `#0d0e13` |
| Text | Text; the muted text is a step towards the background | `#e9ebf1` |

Each area has a colour picker and a text field for the hex value, in each of
the two groups. Both follow each other. A *Default* button brings the built-in colour back. The
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

The stylesheet (`assets/style.css`) keeps every colour as a custom property:
the six base colours of a scheme and the shades derived from them. When an
admin sets a colour, `src/Colors.php` derives the same shades from the new
base colour with the same ratios and the layout prints one `<style>` block
that overrides the stylesheet. Only the areas that are set are printed.
While nothing is set, nothing is printed and the stylesheet's own values
apply. Which selector that block carries, and what happens for a visitor
following their device, is under *How the schemes reach the page*.

What each area derives:

- Accent: `--gold`, a brighter and a deeper shade, and four transparent tints
  (row highlight, hover, notices, chips inside notices) plus the focus glow.
- Secondary: `--violet`, a brighter shade, a soft tint and a line tint.
- Danger: `--danger`, a brighter and a deeper shade, a line colour and three
  transparent tints.
- Success: `--ok` only.
- Background: `--ink` (the ground) and the steps `--surface`, `--shell`,
  `--base`, `--panel` and `--line` – lightened on the dark scheme; on the
  light one `--panel` and `--base` are lifted towards white while `--surface`
  and `--shell` sink.
- Text: `--text` and the muted text (`--text-muted`), mixed towards the
  background.

The page editor's colours follow the site's colours too. CKEditor reads the
same custom properties, see the end of `assets/style.css`.

## Light and dark

There are two designs and three answers to which one a page is drawn in:

* **Dark** – the interface the site has always had.
* **Light** – the same interface on a pale ground.
* **Follow my device** – whatever the visitor's own system is set to
  (`prefers-color-scheme`), so the site changes with it.

Every visitor picks one in the header, behind the sun, the crescent or the
half circle, and it applies to every room and every page they open, on that
device and in that browser. Whoever never touches the menu gets what the
admins chose under *Default design* below.

The switch needs no JavaScript – it is three ordinary forms in a pop-out
menu. With JavaScript the page changes at once and keeps what is in it, so
one can switch in the middle of filling in a wish.

### Default design

*Visitors who have not chosen see* – **Dark**, **Light** or **Follow my
device**. It is the starting point only: everyone may switch for themselves
at any time, and a visitor who has switched is not moved by a later change
here. The value is kept as `ui.theme` in the `settings` table; the default
is **Dark**, so an existing site looks exactly as it did.

A change here reaches the pages that are already open, like the colours do.

### The cookie

| Property | Value |
| --- | --- |
| Name | `songwunsch_theme` |
| Lifetime | one year |
| Path | the same as the session cookie – the application's base path (see [Base path](base-path.md)) |
| Flags | `HttpOnly`, `SameSite=Lax`, `Secure` when the site is served over HTTPS |
| Content | one of the words `system`, `light`, `dark`, nothing else |

It is written when the menu is used and never before. It holds no personal
data and identifies nobody. `prefers-color-scheme` is read by the
stylesheet, in the browser – it is never sent to the server and never
stored, so neither the cookie nor the site knows anything about the device.

### Two sets of six

The colour table above is the **dark** set (`colors.<area>`). Below it stands
the same table again for the **light** set (`colors.light.<area>`), with its
own built-in values:

| Area | Light default |
| --- | --- |
| Accent | `#8a5f0a` |
| Secondary | `#5348bd` |
| Danger | `#c01f36` |
| Success | `#0f7346` |
| Background | `#f5f6f9` |
| Text | `#1a1c23` |

They are separate on purpose. A colour picked against black is rarely
readable on white – gold at `#e6b450` has a contrast of 10:1 on the dark
ground and 1.9:1 on the light one – so the light set is chosen for its own
ground, not derived from the dark one. Nothing corrects what is typed in
either set: **the contrast is the admins' to check, in both schemes.**

What *is* derived per scheme are the shades around a base colour. "Brighter"
means away from the page ground, so on the light scheme the hover shade is
darker, not lighter; a frame is the base colour moved towards the ground, so
it turns pale there. The surfaces follow the scheme's own idiom: on the dark
one every surface is a lightened step of the ground, on the light one the
content surface is lifted towards white while the chrome around it sinks – a
white card on a grey page.

### How the schemes reach the page

`<html data-theme="dark|light|system">` carries the answer, and
`<meta name="color-scheme">` tells the browser to draw its own furniture –
scrollbars, drop-downs, the canvas – to match.

`assets/style.css` holds three palette blocks with the same token names: the
dark one on `:root`, the light one on `:root[data-theme="light"]`, and the
light one once more inside `@media (prefers-color-scheme: light)` for
`[data-theme="system"]`. The third is a verbatim copy of the second, because
a CSS rule cannot span a media query. `php tools/check-colors.php` fails if
the blocks ever drift apart, so it is a checked copy and not one to keep in
step by hand.

`src/Colors.php` writes the admins' colours over the block of the scheme in
question, with that scheme's own selector – with a plain `:root` the light
scheme's own tokens would win on specificity, however late the block comes.
A visitor on *Follow my device* gets both blocks, the light one behind the
same media query.

A logo made for the dark ground disappears on the pale one, so the light
design may have a logo of its own – one slot per design under
*Administration → Logos*, see [Logo](logo.md).

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
