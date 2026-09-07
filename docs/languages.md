# Languages

English is the source language. Every text in the code is English, and that
English text is also the key (`msgid`). Translations are GNU gettext files in
`lang/<code>.po`. `src/PoFile.php` reads them with a small parser of its own,
so neither the gettext extension nor the intl extension is needed. There are
no compiled `.mo` files and no system locales.

## Choosing a language

Top right, a globe icon with the current language code opens the language
menu. The menu is a `<details>` element and works without JavaScript. It lists
every available language under its native name. The menu is only shown when
more than one language is available.

A click adds `?lang=<code>` to the current address. The application stores
the choice in the session and in a cookie, then redirects to the same address
without the parameter. The cookie is `songwunsch_lang` and is valid for one
year. It holds only the language code, no personal data. It carries the same
flags as the session cookie: `HttpOnly`, `SameSite=Lax`, `Secure` on HTTPS,
`path` limited to the base path.

The language of a request is picked in this order: the `?lang` parameter, the
session, the cookie, the browser's `Accept-Language` header, English. From
`Accept-Language` a regional tag such as `de-AT` first tries `de-at`, then
`de`. An unknown code falls back to English. The `<html lang>` attribute
carries the chosen code.

The choice also decides the language of the admins' pages and of the footer
line, see [Pages in several languages](pages.md#pages-in-several-languages).

## Fallback order

Under *Administration → Languages* (`/admin/languages`) admins arrange the
fallback order of the languages. It decides which version of a page (or of the
footer line) a reader gets when there is none in their own language: the first
language of the order that the page has.

The order is changed by dragging a row or with the four arrow buttons of every
row (to the top, one up, one down, to the bottom). The buttons work by keyboard
and without JavaScript. The order is stored in the `settings` table under
`pages_languages`, as codes separated by commas. A language that is not in the
stored order joins the end. Which languages exist is not set on this page;
that comes from the `lang/` folder. With a single language the page says there
is nothing to order.

## Adding a language

One file is enough:

```bash
cp lang/songwunsch.pot lang/fr.po      # file name = language code
```

The file name is the language code: two or three lower-case letters,
optionally followed by a hyphen and a region, for example `pt-br`. Fill in
three lines in the file header:

```
"Language: fr\n"
"Plural-Forms: nplurals=2; plural=(n > 1);\n"
"X-Native-Name: Français\n"
```

* `X-Native-Name` is the name shown in the language menu. Without it the menu
  shows the code.
* `Plural-Forms` is the plural rule in gettext notation. A small interpreter
  evaluates the expression; nothing is passed to `eval()`. Without this header
  `nplurals=2; plural=(n != 1);` applies.
* `Language` is for translation tools. The application takes the code from the
  file name.

Then translate the `msgstr` lines. On the next request the language appears in
the menu. A missing or empty translation falls back to the English text.
Entries marked `#, fuzzy` are skipped. Poedit and similar tools can edit the
file directly. Keep the header at the top of the file: only its first 4 KB
are read for the menu.

## Placeholders and contexts

Placeholders are written in curly braces (`{title}`, `{n}`). A translation may
reorder them. A few entries carry a `msgctxt`; the context tells them apart
from ordinary texts:

* `thousands separator` – the `,` between thousands (German `.`, French a
  space).
* `time format, PHP date()` (`H:i:s`) and `date format, PHP date()`
  (`M j, H:i`) – formats for PHP's `date()`. Here the format is translated,
  not a text.
* `role` – the role names *Admin*, *Editor*, *Moderator*.
* `language tab` – the labels of the language tabs in the page form.

## Template and completeness

`tools/extract-strings.php` collects every `t()` and `tn()` call with literal
strings from `index.php`, `src/`, `templates/` and `config/` into
`lang/songwunsch.pot`. It then reports for every `.po` file how many entries
are translated, missing and obsolete:

```bash
php tools/extract-strings.php          # write the template, print a report
php tools/extract-strings.php --check  # check only, exit code 1 on gaps
```

A `t()` call with a variable instead of a literal string prints a warning; it
cannot be extracted.

## In the code

* `t('Save')` translates a text.
* `t('Page {page} of {pages}', ['page' => 1, 'pages' => 3])` fills
  placeholders.
* `tn('{n} wish', '{n} wishes', $count)` picks the plural form. `{n}` is
  always available.
* The third parameter of `t()` and the fifth of `tn()` set the context.

Output goes through `Format::e()`, which escapes for HTML. Where a translation
deliberately contains HTML (a link, `<strong>`), the HTML is handed in as an
already escaped placeholder value, and the result is printed without a second
escaping.
