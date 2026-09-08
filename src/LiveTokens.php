<?php

declare(strict_types=1);

namespace Songwunsch;

/**
 * The two tokens every open page polls with, and how often it asks.
 *
 * There is no push: a page carries the values it was rendered with and asks
 * whether they moved on. The tokens are built here, in one place, because
 * which value belongs in which token is the one thing about live updates
 * that is easy to get wrong:
 *
 *  - the head token stands for the header, which every page carries: the
 *    room's open/closed state, the catalogue revision (rooms and songs), the
 *    room's wish revision, the suggestions' revision and the interface
 *    revision. When it moves, header.dome alone is drawn anew -- so the tab
 *    counters, the closed-room notice, the logo, the colours and the polling
 *    pace itself follow on every page while a form being filled in keeps
 *    what was typed into it.
 *  - the content token stands for a list, and only a list has one: a form
 *    must not be exchanged under the hands filling it in. When it moves, all
 *    of .cabinet is drawn anew.
 *
 * Putting a shell value into a content token would throw away half-typed
 * input on every save somewhere else; putting a list's value into the head
 * token would renew every header for a change nobody on those pages can see.
 *
 * Every token builds on the one above it, so a page names the shortest one
 * that covers what it shows and the head token is the longest.
 */
final class LiveTokens
{
    private bool $ready = false;
    private bool $paused = false;
    private string $catalog = '';
    private string $wishes = '';
    private string $suggestions = '';
    private string $head = '';

    public function __construct(
        private readonly Settings $settings,
        private readonly WishGuard $guard,
        private readonly Ui $ui,
    ) {
    }

    /**
     * Every value the tokens are made of, read once. The rows themselves
     * cost one query between them: Settings holds the table for the request
     * (see its $cache). Called on the poll path as well, which is why
     * nothing else happens here -- a poll is not a page.
     */
    private function build(): void
    {
        if ($this->ready) {
            return;
        }
        $this->ready = true;

        // Both tokens start with the room's state, so that app.js can tell a
        // closing from any other change and announce it.
        $this->paused      = $this->guard->isPaused();
        $state             = $this->paused ? '1' : '0';
        $this->catalog     = $state . '.' . $this->settings->get(RoomRepository::REVISION_KEY, '0');
        $this->wishes      = $this->catalog . '.' . $this->guard->revision();
        // The suggestions count per room but their revision is one for all
        // rooms: a suggestion elsewhere renews a header for nothing, and
        // never misses one.
        $this->suggestions = $this->wishes . '.' . $this->settings->get(SuggestionRepository::REVISION_KEY, '0');
        // The head token alone carries the interface revision: the look, the
        // message duration and the polling pace belong to the shell, not to
        // any list, so a save there renews headers and leaves contents alone.
        $this->head        = $this->suggestions . '.' . $this->settings->get(Ui::REVISION_KEY, '0');
    }

    /** Wishing closed in this room -- the notice in the header. */
    public function paused(): bool
    {
        $this->build();

        return $this->paused;
    }

    public function head(): string
    {
        $this->build();

        return $this->head;
    }

    /**
     * The content token of a page, '' for a page that is not a list and
     * therefore never gets a content swap.
     *
     * The repertoire and a room's song picker follow the catalogue; the list
     * of rooms follows every room's wish list too, because it counts the
     * wishes of each room and marks the closed ones -- a room the visitor is
     * not in must reach it as well; the wish list follows its own revision,
     * the suggestions theirs plus the room's wish revision, since closing
     * the room hides their form as well.
     */
    public function content(string $page): string
    {
        $this->build();

        return match ($page) {
            'songs', 'room_songs' => $this->catalog,
            'rooms'               => $this->catalog . '.' . $this->guard->allRevision(),
            'wishes'              => $this->wishes,
            'suggestions'         => $this->suggestions,
            default               => '',
        };
    }

    /**
     * How often a page asks, in seconds, from Administration -> Interface;
     * 0 switches the case off, and its pages then carry no live address and
     * do not poll. The poll itself still answers, so a page opened before
     * the switch keeps working until it loads again.
     */
    public function interval(string $page): int
    {
        return $this->ui->get(match ($page) {
            'wishes'      => 'poll_wishes_sec',
            'suggestions' => 'poll_suggestions_sec',
            default       => 'poll_room_sec',
        });
    }
}
