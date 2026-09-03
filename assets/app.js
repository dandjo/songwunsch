/*
 * Small enhancements -- the application works without JavaScript as well:
 * sorting, searching, wishing and deleting run through plain links/forms.
 *
 * Two kinds of bindings: those that live on the document and look the page
 * up when they fire (popouts, the "/" key, the name dialog), registered once;
 * and those bound to elements of the page (confirmations, row click, drag &
 * drop, room filter), gathered in enhance() so they can be set again after
 * the live update below has swapped the page's content.
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

        // Confirmation before deleting.
        root.querySelectorAll('form[data-confirm]').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (!window.confirm(form.getAttribute('data-confirm'))) {
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
            // and keep it lit after the tap until the page reloads with the
            // result.
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

        // Wish list: order by drag & drop. Without JavaScript the arrow
        // buttons remain the way to the same result -- they are also the
        // keyboard access.
        var board = root.querySelector('table[data-reorder]');
        if (board) {
            reorderable(board);
        }
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

            var payload = new URLSearchParams();
            payload.set('a', 'reorder');
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

    // ---- Live update: poll the revision, reload the page's content --------
    // The wish list and the suggestions carry data-live (the poll address)
    // and data-live-rev (the revision they were rendered with). Every few
    // seconds the revision is fetched -- a few bytes; only when it moved on
    // is the page fetched again and its content swapped in, so everyone
    // sees a wish arrive or a row move without touching reload. Hidden tabs
    // do not poll; a drag in progress postpones the swap.
    var live = (function () {
        var url = document.body.getAttribute('data-live');
        var rev = document.body.getAttribute('data-live-rev');
        var interval = 4000;
        var failures = 0;
        var timer = null;
        var busy = false;
        var pending = false;

        if (!url || !window.fetch) {
            return { check: function () {} };
        }

        var announce = function (text) {
            var status = document.getElementById('live-status');
            if (status) {
                status.textContent = text;
            }
        };

        var swap = function () {
            busy = true;
            fetch(window.location.href, {
                headers: { 'X-Requested-With': 'fetch' },
                credentials: 'same-origin'
            }).then(function (response) {
                return response.text();
            }).then(function (html) {
                var fresh = new DOMParser().parseFromString(html, 'text/html');
                var cabinet = fresh.querySelector('.cabinet');
                var newRev = fresh.body.getAttribute('data-live-rev');
                if (!cabinet || !newRev) {
                    return;
                }
                // A drag may have started meanwhile: try again next tick.
                if (dragging) {
                    pending = true;
                    return;
                }
                var focusId = document.activeElement && document.activeElement.id;
                document.querySelector('.cabinet').innerHTML = cabinet.innerHTML;
                document.body.setAttribute('data-live-rev', newRev);
                rev = newRev;
                enhance(document);
                if (focusId) {
                    var again = document.getElementById(focusId);
                    if (again) { again.focus(); }
                }
                announce(document.body.getAttribute('data-msg-updated') || 'The list has been updated.');
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
            fetch(url, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'fetch' },
                credentials: 'same-origin',
                cache: 'no-store'
            }).then(function (response) {
                return response.json();
            }).then(function (data) {
                failures = 0;
                if (data && typeof data.rev === 'string' && data.rev !== rev) {
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

        var schedule = function () {
            clearTimeout(timer);
            timer = setTimeout(function () {
                check();
                schedule();
            }, interval * Math.pow(2, failures));
        };

        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) {
                check();
                schedule();
            }
        });

        // A live region for the announcement, once, outside the swapped area.
        var status = document.createElement('p');
        status.id = 'live-status';
        status.className = 'sr-only';
        status.setAttribute('role', 'status');
        document.body.appendChild(status);

        schedule();

        return { check: check };
    }());

    enhance(document);
}());
