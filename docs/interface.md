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

Each of the seven areas of use has one base colour, and it has it twice:
once for the dark design and once for the light one. Admins can replace
either. Every shade and tint the interface needs – hover states, frames,
notices – is derived from the base colour of the scheme in question.

The table below names the **dark** defaults; the light ones stand under
*Two sets of seven*.

| Area | Used for | Default (dark) |
| --- | --- | --- |
| Primary | Buttons, links, the active tab and focus rings, “wunsch” in the word mark, primary tags and notices | `#93c5fd` |
| Secondary | The room switcher, the tabs that are not the current page, their hover, and the counter discs on them | `#60a5fa` |
| Accent | “wunsch” in the word mark, genre and role tags, what an editor does (*Edit*, *Add …*, *Adopt*, and the *Save* that finishes their forms), the switched-on sort chip, the dot on the account menu, a song already on the wish list, the frame of info notices | `#fbbf24` |
| Danger | Closed rooms, delete buttons, warnings, errors | `#f87171` |
| Success | The frame of confirmation notices, the tick on a saved language tab, the confirm buttons in the page editor's dialogs | `#4ade80` |
| Background | Page ground; shell, panels, fields and lines are steps away from it, as is the text on filled buttons and counters | `#111827` |
| Text | Text; the muted text is a step towards the background | `#f3f4f6` |

The dark design: a near-black navy ground, a light blue for the menu and
everything a visitor acts on, a fuller blue for the counters those tabs
carry, amber for the word mark, the tags and what an editor does, a soft red
for danger, a leaf green for success, and an off-white for the text. The
three carry the interface between them: the menu and its actions, the
counters on it, and what stands out from both.

Each area has a colour picker and a text field for the hex value, in each of
the two groups. Both follow each other. A *Default* button brings the built-in colour back. The
picker and the *Default* button need JavaScript; without it the text field
alone does the job. The built-in value is shown as a hint under each field.

A value is written as `#rrggbb` or `#rgb`; the `#` may be left out. The value
is stored in lower case as `#rrggbb`. An empty field means the built-in
colour; its entry is then removed from the `settings` table.

### The live preview

While a colour is being changed – by a preset or typed into a field – the
whole page follows at once: `app.js` sends the fourteen values to
`/admin/ui/preview`, which answers with the very block `src/Colors.php` would
write, and lays it over the saved one. Nothing is stored, so leaving the page
or saving drops the preview; the derivation stays in PHP, so there is no
second copy of the ratios in JavaScript. Without JavaScript the fields simply
show their values and *Save* is what applies them.

### Presets

Above the two colour sets stands a row of presets: ready-made pairs of a
dark and a light set built from the same colour families, so the switch in
the header keeps the character of the design. A click fills all fourteen
fields – both sets, whichever tab is open. Nothing is saved until *Save*,
and every field can still be changed, so a preset is a starting point as
much as a choice. The preset whose fourteen values are all in the fields is
marked. Like the pickers, the row needs JavaScript; without it the fields
stand alone.

| Preset | Menu · counters · what stands out |
| --- | --- |
| Corporate Blue | Deep blue, a lighter blue, amber – the built-in palette |
| Slate & Teal | Slate, teal, amber |
| Autumn Harvest | Amber, brown, a leaf green, on cream |
| Coral & Cream | Rose, amber, teal, on a warm pale ground |
| Earth Tones | Warm greys, taupe that carries the counters, a green for the tags |
| Clean Minimal | Near-black, grey, and a blue that is the only hue |
| Swiss Design | Red, grey, a second grey – hue only where it is danger |
| Black & Gold | Gold, grey, a pale gold, on near-black |
| Forest Green | Two greens and amber, on a green-tinged ground |
| Holiday Magic | Red, green, amber, on cream |
| Electric Neon | Cyan, pink, amber, on near-black |
| Retro Gaming | Violet, cyan, amber, on indigo |

Each preset names its three voices in that order: the menu and its actions,
the counters the tabs carry, and what stands out from both.

The first entry in the row is **your own palette**, once *Save as my palette*
has been pressed: it keeps the fourteen colours that stand in the fields at
that moment, under `colors.own` in the `settings` table, and saving it again
replaces it. It behaves like any other preset afterwards – a click fills the
fields, *Save* applies them. *Delete my palette* removes the entry again and
appears only while there is one; it changes no colour, neither in the fields
nor on the site.

