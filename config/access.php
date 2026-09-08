<?php

declare(strict_types=1);

/**
 * Who may open which address.
 *
 * A route that is not listed here is public: the repertoire, the wish list,
 * the suggestions, the list of rooms, a page, the login form, wishing,
 * suggesting, the visitor's name, the room switch. Everything listed names
 * the role area it needs -- 'wishes' for moderators, 'songs',
 * 'suggestions', 'rooms' for editors, 'users' for admins -- or ANY, meaning
 * any signed-in user whatever their roles. Admins hold every area
 * (Security::can()).
 *
 * Enforced by AccessListener, before the controller runs. The controllers
 * hold no permission checks of their own; the two places that look at a
 * role at all do so to decide what to *show* -- whether a list carries its
 * editing controls -- not whether it may be reached.
 */

use Songwunsch\EventListener\AccessListener;

return [
    // Own account: any login, no role needed.
    'settings'          => AccessListener::ANY,
    'settings_redirect' => AccessListener::ANY,
    'settings_save'     => AccessListener::ANY,
    'password_save'     => AccessListener::ANY,

    // The wish list's controls belong to the moderators. Looking at the
    // list does not, which is why 'wishes' itself is not here.
    'wish_delete'    => 'wishes',
    'wish_move'      => 'wishes',
    'wishes_reorder' => 'wishes',
    'wishes_clear'   => 'wishes',
    'room_pause'     => 'wishes',

    // The repertoire is edited by the editors.
    'song'        => 'songs',
    'song_adopt'  => 'songs',
    'song_save'   => 'songs',
    'song_delete' => 'songs',

    'suggestion_delete' => 'suggestions',
    'suggestions_clear' => 'suggestions',

    'room_new'          => 'rooms',
    'room_edit'         => 'rooms',
    'room_main_edit'    => 'rooms',
    'room_save'         => 'rooms',
    'room_main_save'    => 'rooms',
    'room_delete'       => 'rooms',
    'room_start'        => 'rooms',
    'room_songs'        => 'rooms',
    'room_songs_add'    => 'rooms',
    'room_songs_remove' => 'rooms',
    'room_qr'           => 'rooms',
    'room_qr_image'     => 'rooms',

    // The Administration menu. 'users' is the area nobody but an admin
    // holds, so it is what closing an admin page means -- including the
    // switch that closes every room at once.
    'admin_home'       => 'users',
    'users'            => 'users',
    'user_new'         => 'users',
    'user_edit'        => 'users',
    'user_save'        => 'users',
    'user_delete'      => 'users',
    'logos'            => 'users',
    'logo_upload'      => 'users',
    'logo_activate'    => 'users',
    'logo_light_inherit' => 'users',
    'logo_delete'      => 'users',
    'ui'               => 'users',
    'ui_save'          => 'users',
    'ui_palette_save'  => 'users',
    'ui_palette_delete' => 'users',
    'ui_preview'       => 'users',
    'limits'           => 'users',
    'limits_save'      => 'users',
    'pages'            => 'users',
    'page_new'         => 'users',
    'page_edit'        => 'users',
    'page_save'        => 'users',
    'page_delete'      => 'users',
    'footer'           => 'users',
    'footer_text_save' => 'users',
    'footer_add'       => 'users',
    'footer_remove'    => 'users',
    'footer_move'      => 'users',
    'footer_reorder'   => 'users',
    'languages'        => 'users',
    'languages_move'   => 'users',
    'languages_reorder' => 'users',
    'rooms_pause_all'  => 'users',

    // Open to everyone on purpose. Written down because a POST route that
    // nobody classified is refused (AccessListener::OPEN), so this list is
    // the difference between "public" and "forgotten".
    'wish'             => AccessListener::OPEN,   // a guest wishes a song
    'suggest'          => AccessListener::OPEN,   // a guest suggests one
    'room_switch'      => AccessListener::OPEN,   // which room this visitor is in
    'name_save'        => AccessListener::OPEN,   // the name for the wish list
    'name_skip'        => AccessListener::OPEN,   // ... or no name
    'theme'            => AccessListener::OPEN,   // light or dark, per visitor
    'login_submit'     => AccessListener::OPEN,   // the login itself
    'logout'           => AccessListener::OPEN,
    // Turning the guest view off has to work while it is on, and a user in
    // guest view counts as no user (Security::user()); the controller checks
    // the account itself.
    'guest_view'       => AccessListener::OPEN,
];
