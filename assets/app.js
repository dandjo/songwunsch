/*
 * Small enhancements -- the application works without JavaScript as well:
 * sorting, searching, wishing and deleting run through plain links/forms.
 *
 * Two kinds of bindings: those that live on the document and look the page
 * up when they fire (popouts, the "/" key, the name dialog, the soft
 * navigation), registered once; and those bound to elements of the page
 * (confirmations, row click, drag & drop, room filter), gathered in
 * enhance() so they can be set again after the page's content has been
 * swapped -- by the soft navigation or by the live update, both below.
 */
(function () {
    'use strict';

    // True while a row is being dragged: the live update waits until the
    // drag is over so the list is not swapped under the pointer.
    var dragging = false;

    // ---- Document-level bindings, once ------------------------------------

    // First visit: the name dialog. The inline script in the layout has
    // already made it modal; here Escape counts as "Not now" -- the skip
    // form posts, so the question is not repeated on the next page.
    document.addEventListener('cancel', function (event) {
        var namebox = event.target.closest ? event.target.closest('dialog[data-namebox]') : null;
        var skip = namebox ? namebox.querySelector('form[data-name-skip]') : null;
        if (skip) {
            event.preventDefault();
            skip.submit();
        }
    }, true);

    // Header popouts (help, language, account, room switcher) and the sort
    // menu on phones are <details> and work without this; with it they also close
    // on a click elsewhere or on Escape, so a menu does not stay open while
    // one uses the page.
    // A <details> nested in another (Administration inside the account menu)
    // follows its parent and is left alone: it stands open on the admin
    // pages and must still be open when the menu is opened again.
    var popouts = function () {
        return [].filter.call(document.querySelectorAll('.dome details[open], .sortbar details[open]'), function (details) {
            return details.parentElement.closest('details') === null;
        });
    };
    document.addEventListener('click', function (event) {
        popouts().forEach(function (details) {
            if (!details.contains(event.target)) {
                details.open = false;
            }
        });
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            popouts().forEach(function (details) {
                details.open = false;
                var toggle = details.querySelector('summary');
                if (toggle && details.contains(document.activeElement)) {
                    toggle.focus();
                }
            });
        }

        // "/" jumps into the search field.
        var search = document.getElementById('q');
        var tag = (event.target.tagName || '').toLowerCase();
        if (search && event.key === '/' && tag !== 'input' && tag !== 'textarea' && !event.metaKey && !event.ctrlKey) {
            event.preventDefault();
            search.focus();
            search.select();
        }
    });

    // ---- Tab bar: stacked as soon as the tabs would wrap --------------------

    // On phones (up to 560 px, the width the CSS fallback uses as well) the
    // page tabs always stack icon over word like an app's tab bar, with the
    // room switcher on a row of its own above them -- a fixed layout, so the
    // bar does not flip between the two forms when a counter grows or the
    // room changes. Above that the tabs stand side by side while they fit on
    // one row and stack otherwise -- decided by measuring, not by a fixed
    // width, so any language, number of tabs and font size gets the right
    // layout. The room switcher shares the tabs' row while everything fits;
    // as soon as something has to give way, it takes a row of its own above
    // the tabs (.nav--rows), and only if the tabs still do not fit on that
    // second row do they stack. The row is measured in its side-by-side form
    // each time.
    var fitTabs = (function () {
        var pending = false;
        var phone = window.matchMedia('(max-width: 560px)');
        var measure = function () {
            pending = false;
            var nav = document.querySelector('.nav');
            if (!nav) {
                return;
            }
            if (phone.matches) {
                nav.classList.remove('nav--inline');
                nav.classList.add('nav--stacked', 'nav--rows');
                return;
            }
            nav.classList.remove('nav--stacked', 'nav--rows');
            nav.classList.add('nav--inline');
            var visible = function (el) {
                return getComputedStyle(el).display !== 'none';
            };
            var tabs = [].filter.call(nav.children, function (el) {
                return !el.classList.contains('roomswitch') && visible(el);
            });
            if (tabs.length === 0) {
                return;
            }
            var top = function (el) {
                return el.getBoundingClientRect().top;
            };
            var oneRow = function () {
                var first = top(tabs[0]);
                return tabs.every(function (tab) {
                    return Math.abs(top(tab) - first) <= 1;
                });
            };
            var room = nav.querySelector(':scope > .roomswitch');
            if (oneRow() && !(room && visible(room) && Math.abs(top(room) - top(tabs[0])) > 1)) {
                return;
            }
            // The switcher moves to its own row; the tabs get the full width.
            nav.classList.add('nav--rows');
            if (!oneRow()) {
                nav.classList.remove('nav--inline');
                nav.classList.add('nav--stacked');
            }
        };
        var schedule = function () {
            if (!pending) {
                pending = true;
                requestAnimationFrame(measure);
            }
        };
        window.addEventListener('resize', schedule);
        return measure;
    }());

    // ---- Bindings on the page's elements ----------------------------------

    function enhance(root) {
        // The tab bar is part of the header; measure it whenever the header
        // is (re)drawn.
        if (root === document || root.querySelector('.nav')) {
            fitTabs();
        }
        // Room switcher: the filter field hides entries that do not contain
        // the typed text. Purely progressive -- without JavaScript the full
        // list shows.
        root.querySelectorAll('input[data-roomfilter]').forEach(function (field) {
            var panel = field.closest('.roomswitch__panel');
            var items = panel ? panel.querySelectorAll('.roomswitch__menu li') : [];
            var groups = panel ? panel.querySelectorAll('[data-roomgroup]') : [];
            var none = panel ? panel.querySelector('[data-roomfilter-empty]') : null;
            var details = field.closest('details');

            field.addEventListener('input', function () {
                var needle = field.value.trim().toLowerCase();
                var shown = 0;
                items.forEach(function (li) {
                    var hit = needle === '' || li.textContent.toLowerCase().indexOf(needle) !== -1;
                    li.hidden = !hit;
                    if (hit) { shown++; }
                });
                // A group ("Your rooms") with no match left disappears with its title.
                groups.forEach(function (group) {
                    group.hidden = group.querySelector('li:not([hidden])') === null;
                });
                if (none) { none.hidden = shown > 0; }
            });

            // Focus the field as soon as the menu opens; reset when it closes.
            if (details) {
                details.addEventListener('toggle', function () {
                    if (details.open) {
                        field.focus();
                    } else if (field.value !== '') {
                        field.value = '';
                        field.dispatchEvent(new Event('input'));
                    }
                });
            }
        });

        // A new room's or page's machine name is proposed from its display
        // name or title: while the machine name field is empty -- or holds
        // nothing but what was proposed -- it follows the name, reduced to
        // the address alphabet ("Sommerfest 2026 – Wiener Straße" becomes
        // "sommerfest-2026-wiener-strasse"). A machine name that is already
        // there (an existing room or page, or typed by hand) is left alone;
        // emptying the field makes it follow again. data-slug-from names the
        // source field(s) by id -- a page has a title per language, whichever
        // one is being typed in proposes.
        root.querySelectorAll('[data-slug-from]').forEach(function (slug) {
            if (slug.hasAttribute('data-bound-from')) { return; }
            var sources = slug.getAttribute('data-slug-from').split(/\s+/).map(function (id) {
                return document.getElementById(id);
            }).filter(Boolean);
            if (sources.length === 0) { return; }
            slug.setAttribute('data-bound-from', '1');
            var max = parseInt(slug.getAttribute('maxlength'), 10) || 64;
            var following = slug.value === '';
            var proposing = false;
            var slugify = function (text) {
                var out = text.toLowerCase()
                    .replace(/ä/g, 'ae').replace(/ö/g, 'oe').replace(/ü/g, 'ue').replace(/ß/g, 'ss')
                    .replace(/æ/g, 'ae').replace(/ø/g, 'oe').replace(/œ/g, 'oe');
                if (out.normalize) {
                    out = out.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
                }
                out = out.replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
                return out.slice(0, max).replace(/-+$/, '');
            };
            sources.forEach(function (name) {
                name.addEventListener('input', function () {
                    if (!following) { return; }
                    proposing = true;
                    slug.value = slugify(name.value);
                    slug.dispatchEvent(new Event('input'));
                    proposing = false;
                });
            });
            slug.addEventListener('input', function () {
                if (!proposing) { following = slug.value === ''; }
            });
        });

        // Machine names (rooms, pages): the address in the hint below the
        // field follows what is typed, lower-cased like the server stores
        // it; while the field is empty the example stands in.
        root.querySelectorAll('[data-slug-preview]').forEach(function (out) {
            var field = document.getElementById(out.getAttribute('data-slug-preview'));
            if (!field || out.hasAttribute('data-bound')) { return; }
            out.setAttribute('data-bound', '1');
            var base = out.getAttribute('data-slug-base') || '';
            var example = out.getAttribute('data-slug-example') || '';
            var render = function () {
                var slug = field.value.trim().toLowerCase();
                out.textContent = base + (slug !== '' ? slug : example);
                out.classList.toggle('is-example', slug === '');
            };
            field.addEventListener('input', render);
            render();
        });

        // Colours: the colour picker and the hex field beside it follow each
        // other; "Default" empties the field, which means the built-in
        // colour. Picker and button appear only here, so without JavaScript
        // the hex field stands alone.
        root.querySelectorAll('[data-colour]').forEach(function (box) {
            var text = box.querySelector('input[type="text"]');
            var picker = box.querySelector('input[type="color"]');
            var reset = box.querySelector('[data-colour-reset]');
            if (!text || !picker || picker.hasAttribute('data-bound')) { return; }
            picker.setAttribute('data-bound', '1');
            var fallback = picker.getAttribute('data-default') || '#000000';
            var expand = function (value) {
                var m = /^#?([0-9a-f]{3}|[0-9a-f]{6})$/i.exec(value.trim());
                if (!m) { return null; }
                var h = m[1].length === 3 ? m[1].replace(/./g, '$&$&') : m[1];
                return '#' + h.toLowerCase();
            };
            var follow = function () { picker.value = expand(text.value) || fallback; };
            picker.hidden = false;
            picker.addEventListener('input', function () { text.value = picker.value; });
            text.addEventListener('input', follow);
            if (reset) {
                reset.hidden = false;
                reset.addEventListener('click', function () {
                    text.value = '';
                    follow();
                    text.focus();
                });
            }
            follow();
        });

        // Password fields: the eye shows the typed password and hides it
        // again. The button is rendered hidden and only appears here, so
        // without JavaScript nothing dangles beside the field.
        root.querySelectorAll('[data-reveal]').forEach(function (box) {
            var field = box.querySelector('input');
            var toggle = box.querySelector('.password__toggle');
            if (!field || !toggle || toggle.hasAttribute('data-bound')) { return; }
            toggle.setAttribute('data-bound', '1');
            toggle.hidden = false;
            toggle.addEventListener('click', function () {
                var show = field.type === 'password';
                field.type = show ? 'text' : 'password';
                toggle.setAttribute('aria-pressed', show ? 'true' : 'false');
                toggle.setAttribute('aria-label', toggle.getAttribute(show ? 'data-hide' : 'data-show'));
                field.focus();
            });
        });

        // A role that includes others (admin): ticking it ticks and locks the
        // named boxes; unticking frees them again. The server derives the
        // same roles, so without JavaScript nothing is lost.
        root.querySelectorAll('input[type="checkbox"][data-implies]').forEach(function (box) {
            var names = box.getAttribute('data-implies').split(/\s+/);
            var apply = function () {
                names.forEach(function (name) {
                    var other = box.form ? box.form.elements[name] : null;
                    if (!other || other.type !== 'checkbox') { return; }
                    if (box.checked) { other.checked = true; }
                    other.disabled = box.checked;
                });
            };
            box.addEventListener('change', apply);
            apply();
        });

        // Footer pages: CKEditor takes the place of the content textarea
        // (Administration -> Footer -> Edit). The bundle in
        // assets/vendor/ckeditor5 is loaded by the layout on that page only
        // and defines window.CKEDITOR; the translation file, when bundled,
        // registers itself under window.CKEDITOR_TRANSLATIONS. The editor
        // writes its HTML back into the textarea when the form is submitted;
        // without JavaScript the textarea shows the HTML as it is, and the
        // server reduces whatever arrives to the allowed tags anyway.
        root.querySelectorAll('textarea[data-editor]').forEach(function (field) {
            var CK = window.CKEDITOR;
            if (!CK || !CK.ClassicEditor || field.hasAttribute('data-bound')) { return; }
            field.setAttribute('data-bound', '1');

            var lang = field.getAttribute('data-editor-lang') || 'en';
            var translated = window.CKEDITOR_TRANSLATIONS && window.CKEDITOR_TRANSLATIONS[lang];
            // The footer line gets the compact variant: text with bold,
            // italic and links, no headings, lists or tables.
            var compact = field.hasAttribute('data-editor-compact');

            CK.ClassicEditor.create(field, compact ? {
                licenseKey: 'GPL',
                language: translated ? lang : 'en',
                placeholder: field.getAttribute('data-editor-placeholder') || '',
                plugins: [CK.Essentials, CK.Paragraph, CK.Bold, CK.Italic, CK.Link, CK.RemoveFormat, CK.PasteFromOffice, CK.SourceEditing],
                toolbar: { items: ['bold', 'italic', 'link', 'removeFormat', '|', 'undo', 'redo', '|', 'sourceEditing'], shouldNotGroupWhenFull: false },
                link: { defaultProtocol: 'https://', addTargetToExternalLinks: true }
            } : {
                licenseKey: 'GPL',
                language: translated ? lang : 'en',
                placeholder: field.getAttribute('data-editor-placeholder') || '',
                plugins: [
                    CK.Essentials, CK.Paragraph, CK.Heading, CK.Bold, CK.Italic, CK.Underline,
                    CK.Strikethrough, CK.Subscript, CK.Superscript, CK.Code, CK.RemoveFormat,
                    CK.Link, CK.List, CK.BlockQuote, CK.HorizontalLine,
                    CK.Table, CK.TableToolbar, CK.Autoformat, CK.PasteFromOffice, CK.SourceEditing
                ],
                toolbar: {
                    items: [
                        'heading', '|',
                        'bold', 'italic', 'underline', 'strikethrough', 'subscript', 'superscript', 'code', 'removeFormat', '|',
                        'link', 'bulletedList', 'numberedList', 'blockQuote', 'insertTable', 'horizontalLine', '|',
                        'undo', 'redo', '|', 'sourceEditing'
                    ],
                    shouldNotGroupWhenFull: false
                },
                // The page's own title is the h1; the content starts below it.
                heading: {
                    options: [
                        { model: 'paragraph', title: 'Paragraph', 'class': 'ck-heading_paragraph' },
                        { model: 'heading2', view: 'h2', title: 'Heading 2', 'class': 'ck-heading_heading2' },
                        { model: 'heading3', view: 'h3', title: 'Heading 3', 'class': 'ck-heading_heading3' },
                        { model: 'heading4', view: 'h4', title: 'Heading 4', 'class': 'ck-heading_heading4' }
                    ]
                },
                link: {
                    defaultProtocol: 'https://',
                    addTargetToExternalLinks: true
                },
                table: {
                    contentToolbar: ['tableColumn', 'tableRow', 'mergeTableCells']
                }
            }).then(function (editor) {
                // The editor is created after the browser's own validation
                // marked the field; the label now points at the editing area.
                var label = document.querySelector('label[for="' + field.id + '"]');
                var editable = editor.ui.view.editable.element;
                if (label && editable) {
                    editable.setAttribute('aria-labelledby', label.id || (label.id = field.id + '-label'));
                }
            }).catch(function (error) {
                // The plain textarea stays usable.
                field.removeAttribute('data-bound');
                if (window.console) { console.error(error); }
            });
        });

        // Confirmation before deleting -- a whole form, or one button in a
        // form that otherwise saves (a page's "Remove <language>").
        // The result of an action (the flash with data-toast) is lifted out
        // of the content into the pop-up stack at the bottom edge, so it is
        // seen wherever the page is scrolled to, and goes away after the
        // seconds set under Interface (0: until dismissed), errors included.
        // Pointer or focus on it holds it. Without JavaScript the message
        // stands at the top of the content.
        root.querySelectorAll('[data-toast]').forEach(toast);

        // The QR code page: a print button that exists only with JavaScript
        // (the browser's own print command does the same).
        root.querySelectorAll('[data-print]').forEach(function (button) {
            button.hidden = false;
            button.addEventListener('click', function () { window.print(); });
        });

        root.querySelectorAll('form[data-confirm]').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (!window.confirm(form.getAttribute('data-confirm'))) {
                    event.preventDefault();
                }
            });
        });
        root.querySelectorAll('button[data-confirm]').forEach(function (button) {
            button.addEventListener('click', function (event) {
                if (!window.confirm(button.getAttribute('data-confirm'))) {
                    event.preventDefault();
                }
            });
        });

        // A click on a song row triggers the wish (the button remains the
        // real, keyboard- and screen-reader-accessible way).
        root.querySelectorAll('table[data-rowclick] tbody tr').forEach(function (row) {
            var button = row.querySelector('.wish-button');
            if (!button) {
                return;
            }

            // The class carries cursor, hover and pressed state (style.css) --
            // only rows that really react get them.
            row.classList.add('is-clickable');
            row.addEventListener('click', function (event) {
                if (event.target.closest('a, button, input, label')) {
                    return;
                }
                button.click();
            });

            // Touch has no hover: light the card up while the finger is down
            // and keep it lit after the tap until the content comes back
            // with the result.
            var press = function () { row.classList.add('is-pressed'); };
            var release = function () { row.classList.remove('is-pressed'); };
            row.addEventListener('pointerdown', press);
            row.addEventListener('pointercancel', release);
            row.addEventListener('pointerleave', release);
            row.addEventListener('pointerup', function (event) {
                if (event.target.closest('a:not(.wish-button), .row-actions__pair, input, label')) {
                    release();
                }
            });
        });

        // Wish list, footer and the languages' fallback order: order by drag
        // & drop. Without JavaScript the arrow buttons remain the way to the
        // same result -- they are also the keyboard access.
        var board = root.querySelector('table[data-reorder]');
        if (board) {
            reorderable(board);
        }

        // The page form: one panel per language, shown one at a time behind
        // tabs. Without JavaScript the anchors lead to the panels, which are
        // all on the page.
        root.querySelectorAll('[data-tabs]').forEach(tabbed);
    }

    // ---- Pop-up messages ---------------------------------------------------
    function toast(message) {
        var stack = document.getElementById('toasts');
        if (!stack) {
            stack = document.createElement('div');
            stack.id = 'toasts';
            stack.className = 'toasts';
            document.body.appendChild(stack);
        }

        var seconds = parseInt(document.body.getAttribute('data-toast-sec') || '5', 10);
        var timer = null;

        var leave = function () {
            clearTimeout(timer);
            timer = null;
            message.classList.add('toast--leaving');
            // The fade takes .25s (style.css); remove after it, at once
            // when reduced motion cuts it short (animationend still fires).
            var done = function () {
                if (message.parentNode) {
                    message.parentNode.removeChild(message);
                }
            };
            message.addEventListener('animationend', done, { once: true });
            setTimeout(done, 400);
        };
        var hold = function () { clearTimeout(timer); timer = null; };
        var arm = function () {
            if (seconds > 0 && timer === null) {
                timer = setTimeout(leave, seconds * 1000);
            }
        };

        var close = document.createElement('button');
        close.type = 'button';
        close.className = 'toast__close';
        close.setAttribute('aria-label', document.body.getAttribute('data-msg-dismiss') || 'Dismiss');
        close.textContent = '×';
        close.addEventListener('click', leave);

        message.removeAttribute('data-toast');
        message.classList.add('toast');
        message.appendChild(close);
        message.addEventListener('mouseenter', hold);
        message.addEventListener('mouseleave', arm);
        message.addEventListener('focusin', hold);
        message.addEventListener('focusout', function (event) {
            if (!message.contains(event.relatedTarget)) {
                arm();
            }
        });
        stack.appendChild(message);
        page.announce(message.textContent.replace(/×$/, '').trim());
        arm();
    }

    // ---- Tabs over several versions of the same fields ---------------------
    // Used by the languages of a page and its footer line, and by the two
    // colour sets under Interface: one form, one panel per version. The
    // anchors become tabs (role=tab, arrow keys move between them), the
    // fieldsets tabpanels; only the selected panel is shown. Without this the
    // anchors are plain links to the panels, which all stand on the page.
    // The tab to start on comes from the address (the panel's own id in the
    // hash), otherwise from the server (data-tabs-active: the version whose
    // fields need a look).
    function tabbed(box) {
        var tabs = Array.prototype.slice.call(box.querySelectorAll('[data-tab]'));
        var panels = Array.prototype.slice.call(box.querySelectorAll('[data-panel]'));
        if (tabs.length < 2 || panels.length !== tabs.length) {
            return;
        }
        var list = tabs[0].closest('ul');
        var panelOf = function (code) {
            return panels.find(function (panel) { return panel.getAttribute('data-panel') === code; });
        };

        box.classList.add('is-tabbed');
        if (list) {
            list.setAttribute('role', 'tablist');
            list.querySelectorAll('li').forEach(function (li) { li.setAttribute('role', 'presentation'); });
        }

        var select = function (code, focus) {
            tabs.forEach(function (tab) {
                var own = tab.getAttribute('data-tab') === code;
                tab.setAttribute('aria-selected', own ? 'true' : 'false');
                tab.setAttribute('tabindex', own ? '0' : '-1');
                if (own && focus) {
                    tab.focus();
                }
            });
            panels.forEach(function (panel) {
                panel.hidden = panel.getAttribute('data-panel') !== code;
            });
        };

        tabs.forEach(function (tab) {
            var code = tab.getAttribute('data-tab');
            var panel = panelOf(code);
            tab.setAttribute('role', 'tab');
            tab.setAttribute('aria-controls', panel.id);
            panel.setAttribute('role', 'tabpanel');
            panel.setAttribute('aria-labelledby', tab.id || (tab.id = 'tab-' + panel.id));

            tab.addEventListener('click', function (event) {
                event.preventDefault();
                select(code, false);
                if (window.history && history.replaceState) {
                    history.replaceState(null, '', '#' + panel.id);
                }
            });
            tab.addEventListener('keydown', function (event) {
                var index = tabs.indexOf(tab);
                var next = null;
                if (event.key === 'ArrowRight' || event.key === 'ArrowDown') { next = tabs[(index + 1) % tabs.length]; }
                if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') { next = tabs[(index - 1 + tabs.length) % tabs.length]; }
                if (event.key === 'Home') { next = tabs[0]; }
                if (event.key === 'End') { next = tabs[tabs.length - 1]; }
                if (next) {
                    event.preventDefault();
                    select(next.getAttribute('data-tab'), true);
                }
            });
        });

        // A field the browser refuses to submit (maxlength, pattern) may sit
        // in a hidden panel: bring that panel forward so the message shows.
        panels.forEach(function (panel) {
            panel.addEventListener('invalid', function () {
                select(panel.getAttribute('data-panel'), false);
            }, true);
        });

        // The panel the address names, whatever its id is called -- a click
        // writes that id into the hash below, so a reload comes back to it.
        var named = panels.find(function (panel) { return '#' + panel.id === location.hash; });
        var start = named ? named.getAttribute('data-panel') : box.getAttribute('data-tabs-active');
        select(panelOf(start) ? start : tabs[0].getAttribute('data-tab'), false);
    }

    function reorderable(board) {
        var body = board.querySelector('tbody');
        var status = document.getElementById('reorder-status');
        var dragged = null;

        board.classList.add('has-dragdrop');

        var announce = function (text) {
            if (status) {
                status.textContent = text;
            }
        };

        // A paged list shows one page of the whole: data-reorder-offset is
        // the number of rows before this page, data-reorder-total the size
        // of the whole list, so ranks and end states follow the list, not
        // the page.
        var offset = parseInt(board.getAttribute('data-reorder-offset') || '0', 10) || 0;
        var total = parseInt(board.getAttribute('data-reorder-total') || '', 10);

        var renumber = function () {
            var rows = body.querySelectorAll('tr');
            var last = isNaN(total) ? rows.length - 1 : total - 1 - offset;
            rows.forEach(function (row, index) {
                var rank = row.querySelector('.rank');
                if (rank) {
                    rank.lastChild.textContent = String(offset + index + 1);
                }
                // Up and to the top are pointless on the first row of the
                // list, down and to the bottom on its last.
                row.querySelectorAll('.move button[data-move]').forEach(function (button) {
                    var dir = button.getAttribute('data-move');
                    button.disabled = (dir === 'up' || dir === 'top')
                        ? offset + index === 0
                        : index === last;
                });
            });
        };

        var persist = function () {
            var ids = Array.prototype.map.call(body.querySelectorAll('tr'), function (row) {
                return row.getAttribute('data-id');
            });

            // Each list carries the address its order is saved at
            // (data-reorder-url): the wish list's, the footer's, the
            // languages'. Of a paged list only this page's ids go: the
            // server places them where these entries stood and leaves the
            // other pages alone.
            var payload = new URLSearchParams();
            payload.set('csrf', board.getAttribute('data-csrf'));
            payload.set('order', ids.join(','));

            fetch(board.getAttribute('data-reorder-url'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'fetch',
                    'Accept': 'application/json'
                },
                body: payload.toString(),
                credentials: 'same-origin'
            }).then(function (response) {
                return response.json();
            }).then(function (data) {
                if (data && data.ok) {
                    announce(board.getAttribute('data-msg-saved') || 'Order saved.');
                    // Our own change: adopt the new revision right away so
                    // the poll does not reload what we already show.
                    live.check();
                } else {
                    announce((data && data.error) || board.getAttribute('data-msg-failed') || 'The order could not be saved.');
                    board.classList.add('reorder-failed');
                }
            }).catch(function () {
                announce(board.getAttribute('data-msg-offline') || 'The order could not be saved – please reload the page.');
                board.classList.add('reorder-failed');
            });
        };

        body.addEventListener('dragstart', function (event) {
            var row = event.target.closest('tr');
            if (!row) {
                return;
            }
            dragged = row;
            dragging = true;
            row.classList.add('is-dragging');
            event.dataTransfer.effectAllowed = 'move';
            // Firefox only starts the drag when data has been set.
            event.dataTransfer.setData('text/plain', row.getAttribute('data-id'));
        });

        body.addEventListener('dragover', function (event) {
            if (!dragged) {
                return;
            }
            event.preventDefault();
            event.dataTransfer.dropEffect = 'move';

            var over = event.target.closest('tr');
            if (!over || over === dragged) {
                return;
            }

            var box = over.getBoundingClientRect();
            var below = (event.clientY - box.top) > (box.height / 2);
            body.insertBefore(dragged, below ? over.nextSibling : over);
        });

        body.addEventListener('drop', function (event) {
            event.preventDefault();
        });

        body.addEventListener('dragend', function () {
            if (!dragged) {
                return;
            }
            dragged.classList.remove('is-dragging');
            dragged = null;
            dragging = false;
            renumber();
            persist();
        });
    }

    // ---- Soft navigation: exchange the content, keep the page -------------
    // Sorting, paging and searching are links and GET forms that lead back
    // to the page they stand on; every action -- wish, move, add, remove,
    // delete, save, close a room -- is a form that posts and is redirected
    // to a page of ours. Here they are fetched instead and only the page's
    // content (.cabinet) is exchanged: no white flash, the scroll position
    // stays, the focus returns to the control that was used, and the
    // address bar follows so back, forward and reload keep working. A link
    // to another page (a form to fill in, an admin page) loads the normal
    // way, so do the header's menus and the name dialog, a page that needs
    // scripts this one has not loaded (the editor), and everything when a
    // request fails. Without JavaScript the same links and forms simply
    // reload.
    var page = (function () {
        var loading = false;
        // The address the content belongs to (without the hash), so that a
        // popstate for a hash jump is told apart from back/forward.
        var shown = window.location.href.split('#')[0];

        // A live region for announcements, once, outside the swapped area.
        var status = document.createElement('p');
        status.id = 'live-status';
        status.className = 'sr-only';
        status.setAttribute('role', 'status');
        document.body.appendChild(status);
        var announce = function (text) { status.textContent = text; };

        // Same page: only the query may differ.
        var samePage = function (href) {
            var url = new URL(href, window.location.href);
            return url.origin === window.location.origin && url.pathname === window.location.pathname;
        };

        // Where the focus should return after the swap: the element with the
        // same id, else the n-th element of the same class -- the sort
        // switches and move buttons have no ids but keep their place. The
        // element is the one that has the focus, else the control that was
        // used (a mouse click does not focus a link in every browser).
        var remember = function (used) {
            var active = document.activeElement;
            var element = used || active;
            // The focus stays where it is when it sits in the form that was
            // sent (Enter in the search field, not its button).
            if (active && active !== document.body && used && used.form && used.form.contains(active)) {
                element = active;
            }
            if (!element || element === document.body) {
                return null;
            }
            if (element.id) {
                return { id: element.id };
            }
            var name = (element.className || '').split(/\s+/).filter(function (cls) {
                return cls !== '' && cls.indexOf('is-') !== 0 && cls.indexOf('sort--') !== 0;
            })[0];
            if (!name) {
                return null;
            }
            var selector = element.tagName.toLowerCase() + '.' + name;
            return { selector: selector, index: Array.prototype.indexOf.call(document.querySelectorAll(selector), element) };
        };
        var restore = function (mark) {
            if (!mark) {
                return;
            }
            var element = null;
            if (mark.id) {
                element = document.getElementById(mark.id);
            } else {
                // The n-th of its kind; when that one is gone (the row was
                // deleted), the last one that is left.
                var alike = document.querySelectorAll(mark.selector);
                element = alike[Math.min(mark.index, alike.length - 1)] || null;
            }
            if (element && element.disabled) {
                // A move button that became pointless (the row is at the
                // top now): the nearest one of its group that still works.
                var group = element.closest('.move');
                element = group ? group.querySelector('button:not([disabled])') : null;
            }
            if (!element) {
                // Nothing left to return to: the content itself, as after
                // a page load, so the keyboard does not start over at the top.
                element = document.getElementById('content');
                if (element) {
                    element.setAttribute('tabindex', '-1');
                }
            }
            if (element) {
                element.focus({ preventScroll: true });
            }
        };

        // Does the fetched page need a script or stylesheet this one has not
        // loaded (the editor's bundle)? Then it must load the normal way.
        var needsMore = function (fresh) {
            var have = {};
            document.querySelectorAll('script[src], link[rel="stylesheet"]').forEach(function (el) {
                have[el.src || el.href] = true;
            });
            return Array.prototype.some.call(fresh.querySelectorAll('script[src], link[rel="stylesheet"]'), function (el) {
                return !have[el.src || el.href];
            });
        };

        // The body's data attributes belong to the page: the live address,
        // its two tokens, the doorbell, the interval, the messages. Taken
        // over from the fetched document.
        var adoptBodyData = function (fresh) {
            Array.prototype.slice.call(document.body.attributes).forEach(function (attr) {
                if (attr.name.indexOf('data-') === 0) {
                    document.body.removeAttribute(attr.name);
                }
            });
            Array.prototype.slice.call(fresh.body.attributes).forEach(function (attr) {
                if (attr.name.indexOf('data-') === 0) {
                    document.body.setAttribute(attr.name, attr.value);
                }
            });
        };

        // The colours set under Interface live in a <style id="colors"> in
        // the head, outside the swapped area. Taken over from the fetched
        // document -- added, replaced or removed -- so a save shows at once.
        var adoptColors = function (fresh) {
            var current = document.getElementById('colors');
            var wanted = fresh.getElementById('colors');
            if (wanted && current) {
                if (current.textContent !== wanted.textContent) {
                    current.textContent = wanted.textContent;
                }
            } else if (wanted) {
                document.head.appendChild(document.importNode(wanted, true));
            } else if (current) {
                current.remove();
            }
        };

        // Light or dark sits on the root element, outside the swapped area
        // (src/Theme.php). Taken over from the fetched document so a page
        // that was switched in another tab does not stay in the old scheme.
        var adoptTheme = function (fresh) {
            var wanted = fresh.documentElement.getAttribute('data-theme');
            if (wanted && wanted !== document.documentElement.getAttribute('data-theme')) {
                document.documentElement.setAttribute('data-theme', wanted);
            }
        };

        // Renew the header alone from the fetched page: the room's notice
        // appears or goes, the menus are drawn afresh, the content -- a form
        // someone may be filling in -- stays as it is. The colours come along
        // (they sit in the head, not in the header) because an admin who
        // changes them changes them for every page, this one included.
        // False when the page is not one of ours.
        //
        // The page that came back may already show more than the one on
        // screen: something changed while it was on its way. Its content
        // token says so, and keeping only its header would take that token
        // over without the content it stands for -- the change would be
        // counted as seen and never appear. Then the whole content goes in
        // instead, which is what it was going to take anyway.
        var renderHeader = function (html, used) {
            var fresh = new DOMParser().parseFromString(html, 'text/html');
            var dome = fresh.querySelector('.dome');
            var current = document.querySelector('.dome');
            if (!dome || !current) {
                return false;
            }
            var here = document.body.getAttribute('data-live-rev');
            var there = fresh.body.getAttribute('data-live-rev');
            if (there && there !== here) {
                return render(html, used);
            }
            current.replaceWith(document.importNode(dome, true));
            adoptTheme(fresh);
            adoptColors(fresh);
            adoptBodyData(fresh);
            enhance(document.querySelector('.dome'));
            return true;
        };

        // Swap the fetched page's content in. False when it is not one of
        // ours (no .cabinet): the caller then loads it the normal way.
        var render = function (html, used) {
            var fresh = new DOMParser().parseFromString(html, 'text/html');
            var cabinet = fresh.querySelector('.cabinet');
            if (!cabinet || needsMore(fresh)) {
                return false;
            }
            var focus = remember(used);
            document.querySelector('.cabinet').innerHTML = cabinet.innerHTML;
            document.title = fresh.title;
            adoptTheme(fresh);
            adoptColors(fresh);
            adoptBodyData(fresh);
            // The name dialog's inline script (layout) does not run on a
            // swap; make it modal here.
            document.querySelectorAll('dialog[data-namebox][open]').forEach(function (dialog) {
                if (typeof dialog.showModal === 'function') {
                    dialog.removeAttribute('open');
                    dialog.showModal();
                }
            });
            enhance(document);
            // A form that came back (a validation error, a page to fill in)
            // asks for the focus itself; autofocus does not fire on a swap.
            var wanted = document.querySelector('[autofocus]');
            if (wanted) {
                wanted.focus({ preventScroll: true });
            } else {
                restore(focus);
            }
            // A notice that stays in the content is announced here; a pop-up
            // announces itself when it is lifted out (toast()).
            var flash = document.querySelector('.cabinet .flash');
            if (flash) {
                announce(flash.textContent.trim());
            }
            return true;
        };

        var busy = function (state) {
            loading = state;
            var cabinet = document.querySelector('.cabinet');
            if (cabinet) {
                if (state) {
                    cabinet.setAttribute('aria-busy', 'true');
                } else {
                    cabinet.removeAttribute('aria-busy');
                }
            }
        };

        // Fetch a page of ours as HTML. Redirects are followed; the final
        // address comes back with the text so the caller can tell whether
        // it still is this page.
        var request = function (url, options) {
            busy(true);
            options = options || {};
            options.credentials = 'same-origin';
            options.headers = { 'Accept': 'text/html' };
            return fetch(url, options).then(function (response) {
                return response.text().then(function (html) {
                    return { url: response.url, html: html };
                });
            }).finally(function () {
                busy(false);
            });
        };

        // A link or a GET form: fetch, swap, and put the address into the
        // history (push) -- or not, when back/forward brought us here. The
        // view keeps its scroll position -- except after paging: the pager
        // stands below the list, the next page is read from its top.
        var go = function (href, push, used) {
            request(href).then(function (result) {
                if (!samePage(result.url) || !render(result.html, used)) {
                    window.location.assign(result.url);
                    return;
                }
                if (push) {
                    history.pushState(null, '', result.url);
                }
                if (used && used.closest('.pager')) {
                    window.scrollTo(0, 0);
                }
                shown = result.url.split('#')[0];
            }).catch(function () {
                window.location.assign(href);
            });
        };

        // A form that posts: the server answers with a redirect
        // (post/redirect/get), which fetch follows; the page that comes back
        // carries the flash message. Mostly it is the page the form stood
        // on; after a save or a login it is the list the form belongs to,
        // which then takes the place of this page in the history.
        var submit = function (form, submitter) {
            var data = new FormData(form);
            if (submitter && submitter.name) {
                data.append(submitter.name, submitter.value);
            }
            var body = form.enctype === 'multipart/form-data' ? data : new URLSearchParams(data);
            request(form.action, { method: 'POST', body: body }).then(function (result) {
                var url = new URL(result.url, window.location.href);
                if (url.origin !== window.location.origin || !render(result.html, submitter)) {
                    window.location.assign(result.url);
                    return;
                }
                if (url.pathname === window.location.pathname) {
                    history.replaceState(null, '', result.url);
                } else {
                    // Another page: it starts at the top, as a loaded page would.
                    history.pushState(null, '', result.url);
                    window.scrollTo(0, 0);
                }
                shown = result.url.split('#')[0];
            }).catch(function () {
                // The post may or may not have gone through: say so instead
                // of sending it again.
                announce(document.body.getAttribute('data-msg-failed') || 'The page could not be updated – please reload it.');
            });
        };

        // Light, dark or the device's word. The switch stands in the header,
        // which lives inside the swapped area -- posting it the ordinary way
        // would exchange the whole content and lose a wish someone was half
        // way through typing. So the scheme is turned over here and now (one
        // attribute on the root element, and the stylesheet does the rest),
        // the menu is marked accordingly, and the post goes off in the
        // background for the cookie alone; its answer is not rendered. What
        // comes back is the same page in the same scheme, which nobody needs
        // to see twice.
        var schemes = { system: true, light: true, dark: true };
        var switchTheme = function (form) {
            var field = form.elements.theme;
            var wanted = field ? field.value : '';
            if (!schemes[wanted]) {
                return;
            }
            document.documentElement.setAttribute('data-theme', wanted);
            // Which entry the menu calls the current one. The glyph and the
            // toggle's accessible name follow the attribute by themselves
            // (CSS), so there is no text to translate here.
            document.querySelectorAll('.theme__item').forEach(function (item) {
                var active = item.getAttribute('data-theme-value') === wanted;
                item.classList.toggle('is-active', active);
                if (active) {
                    item.setAttribute('aria-current', 'true');
                } else {
                    item.removeAttribute('aria-current');
                }
            });
            // The menu has done its job; close it as a real navigation would.
            var menu = form.closest('details');
            if (menu) {
                menu.open = false;
            }
            fetch(form.action, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Accept': 'text/html' },
                body: new URLSearchParams(new FormData(form))
            }).catch(function () {
                // The scheme is already on screen; only remembering it
                // failed. Saying so would be noise -- the next click tries
                // again.
            });
        };

        // Which links and forms are ours: inside the page's content, not in
        // the header's menus (language, account, room switcher change more
        // than the content) and not in the name dialog.
        var handles = function (element) {
            return element.closest('.cabinet') !== null
                && element.closest('.nav, dialog') === null;
        };

        if (!window.fetch || !window.DOMParser || !window.URL) {
            return { announce: announce, refresh: function () { return Promise.resolve(false); } };
        }

        document.addEventListener('click', function (event) {
            if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                return;
            }
            var link = event.target.closest('a[href]');
            if (!link || !handles(link) || link.target || link.hasAttribute('download') || !samePage(link.href)) {
                return;
            }
            // A jump within the page (tabs) stays with the browser.
            var target = new URL(link.href, window.location.href);
            if (target.hash && target.href.split('#')[0] === shown) {
                return;
            }
            event.preventDefault();
            if (!loading) {
                go(link.href, true, link);
            }
        });

        document.addEventListener('submit', function (event) {
            var form = event.target;
            if (event.defaultPrevented || !handles(form) || form.target) {
                return;
            }
            var method = (form.method || 'get').toLowerCase();
            var action = new URL(form.action, window.location.href);
            if (action.origin !== window.location.origin) {
                return;
            }
            if (method === 'get') {
                if (action.pathname !== window.location.pathname) {
                    return;
                }
                event.preventDefault();
                if (!loading) {
                    action.search = new URLSearchParams(new FormData(form)).toString();
                    go(action.href, true, event.submitter);
                }
                return;
            }
            if (form.hasAttribute('data-theme-switch')) {
                event.preventDefault();
                switchTheme(form);
                return;
            }
            event.preventDefault();
            if (!loading) {
                submit(form, event.submitter);
            }
        });

        window.addEventListener('popstate', function () {
            var here = window.location.href.split('#')[0];
            if (here !== shown) {
                go(here, false);
            }
        });

        return {
            announce: announce,
            // Fetch this page again and swap its content (the live update).
            // Resolves to true when the content was exchanged.
            refresh: function () {
                if (loading) {
                    return Promise.resolve(false);
                }
                return request(window.location.href).then(function (result) {
                    return samePage(result.url) && render(result.html);
                });
            },
            // Fetch this page again and renew only its header (the live
            // update of the room's state on pages that are not lists).
            refreshHeader: function () {
                if (loading) {
                    return Promise.resolve(false);
                }
                return request(window.location.href).then(function (result) {
                    return samePage(result.url) && renderHeader(result.html);
                });
            }
        };
    }());

    // ---- Live update: poll the revisions, reload what moved ---------------
    // Every page carries data-live (the poll address), data-live-interval
    // (seconds between two polls, set under Interface per case; a case set to
    // 0 carries no data-live at all) and two tokens it was rendered with:
    // data-live-head for the header and data-live-rev for the content of a
    // list -- a page that is no list carries an empty one. Every interval
    // both are fetched -- a few bytes; only when one moved on is the page
    // fetched again.
    // The content token brings the whole page with it, so everyone on a list
    // sees a wish arrive, a song appear or the room close without touching
    // reload. When only the head token moved, the header alone is renewed:
    // the counters on the tabs and the closed-room notice follow everywhere,
    // on a list as well as on a form, and a form being filled in -- or a
    // search term just typed into a list -- keeps its input.
    // Hidden tabs do not poll; a drag in progress or an open header menu
    // postpones the swap.
    //
    // Asking costs almost nothing, because the question is not put to PHP.
    // data-live-gate names a static file that every change of the
    // application rewrites (LiveSignal) and that the web server hands out by
    // itself -- no PHP process, no database, and with an ETag, so a page that
    // asks again gets "304 Not Modified" and an empty body. Only when its
    // content differs from data-live-gate-rev, the value this page was given,
    // is data-live asked for the real tokens. In a quiet room that never
    // happens. Every minute the tokens are fetched anyway, so a page cannot
    // stay behind if the file stops being written or a signal is lost.
    // Without data-live-gate -- the file cannot be written -- every poll goes
    // to PHP, as it did before.
    var live = (function () {
        var failures = 0;
        var timer = null;
        var busy = false;
        var pending = false;
        var pendingOn = '';  // the address the postponed swap belongs to
        var asking = false;  // a round is in flight: do not start a second one
        // When the tokens were last fetched from PHP, and how long the
        // doorbell alone may answer for the page.
        var asked = 0;
        var FALLBACK = 60000;

        if (!window.fetch) {
            return { check: function () {} };
        }

        // A header menu that is open would snap shut with the swap: wait.
        // Only the popouts themselves count -- the Administration group
        // nested in the account menu stands open on the admin pages, and
        // counting it would postpone every swap there for good.
        var menuOpen = function () {
            return popouts().length > 0;
        };

        // Both tokens start with the room's state (closed or open): when that
        // part moved, the fresh page's data-msg-state says which; any other
        // change -- a wish, a song, a room -- is announced on a list only.
        var statePart = function (rev) {
            return (rev || '').split('.')[0];
        };

        // content: the list's own token moved, so the whole page is drawn
        // anew (the header comes with it); otherwise the header alone. The
        // doorbell value is not written here: a swap that goes through
        // brings the fetched page's own value along (adoptBodyData), and one
        // that does not must be tried again on the next round.
        var swap = function (content) {
            busy = true;
            var before = document.body.getAttribute('data-live-head');
            (content ? page.refresh() : page.refreshHeader()).then(function (swapped) {
                if (swapped) {
                    var status = document.getElementById('live-status');
                    var message = statePart(before) !== statePart(document.body.getAttribute('data-live-head'))
                        ? document.body.getAttribute('data-msg-state')
                        : (content ? document.body.getAttribute('data-msg-updated') || 'The list has been updated.' : '');
                    if (status && message) {
                        status.textContent = message;
                    }
                }
            }).catch(function () {
                // Nothing: the next poll tries again.
            }).finally(function () {
                busy = false;
            });
        };

        // Ask PHP for the two tokens and act on them. `signal` is the
        // doorbell value this answer belongs to, remembered on the body so
        // the next round knows what it has already asked about.
        var tokens = function (url, signal) {
            asked = Date.now();
            return fetch(url, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'fetch' },
                credentials: 'same-origin',
                cache: 'no-store'
            }).then(function (response) {
                return response.json();
            }).then(function (data) {
                failures = 0;
                if (!data || typeof data.head !== 'string') {
                    return;
                }
                // The tokens shown are read afresh: a soft navigation may
                // have exchanged the content meanwhile.
                var content = typeof data.rev === 'string' && data.rev !== ''
                    && data.rev !== document.body.getAttribute('data-live-rev');
                if (!content && data.head === document.body.getAttribute('data-live-head')) {
                    // Nothing to draw: this ring of the doorbell is answered,
                    // and the next round may go back to the static file.
                    if (signal) {
                        document.body.setAttribute('data-live-gate-rev', signal);
                    }
                    return;
                }
                if (dragging || menuOpen()) {
                    // A content swap that waits stays a content swap: its
                    // token keeps differing until it has happened. It belongs
                    // to this address; a soft navigation drops it.
                    pending = content ? 'content' : 'header';
                    pendingOn = window.location.href;
                    return;
                }
                return swap(content);
            }).catch(function () {
                // Back off a little on errors: the wait doubles per failed
                // poll, up to sixteen times the interval.
                failures = Math.min(failures + 1, 4);
            });
        };

        var check = function () {
            if (busy || asking || document.hidden) {
                return;
            }
            // A postponed swap belongs to the page it was postponed on: after
            // a soft navigation it would exchange a content nobody asked
            // about -- and take a form being filled in with it.
            if (pending && pendingOn !== window.location.href) {
                pending = false;
            }
            if (pending && !dragging && !menuOpen()) {
                var postponed = pending;
                pending = false;
                swap(postponed === 'content');
                return;
            }
            // Read afresh each time: the soft navigation may have brought a
            // page with (or without) a live address.
            var url = document.body.getAttribute('data-live');
            if (!url) {
                return;
            }
            var done = function () { asking = false; };
            asking = true;
            var gate = document.body.getAttribute('data-live-gate');
            if (!gate || Date.now() - asked > FALLBACK) {
                tokens(url, null).then(done, done);
                return;
            }
            // 'no-cache', not 'no-store': the browser is meant to ask with
            // the ETag it has, so an unchanged file comes back as a 304 with
            // no body at all. A doorbell that answers says nothing about PHP,
            // so it does not clear the back-off -- only an answered ?poll=1
            // does.
            fetch(gate, { credentials: 'same-origin', cache: 'no-cache' }).then(function (response) {
                if (!response.ok) {
                    throw new Error('gate');
                }
                return response.text();
            }).then(function (text) {
                var signal = text.replace(/\s+/g, '');
                if (signal !== '' && signal === document.body.getAttribute('data-live-gate-rev')) {
                    return; // Nothing has happened anywhere: no PHP, no database.
                }
                return tokens(url, signal);
            }).catch(function () {
                // No doorbell (missing after a deployment, or unreachable):
                // ask PHP itself, which writes the file again as it renders.
                return tokens(url, null);
            }).then(done, done);
        };

        // Read afresh for every poll, like the address: a soft navigation
        // may have brought a page with another interval. 4 s when a live
        // page does not say; a page without a live address ticks idly at
        // that pace, check() finds nothing to do.
        var interval = function () {
            var seconds = parseInt(document.body.getAttribute('data-live-interval') || '', 10);
            return (seconds > 0 ? seconds : 4) * 1000;
        };

        var schedule = function () {
            clearTimeout(timer);
            timer = setTimeout(function () {
                check();
                schedule();
            }, interval() * Math.pow(2, failures));
        };

        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) {
                check();
                schedule();
            }
        });

        schedule();

        return { check: check };
    }());

    enhance(document);
}());
