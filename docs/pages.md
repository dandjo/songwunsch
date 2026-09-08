# Pages and footer

Admins write **pages** – an imprint, FAQs, a privacy notice – and decide which
of them the **footer** links. Both are admin tasks in the *Administration*
menu.

## Pages

Pages are managed under *Administration → Pages* (`/admin/pages`, admins
only). The list shows every page with its title, address, languages and last
change. It can be searched by title or machine name and is paged like the
other lists. *Add page* opens the form; each row has *Edit* and *Delete*
buttons.

A page has three parts:

- A **machine name**. It becomes the address, `/pages/<name>`. Allowed are
  lower-case letters a–z, digits and single hyphens, 2 to 64 characters, like
  a room's machine name. While the field is empty, the browser proposes it
  from the title being typed, in whichever language. The hint below the field
  shows the resulting address. Changing the machine name of a saved page
  changes its address; links already handed out stop working.
- A **title** per language, at most 128 characters. It is the heading of the
  page and the text of its footer link.
- A **body** per language, written in the editor (see below). At most 200 000
  characters of HTML.

Every page is public under its address as soon as it is saved. It may link to
any other page by that address. The footer is not what makes a page
reachable: a page outside the footer is reached through its address alone.
Admins see an *Edit* button on the page itself.

Deleting a page removes it from the footer and deletes all its languages.

## The footer

The footer at the bottom of every screen links the pages the admins put
there. It is arranged under *Administration → Footer* (`/admin/footer`,
admins only). The page is built like a room's song picker: two columns, left
the pages the footer does not link, right the footer in its order. An arrow
moves a page across: → into the footer, ← out of it. A page taken out keeps
its address.

The order on the right is changed by dragging a row – the same drag & drop as
on the wish list, saved in the background – or with the four move buttons of
every row: to the top, up, down, to the bottom. The buttons work by keyboard
and without JavaScript.

Both columns are paged on their own (`page` for the left column, `rpage` for
the right). A drag reorders the rows of the shown page among the places they
hold; the rows on other pages keep theirs.

In the footer the page being read is marked (`aria-current="page"`). With no
page in the footer and no own line (see below) there is no footer at all.

Pages live in the `pages` table, their texts per language in
`page_translations` (see [Database](database.md)). `footer_position` on a
page says whether and where it is linked in the footer; `NULL` means not
linked.

## The editor

The body is written in **CKEditor 5**. The full editor on the page form
offers: headings (levels 2 to 4), paragraphs, bold, italic, underline,
strikethrough, subscript, superscript, inline code, remove formatting, links,
bulleted and numbered lists, quotes, tables, horizontal lines, undo and redo,
and a source view. Links to other sites open in a new tab. Without
JavaScript the text area shows the HTML itself.

The editor's interface follows the site's language when a translation file
is bundled; otherwise it is English. Its colours follow the site's colours
through its custom properties, see the end of `assets/style.css` and
[Interface](interface.md).

CKEditor is bundled, not loaded from a CDN, so visitors' browsers talk to no
third party. `assets/vendor/ckeditor5/` holds the browser build of CKEditor 5
version 48.5.0: `ckeditor5.umd.js` (about 1.9 MB), `ckeditor5.css`, and the
German and French interface translations under `translations/`. Only the page
form and the Footer page load it; visitors never do.

CKEditor 5 is licensed under the GPL 2 or later; the editor is configured
with the `GPL` licence key. That is compatible with this project's GPL 3
(`LICENSE`). The licence files (`LICENSE.md`, `COPYING.GPL`) sit next to the
editor. The `README.md` there says how to update it.

## What the editor keeps

The body is stored as HTML, but not as it arrives. On save `src/Html.php`
reduces it to a fixed set of elements:

- Text structure: `p`, `br`, `h2`, `h3`, `h4`, `hr`, `blockquote`, `pre`
- Inline marks: `strong`, `b`, `em`, `i`, `u`, `s`, `sub`, `sup`, `code`
- Lists: `ul`, `ol`, `li`
- Links: `a`
- Tables: `table`, `thead`, `tbody`, `tfoot`, `tr`, `th`, `td`
- `figure` and `figcaption`

`h1` becomes `h2`, because the page's title is the `h1`. `h5` and `h6`
become `h4`.

Everything else goes:

- Unknown elements lose their tags and keep their text.
- Scripts, styles, frames, forms and their fields, embedded objects, SVG and
  MathML go with their content.
- Pictures are removed.
- HTML comments are removed.
- All attributes are dropped, except: `href` and `target` on links, `start`
  on numbered lists, `colspan` and `rowspan` on cells. `target` is only kept
  as `target="_blank"`, and such a link gets `rel="noopener"`. Numbers must
  have one to four digits.

A link may point to a web address (`http://`, `https://`), a mail address
(`mailto:`), a phone number (`tel:`), an anchor (`#…`), a path of this site
(`/…`) or a relative path. Every other scheme – `javascript:`, `data:`,
`vbscript:` – is refused, as is a protocol-relative `//host`. A link without
a usable address becomes plain text.

What visitors get is exactly what is stored. It is printed unescaped inside
`<div class="prose">`.

## Pages in several languages

A page is its address plus a title and a body **per language of the
switcher**. Every language is equal, none is the original. The form shows a
row of tabs, one per language (English, Deutsch, Français, … whatever
`lang/*.po` provides, see [Languages](languages.md)), from the very first
save on. Each tab holds a title and a content field.

The rules:

- A language with both fields empty is not part of the page. A body with
  only blank space counts as empty.
- A language with only one of the two fields filled in is an error.
- A page needs at least one language.

Tabs mark their state: a tick where the page is saved in that language,
*missing* where it is not yet, an alert where the fields need a look. After a
failed save the form opens on the tab with the error. *Remove <language>* on
a tab takes that language off the saved page at once, after a confirmation.
Nothing else of the form is saved by it, and the last language of a page
cannot be removed. Without JavaScript all panels are on the page, each headed
by its language.

Readers get a page in the language chosen in the switcher. Where the page
lacks it, they get the first language of the **fallback order** the page has.
The order is set under *Administration → Languages* (`/admin/languages`), by
dragging a row or with its arrow buttons, the same as the footer's order. A
language that arrives later (a new `.po` file) joins the end. When a page
falls back to another language, the title and body carry `lang="…"` so screen
readers and hyphenation know.

The footer links, the Pages list and the admins' messages pick the title the
same way. On the public page, an admin's *Edit* button opens the tab of the
admin's interface language – the one to fill in when the page fell back to
another. The Pages list shows a chip per language and page, in the fallback
order: filled where the page has it, dashed where not. Each chip leads to
that tab of the form.

In the database, `pages` holds the address and the footer position,
`page_translations` one row per page and language (`page_id`, `lang`,
`title`, `body`). The fallback order is `pages_languages` in the `settings`
table, codes separated by commas.

## Your own line

The operator's own line – credits, a link to the operator's site – is written
on the Footer page, under *Your own line* below the picker. It is printed
below the page links on every screen.

The line uses a compact version of the editor: bold, italic, links, remove
formatting, undo and redo, and a source view. No headings, lists or tables.

Like a page, the line is written **per language**, one tab each. Readers get
the line in their own language, otherwise in the first language of the
fallback order that has one, with `lang="…"` on it. A language left empty has
no line of its own. Leave every tab empty for no line at all.

Saving reduces the HTML to what the pages may contain (see *What the editor
keeps*). The values live in the `settings` table as `footer_html.<code>`, one
entry per language. An empty language has no entry.
