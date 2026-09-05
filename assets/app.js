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

    // Address of the front controller -- comes from the layout so a base
    // path like /songliste is taken into account.
    var endpoint = document.body.getAttribute('data-endpoint') || '/';

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

    // Header popouts (language, account, room switcher) are <details> and
    // work without this; with it they also close on a click elsewhere or on
    // Escape, so a menu does not stay open while one uses the page.
    document.addEventListener('click', function (event) {
        document.querySelectorAll('.dome details[open]').forEach(function (details) {
            if (!details.contains(event.target)) {
                details.open = false;
            }
        });
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            document.querySelectorAll('.dome details[open]').forEach(function (details) {
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

    // ---- Bindings on the page's elements ----------------------------------

    function enhance(root) {
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

        // Help: the "?" beside a title folds the explanation below it away
        // and opens it again. The button is rendered hidden and appears only
        // here, so without JavaScript the text simply stands open. Whether
        // it is open is kept per text for the tab (sessionStorage), so the
        // live update and the soft navigation, which draw the page anew,
        // leave an opened help open.
        root.querySelectorAll('.help-toggle[aria-controls]').forEach(function (button) {
            var text = document.getElementById(button.getAttribute('aria-controls'));
            if (!text || button.hasAttribute('data-bound')) { return; }
            button.setAttribute('data-bound', '1');
            var key = 'help:' + text.id;
            var remember = function (on) {
                try {
                    if (on) { sessionStorage.setItem(key, '1'); } else { sessionStorage.removeItem(key); }
                } catch (e) {
                    // Storage refused (private mode): the help simply starts closed next time.
                }
            };
            var set = function (on) {
                text.hidden = !on;
                button.setAttribute('aria-expanded', on ? 'true' : 'false');
            };
            var open = false;
            try { open = sessionStorage.getItem(key) === '1'; } catch (e) { /* as above */ }
            button.hidden = false;
            set(open);
            button.addEventListener('click', function () {
                set(text.hidden);
                remember(text.hidden === false);
            });
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
        // seconds set under Limits (0: until dismissed). An error stays until
        // dismissed. Pointer or focus on it holds it. Without JavaScript the
        // message stands at the top of the content.
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
        var isError = message.classList.contains('flash--error');
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
            if (!isError && seconds > 0 && timer === null) {
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

    // ---- Tabs over the languages of a page ---------------------------------
    // The anchors become tabs (role=tab, arrow keys move between them), the
    // fieldsets tabpanels; only the selected panel is shown. The tab to start
    // on comes from the address (#lang-<code>), otherwise from the server
    // (data-tabs-active: the language with an error, or the one the page has).
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

        var fromHash = location.hash.indexOf('#lang-') === 0 ? location.hash.slice(6) : '';
        var start = panelOf(fromHash) ? fromHash : box.getAttribute('data-tabs-active');
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

        var renumber = function () {
            var rows = body.querySelectorAll('tr');
            rows.forEach(function (row, index) {
                var rank = row.querySelector('.rank');
                if (rank) {
                    rank.lastChild.textContent = String(index + 1);
                }
                // Up and to the top are pointless on the first row, down and
                // to the bottom on the last.
                row.querySelectorAll('.move button[data-move]').forEach(function (button) {
                    var dir = button.getAttribute('data-move');
                    button.disabled = (dir === 'up' || dir === 'top')
                        ? index === 0
                        : index === rows.length - 1;
                });
            });
        };

        var persist = function () {
            var ids = Array.prototype.map.call(body.querySelectorAll('tr'), function (row) {
                return row.getAttribute('data-id');
            });

            // The wish list posts 'reorder', the footer 'footer_reorder'.
            var payload = new URLSearchParams();
            payload.set('a', board.getAttribute('data-reorder-action') || 'reorder');
            payload.set('csrf', board.getAttribute('data-csrf'));
            payload.set('order', ids.join(','));

            fetch(endpoint, {
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
            // The body's data attributes belong to the page: the endpoint of
            // its room, the live address, token and interval, the messages.
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
            endpoint = document.body.getAttribute('data-endpoint') || '/';
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
            }
        };
    }());

    // ---- Live update: poll the revision, reload the page's content --------
    // The song list, the wish list and the suggestions carry data-live (the
    // poll address), data-live-rev (the token they were rendered with) and
    // data-live-interval (seconds between two polls, set under Interface per
    // case; a case set to 0 carries no data-live at all). Every interval the
    // token is fetched -- a few bytes; only when it moved on is the page
    // fetched again and its content swapped in, so everyone sees a wish
    // arrive, a row move or the room close without touching reload. The
    // song list's token is the room's state alone, so a wish does not
    // reload it. Hidden tabs do not poll; a drag in progress postpones the
    // swap.
    var live = (function () {
        var failures = 0;
        var timer = null;
        var busy = false;
        var pending = false;

        if (!window.fetch) {
            return { check: function () {} };
        }

        var swap = function () {
            busy = true;
            page.refresh().then(function (swapped) {
                if (swapped) {
                    var status = document.getElementById('live-status');
                    if (status) {
                        status.textContent = document.body.getAttribute('data-msg-updated') || 'The list has been updated.';
                    }
                }
            }).catch(function () {
                // Nothing: the next poll tries again.
            }).finally(function () {
                busy = false;
            });
        };

        var check = function () {
            if (busy || document.hidden) {
                return;
            }
            if (pending && !dragging) {
                pending = false;
                swap();
                return;
            }
            // Read afresh each time: the soft navigation may have brought a
            // page with (or without) a live address.
            var url = document.body.getAttribute('data-live');
            if (!url) {
                return;
            }
            fetch(url, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'fetch' },
                credentials: 'same-origin',
                cache: 'no-store'
            }).then(function (response) {
                return response.json();
            }).then(function (data) {
                failures = 0;
                // The revision shown is read afresh: a soft navigation may
                // have exchanged the content meanwhile.
                if (data && typeof data.rev === 'string' && data.rev !== document.body.getAttribute('data-live-rev')) {
                    if (dragging) {
                        pending = true;
                    } else {
                        swap();
                    }
                }
            }).catch(function () {
                // Back off a little on errors, up to a minute.
                failures = Math.min(failures + 1, 4);
            });
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
