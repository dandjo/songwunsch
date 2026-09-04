# CKEditor 5 (bundled)

Browser build of [CKEditor 5](https://ckeditor.com/ckeditor-5/) 48.5.0 from the
npm package `ckeditor5`, used on the admin page that edits the footer pages
(`templates/footer_page.php`, wired up in `assets/app.js`). Licensed under the
GPL 2 or later (LICENSE.md, COPYING.GPL); the editor is configured with the
`GPL` licence key.

Files: `ckeditor5.umd.js` (the editor, defines `window.CKEDITOR`),
`ckeditor5.css` (its styles), `translations/<code>.umd.js` (the interface in
that language; a language without a file falls back to English). The source
map references are removed; otherwise the files are unchanged.

To update: download the new `ckeditor5-<version>.tgz` from npm, copy
`dist/browser/ckeditor5.umd.js`, `dist/browser/ckeditor5.css` and
`dist/translations/{de,fr}.umd.js` here, strip the `sourceMappingURL`
comments and raise the version here and in `config.php` (`version`).