*Corporate Blue* is the built-in palette – what the stylesheet carries and
what an empty field means – and it is offered as a preset all the same, so a
set of fields that has wandered can be put back to it in one click. The
*Default* button beside a single field does the same for that one field.

The presets live in `Colors::PRESETS` (`src/Colors.php`). The colour families
are the Tailwind CSS scale; the pairings follow the role palettes at
mypalettetool.com. A red and a green for danger and success are the same in
every preset – those two carry meaning, not brand, so a preset whose own
character is red or green (Holiday Magic, Forest Green) shares those values
rather than shifting them. `php tools/check-colors.php`
checks every preset, in both schemes, for the contrast WCAG 2.2 AA asks for
on the surfaces the stylesheet puts each colour on – 4.5:1 for text, links
and button labels, 3:1 for the number on a counter disc – and a new preset
has to pass the same check.

Keep the contrast readable. Check the result with the accessibility tools of
the browser after a change – in both schemes, see *Light and dark* below and
[Accessibility](accessibility.md).

A saved colour behaves the same way: every page that is open picks it up at
its next check.

### How the colours reach the page

The stylesheet (`assets/style.css`) keeps every colour as a custom property:
the seven base colours of a scheme and the shades derived from them. When an
admin sets a colour, `src/Colors.php` derives the same shades from the new
base colour with the same ratios and the layout prints one `<style>` block
that overrides the stylesheet. Only the areas that are set are printed.
While nothing is set, nothing is printed and the stylesheet's own values
apply. Which selector that block carries, and what happens for a visitor
following their device, is under *How the schemes reach the page*.

What each area derives:

- Primary: `--gold`, a brighter and a deeper shade, and four transparent tints
  (row highlight, hover, notices, chips inside notices). The focus glow of the
  editor is drawn with the strongest of them, so it follows without a token
  of its own.
- Secondary: `--steel`, a brighter and a deeper shade, and two transparent
  tints – the frame, the veil and the label of the room switcher and of the
  tabs that are not the current page, and the discs their counters sit on.
- Accent: `--violet`, a brighter shade, and three transparent tints – the
    wash under a row that is already wished, the soft veil of a tag, the line
    of its frame.
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
is **Follow my device**, so a visitor who never touches the switch gets the
design their device is set to. *Dark* here makes the site look as it always
did for everyone.

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

### Two sets of seven

The seven areas come twice, one set per design, behind two tabs *Dark* and
*Light* — the same tabs the page editor uses for its languages, and for the
same reason: it is one form, so switching a tab keeps everything that is
typed in and one *Save* stores both sets. Without JavaScript both sets stand
on the page, each under its own heading. After a failed save the tabs open
on the set that needs a look, and the other tab is marked if it needs one
too.

The colour table above is the **dark** set (`colors.<area>`). The **light**
set (`colors.light.<area>`) holds the same seven areas with its own built-in
values:

| Area | Light default |
| --- | --- |
| Primary | `#1e40af` |
| Secondary | `#3b82f6` |
| Accent | `#b45309` |
| Danger | `#b91c1c` |
| Success | `#15803d` |
| Background | `#f3f4f6` |
| Text | `#1f2937` |

The light design: a light grey ground with an almost white card lifted off
it, a deep blue for the menu and what a visitor acts on, a brighter blue for
the counters, a burnt amber for the word mark and the tags, a deep red for
danger and a leaf green for success, and a dark slate for the text. The
softness lives in the tints – the veils behind notices, chips, row highlights
and hover states – and in the pale frames.

They are separate on purpose. A colour picked against a dark ground is
rarely readable on a light one – the light blue the dark design uses for
actions would stand at 1.7:1 on the pale card – so the light set is chosen for its own
ground, not derived from the dark one. The frames around transparent buttons
are the exception the other way: on the light design they are the pastel of
their colour family, soft rather than 3:1, because the button they frame
carries readable text of its own. Nothing corrects what is typed in
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
step by hand. The same tool derives every shade and tint from the seven base
colours the way `src/Colors.php` does and insists the blocks carry those,
and that the two tables above name the same fourteen values. A re-tune of the
built-in palette starts with the seven base colours per scheme; the tool then
names every derived line that has not followed. The neutral veils and
shadows are not derived from anything and remain a decision made by hand.

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
