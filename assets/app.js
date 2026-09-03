/*
 * Small enhancements -- the application works without JavaScript as well:
 * sorting, searching, wishing and deleting run through plain links/forms.
 */
(function () {
    'use strict';

    // Address of the front controller -- comes from the layout so a base
    // path like /songliste is taken into account.
    var endpoint = document.body.getAttribute('data-endpoint') || '/';

    // Confirmation before deleting.
    // Room switcher: the filter field hides entries that do not contain the
    // typed text. Purely progressive -- without JavaScript the full list shows.
    document.querySelectorAll('input[data-roomfilter]').forEach(function (field) {
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

    // Header popouts (language, account, room switcher) are <details> and
    // work without this; with it they also close on a click elsewhere or on
    // Escape, so a menu does not stay open while one uses the page.
    var popouts = document.querySelectorAll('.dome details');
    if (popouts.length) {
        document.addEventListener('click', function (event) {
            popouts.forEach(function (details) {
                if (details.open && !details.contains(event.target)) {
                    details.open = false;
                }
            });
        });
        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') {
                return;
            }
            popouts.forEach(function (details) {
                if (details.open) {
                    details.open = false;
                    var toggle = details.querySelector('summary');
                    if (toggle && details.contains(document.activeElement)) {
                        toggle.focus();
                    }
                }
            });
        });
    }

    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!window.confirm(form.getAttribute('data-confirm'))) {
                event.preventDefault();
            }
        });
    });

    // A click on a song row triggers the wish (the button remains the real,
    // keyboard- and screen-reader-accessible way).
    document.querySelectorAll('table[data-rowclick] tbody tr').forEach(function (row) {
        var button = row.querySelector('.wish-button');
        if (!button) {
            return;
        }

        row.style.cursor = 'pointer';
        row.addEventListener('click', function (event) {
            if (event.target.closest('a, button, input, label')) {
                return;
            }
            button.click();
        });
    });

    // "/" jumps into the search field.
    var search = document.getElementById('q');
    if (search) {
        document.addEventListener('keydown', function (event) {
            var tag = (event.target.tagName || '').toLowerCase();
            if (event.key === '/' && tag !== 'input' && tag !== 'textarea' && !event.metaKey && !event.ctrlKey) {
                event.preventDefault();
                search.focus();
                search.select();
            }
        });
    }

    // ---- Wish list: order by drag & drop ---------------------------------
    // Without JavaScript the arrow buttons in the first column remain the
    // way to the same result -- they are also the keyboard access.
    var board = document.querySelector('table[data-reorder]');
    if (board) {
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
                var up = row.querySelector('.move form:first-child button');
                var down = row.querySelector('.move form:last-child button');
                if (up) {
                    up.disabled = index === 0;
                }
                if (down) {
                    down.disabled = index === rows.length - 1;
                }
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
            renumber();
            persist();
        });
    }
}());
