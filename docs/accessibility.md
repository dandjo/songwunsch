# Accessibility

## What is built in

* **Skip link.** The first element of every page is a link to the content
  (`#content`). It is hidden until it receives focus.
* **Focus ring.** Every focusable element shows a visible outline on
  `:focus-visible` (`assets/style.css`). Menus and switches have their own
  focus styles on top.
* **Tables.** Every table has a `<caption>` (visually hidden where the heading
  already says it) and `scope="col"` on its header cells. The repertoire and
  the wish list carry `aria-sort` on the sortable columns and say the next sort
  direction for screen readers.
* **Buttons and menus.** Icon-only buttons carry a text label for screen
  readers (a visually hidden span or `aria-label`): the move buttons, the
  password toggle, the help, language and account menus. The decorative icons
  are `aria-hidden`. The current page and the current room are marked with
  `aria-current`. Switches such as *View as guest* and the password toggle
  report their state with `aria-pressed`.
* **Keyboard.** Everything works by keyboard. Reordering (wish list, footer
  links, language order) has four buttons per row – to the top, one up, one
  down, to the bottom – as an alternative to drag & drop. The pop-out menus
  (`<details>`) close with Escape and give the focus back to their toggle.
  Tabs — the languages of a page or footer line, the two colour sets under
  *Interface* — move with the arrow keys. `/` jumps into
  the search field.
* **Without JavaScript.** The menus, the room switcher, the language menu, the
  reorder buttons and the name dialog work as plain HTML. JavaScript only adds
  drag & drop, pop-up messages, live updates and the modal dialog.
* **Announcements.** Messages after an action carry `role="status"`, errors
  `role="alert"`. Drag & drop reports *saved* or *failed* in a hidden live
  region. Live updates announce that a list has changed.
* **Forms.** Errors are tied to their fields with `aria-invalid` and
  `aria-describedby`; hints are linked the same way. Search and filter fields
  have visually hidden labels.
* **Language.** `<html lang>` carries the interface language. A page or footer
  line that falls back to another language marks that text with its own
  `lang` attribute.
* **Light and dark.** A menu in the header offers a light design, a dark one
  and *Follow my device*, per visitor, kept in a cookie; `prefers-color-scheme`
  is honoured for the third. Both built-in palettes are built to the contrast
  WCAG 2.2 AA asks for: 4.5:1 for text and links against the surface they sit
  on, 3:1 for the frames of buttons. `<html data-theme>` and
  `<meta name="color-scheme">` say which one is in use, so the browser's own
  furniture follows.
* **Motion and contrast modes.** `prefers-reduced-motion: reduce` switches
  animations and transitions off. `forced-colors: active` (Windows high
  contrast) gives buttons and links a visible border.

## What to check yourself

* **Contrast.** Admins can change the colours under *Interface*, as two sets
  of six – one per design. The form checks only that a value is a valid
  `#rrggbb` colour, never its contrast, and nothing is corrected behind your
  back: a light set is yours to choose against the pale ground. **Check both
  sets before going live.** A colour that reads well on black is usually
  unreadable on white, and the other way round.
* **Screen reader test.** A test with a screen reader and keyboard before
  going live is still worthwhile, in particular for the wish list and the room
  switcher.
