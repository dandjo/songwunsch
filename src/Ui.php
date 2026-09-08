<?php

declare(strict_types=1);

namespace Songwunsch;

/**
 * How the interface behaves, set by the admins under Administration ->
 * Interface and kept in the `settings` table as `ui.<name>`: how long a
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
     * Settings key of the interface revision: a counter raised whenever
     * something the page shell shows changes -- the colours, the message
     * duration, the polling intervals (this form) and the logo in the header
     * (Administration -> Logos). Every page carries it in its head token, so
     * an open page renews its header and takes on the new look, the new
     * message duration and the new pace without being reloaded (index.php).
     */
    public const REVISION_KEY = 'ui_rev';

    /**
     * Every setting: default, smallest and largest allowed value.
     *
     * @var array<string,array{0:int,1:int,2:int}>
     */
    public const FIELDS = [
        'toast_sec'            => [5, 0, 60],   // seconds a pop-up message (the result of an action) stays; 0 = until dismissed
        'poll_wishes_sec'      => [4, 0, 300],  // seconds between two polls of the wish list; 0 = no live update
        'poll_suggestions_sec' => [4, 0, 300],  // ... of the suggestions
        'poll_room_sec'        => [10, 0, 300], // ... of the room's state (closed or open) and the catalogue (rooms and songs): the song list and the list of rooms, the header everywhere else
    ];

}
