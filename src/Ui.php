<?php

declare(strict_types=1);

namespace Songwunsch;

/**
 * How the interface behaves, set by the admins under Administration -> User
 * interface and kept in the `settings` table as `ui.<name>`: how long a
 * pop-up message stays, and how often a page asks the server whether what it
 * shows has changed -- one interval per case (the wish list, the
 * suggestions, the room's open/closed state). The page's colours share the
 * form but are their own class (Colors, `colors.<area>`).
 *
 * The layout hands the values to app.js as data attributes on <body>; a
 * polling interval of 0 switches that case's live update off.
 */
final class Ui extends NumberSettings
{
    public const PREFIX = 'ui.';

    /**
     * Every setting: default, smallest and largest allowed value.
     *
     * @var array<string,array{0:int,1:int,2:int}>
     */
    public const FIELDS = [
        'toast_sec'            => [5, 0, 60],   // seconds a pop-up message (the result of an action) stays; 0 = until dismissed
        'poll_wishes_sec'      => [4, 0, 300],  // seconds between two polls of the wish list; 0 = no live update
        'poll_suggestions_sec' => [4, 0, 300],  // ... of the suggestions
        'poll_room_sec'        => [10, 0, 300], // ... of the room's state (closed or open) on the song list
    ];

    /** The message duration lived under the limits until it moved here. */
    protected function legacy(): array
    {
        $old = $this->settings->get(Limits::PREFIX . 'toast_sec');

        return $old === null ? [] : ['toast_sec' => $old];
    }

    /**
     * @param array<string,int> $values  from validate()
     */
    public function save(array $values): void
    {
        parent::save($values);
        $this->settings->delete(Limits::PREFIX . 'toast_sec');
    }
}
