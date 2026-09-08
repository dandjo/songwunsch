<?php

declare(strict_types=1);

/**
 * The route table -- every address of the application, in one place.
 *
 * This file is the single source of truth for both directions: the matcher
 * reads it to find the controller behind a path, the generator reads it to
 * build the address of a route by name. Nothing else in the application
 * writes a path.
 *
 * The order matters for matching: a literal path is declared before a
 * pattern that could swallow it. Room-scoped routes are always tried after
 * everything else, whatever their place here (see RouteMatcher).
 *
 * Conventions:
 *
 *  - GET addresses are exactly the ones the application has always had;
 *    bookmarks, printed QR codes and search engines keep working.
 *  - Every mutation is a POST route with an address of its own. Where the
 *    action concerns one record, its id is part of the path
 *    (/wishes/12/delete); a form that serves both "new" and "edit" posts to
 *    a /save address and names the record in its body.
 *  - roomScoped(): the declared path is the main room's -- which lives at
 *    the base path and has no machine name -- and /rooms/<slug> in front of
 *    it is every other room's. roomOnly(): only inside a real room, unless
 *    a path for the main room is given.
 *  - page(): the screen this route belongs to, for the navigation, the
 *    templates and the live-update tokens. Several routes share one.
 */

use Songwunsch\Controller\AdminController;
use Songwunsch\Controller\AuthController;
use Songwunsch\Controller\FooterController;
use Songwunsch\Controller\GuestController;
use Songwunsch\Controller\LanguageController;
use Songwunsch\Controller\PageController;
use Songwunsch\Controller\RoomController;
use Songwunsch\Controller\SongController;
use Songwunsch\Controller\SuggestionController;
use Songwunsch\Controller\UserController;
use Songwunsch\Controller\WishController;
use Songwunsch\PageRepository;
use Songwunsch\Routing\Route;

// An id in a path: a real record, never 0 -- and the form that allows 0,
// where 0 means "the main room" or "no logo at all".
$id       = '[1-9][0-9]*';
$idOrNone = '[0-9]+';

// {room} is deliberately left unconstrained. A malformed machine name is no
// dead end: RoomListener answers "there is no room at this address -- here
// is the start page", the same as for a room that has been deleted or
// renamed, instead of a 404.

return [
    // ---- The repertoire ---------------------------------------------------
    // The start page, and a room's song list: the room itself is its songs.
    Route::get('songs', '/', [SongController::class, 'index'])->roomScoped(),

    // The song form serves "new" and "edit"; adopting a suggestion is the
    // same form, filled from the suggestion, under an address of its own.
    Route::get('song', '/song/{id}', [SongController::class, 'form'])
        ->requirements(['id' => 'new|' . $id])->defaults(['id' => 'new']),
    Route::get('song_adopt', '/suggestions/{id}/adopt', [SongController::class, 'form'])
        ->requirements(['id' => $id])->page('song'),
    Route::post('song_save', '/song/save', [SongController::class, 'save'])->roomScoped(),
    Route::post('song_delete', '/song/{id}/delete', [SongController::class, 'delete'])
        ->requirements(['id' => $id])->roomScoped(),

    // ---- The wish list ----------------------------------------------------
    Route::get('wishes', '/wishes', [WishController::class, 'index'])->roomScoped(),
    Route::post('wish', '/wishes/add', [WishController::class, 'add'])->roomScoped(),
    Route::post('wishes_clear', '/wishes/clear', [WishController::class, 'clear'])->roomScoped(),
    Route::post('wishes_reorder', '/wishes/reorder', [WishController::class, 'reorder'])->roomScoped(),
    Route::post('wish_delete', '/wishes/{id}/delete', [WishController::class, 'delete'])
        ->requirements(['id' => $id])->roomScoped(),
    Route::post('wish_move', '/wishes/{id}/move', [WishController::class, 'move'])
        ->requirements(['id' => $id])->roomScoped(),

    // ---- Song suggestions -------------------------------------------------
    Route::get('suggestions', '/suggestions', [SuggestionController::class, 'index'])->roomScoped(),
    Route::post('suggest', '/suggestions/add', [SuggestionController::class, 'add'])->roomScoped(),
    Route::post('suggestions_clear', '/suggestions/clear', [SuggestionController::class, 'clear'])->roomScoped(),
    Route::post('suggestion_delete', '/suggestions/{id}/delete', [SuggestionController::class, 'delete'])
        ->requirements(['id' => $id])->roomScoped(),

    // ---- Rooms ------------------------------------------------------------
    // 'new' and 'main' are reserved room names (RoomRepository::RESERVED_SLUGS),
    // which is what lets these literal paths stand in front of /rooms/<slug>.
    Route::get('rooms', '/rooms', [RoomController::class, 'index']),
    Route::get('room_new', '/rooms/new', [RoomController::class, 'form'])->page('room'),
    Route::get('room_main_edit', '/rooms/main/edit', [RoomController::class, 'form'])->page('room'),
    Route::get('room_edit', '/rooms/{id}/edit', [RoomController::class, 'form'])
        ->requirements(['id' => $id])->page('room'),

    Route::post('room_save', '/rooms/save', [RoomController::class, 'save']),
    Route::post('room_main_save', '/rooms/main/save', [RoomController::class, 'saveMain']),
    Route::post('room_switch', '/rooms/switch', [RoomController::class, 'switchTo']),
    Route::post('rooms_pause_all', '/rooms/pause-all', [RoomController::class, 'pauseAll']),
    Route::post('room_delete', '/rooms/{id}/delete', [RoomController::class, 'delete'])
        ->requirements(['id' => $id]),
    // Id 0 is the main room here: it can be the start room and it can be closed.
    Route::post('room_start', '/rooms/{id}/start', [RoomController::class, 'start'])
        ->requirements(['id' => $idOrNone]),
    Route::post('room_pause', '/rooms/{id}/pause', [RoomController::class, 'pause'])
        ->requirements(['id' => $idOrNone]),

    // A room's song selection from the main list. The main room always
    // offers the whole repertoire, so this exists inside a real room only.
    Route::get('room_songs', '/manage', [RoomController::class, 'songs'])->roomOnly(),
    Route::post('room_songs_add', '/manage/add', [RoomController::class, 'songsAdd'])->roomOnly(),
    Route::post('room_songs_remove', '/manage/remove', [RoomController::class, 'songsRemove'])->roomOnly(),

    // The room's address as a QR code. The main room's pages are the base
    // path itself, so its code needs an address of its own; and this page
    // names its room outright -- looking at a code is not entering the room.
    Route::get('room_qr', '/qr', [RoomController::class, 'qr'])
        ->roomOnly('/rooms/main/qr')->namesRoom(),
    Route::get('room_qr_image', '/qr.{format}', [RoomController::class, 'qrImage'])
        ->roomOnly('/rooms/main/qr.{format}')->namesRoom()
        ->requirements(['format' => 'svg|png'])->page('room_qr')->raw(),

    // ---- Logging in, the account ------------------------------------------
    Route::get('login', '/login', [AuthController::class, 'form']),
    Route::post('login_submit', '/login', [AuthController::class, 'login'])->page('login'),
    // Logging out must work when the database does not.
    Route::post('logout', '/logout', [AuthController::class, 'logout'])->withoutSchema(),
    Route::post('guest_view', '/guest-view', [AuthController::class, 'guestView']),

    // Own settings: every signed-in user, so not below /admin. The bare
    // address leads to one's own page.
    Route::get('settings_redirect', '/settings', [UserController::class, 'settingsRedirect'])->page('settings'),
    Route::get('settings', '/users/{id}/settings', [UserController::class, 'settings'])
        ->requirements(['id' => $id]),
    Route::post('settings_save', '/settings/save', [UserController::class, 'saveSettings']),
    Route::post('password_save', '/settings/password', [UserController::class, 'savePassword']),

    // ---- What a visitor sets for themselves -------------------------------
    // The name that goes with their wishes, and the colour scheme. Both live
    // in a cookie, so both are public addresses -- a guest has no account.
    Route::get('name', '/name', [GuestController::class, 'form']),
    Route::post('name_save', '/name/save', [GuestController::class, 'save']),
    Route::post('name_skip', '/name/skip', [GuestController::class, 'skip']),
    Route::post('theme', '/theme', [GuestController::class, 'theme']),

    // ---- A page for everyone: imprint, FAQ, ... ---------------------------
    Route::get('page', '/pages/{slug}', [PageController::class, 'show'])
        ->requirements(['slug' => PageRepository::SLUG_RAW]),

    // An uploaded logo as its own resource for <img src="/logo/<id>">.
    Route::get('logo', '/logo/{id}', [AdminController::class, 'logoFile'])
        ->requirements(['id' => $id])->raw(),

    // ---- Administration ---------------------------------------------------
    // Everything the Administration menu leads to sits below /admin.
    Route::get('admin_home', '/admin', [AdminController::class, 'home'])->page('users'),

    Route::get('users', '/admin/users', [UserController::class, 'index']),
    Route::get('user_new', '/admin/users/new', [UserController::class, 'form'])->page('user'),
    Route::get('user_edit', '/admin/users/{id}/edit', [UserController::class, 'form'])
        ->requirements(['id' => $id])->page('user'),
    Route::post('user_save', '/admin/users/save', [UserController::class, 'save']),
    Route::post('user_delete', '/admin/users/{id}/delete', [UserController::class, 'delete'])
        ->requirements(['id' => $id]),

    Route::get('logos', '/admin/logos', [AdminController::class, 'logos']),
    Route::post('logo_upload', '/admin/logos/upload', [AdminController::class, 'logoUpload']),
    // Id 0 activates no logo at all: the word mark comes back.
    Route::post('logo_activate', '/admin/logos/{id}/activate', [AdminController::class, 'logoActivate'])
        ->requirements(['id' => $idOrNone]),
    Route::post('logo_delete', '/admin/logos/{id}/delete', [AdminController::class, 'logoDelete'])
        ->requirements(['id' => $id]),

    // The colours, the message duration and the polling intervals; and the
    // limits on wishing and suggesting. One form each, so it posts to its
    // own address.
    Route::get('ui', '/admin/ui', [AdminController::class, 'ui']),
    Route::post('ui_save', '/admin/ui', [AdminController::class, 'saveUi'])->page('ui'),
    Route::get('limits', '/admin/limits', [AdminController::class, 'limits']),
    Route::post('limits_save', '/admin/limits', [AdminController::class, 'saveLimits'])->page('limits'),

    Route::get('pages', '/admin/pages', [PageController::class, 'index']),
    Route::get('page_new', '/admin/pages/new', [PageController::class, 'form'])->page('page_edit'),
    Route::get('page_edit', '/admin/pages/{id}/edit', [PageController::class, 'form'])
        ->requirements(['id' => $id])->page('page_edit'),
    Route::post('page_save', '/admin/pages/save', [PageController::class, 'save']),
    Route::post('page_delete', '/admin/pages/{id}/delete', [PageController::class, 'delete'])
        ->requirements(['id' => $id]),

    Route::get('footer', '/admin/footer', [FooterController::class, 'index']),
    Route::post('footer_text_save', '/admin/footer/text', [FooterController::class, 'saveText']),
    Route::post('footer_reorder', '/admin/footer/reorder', [FooterController::class, 'reorder']),
    Route::post('footer_add', '/admin/footer/{id}/add', [FooterController::class, 'add'])
        ->requirements(['id' => $id]),
    Route::post('footer_remove', '/admin/footer/{id}/remove', [FooterController::class, 'remove'])
        ->requirements(['id' => $id]),
    Route::post('footer_move', '/admin/footer/{id}/move', [FooterController::class, 'move'])
        ->requirements(['id' => $id]),

    Route::get('languages', '/admin/languages', [LanguageController::class, 'index']),
    Route::post('languages_reorder', '/admin/languages/reorder', [LanguageController::class, 'reorder']),
    Route::post('languages_move', '/admin/languages/{code}/move', [LanguageController::class, 'move'])
        ->requirements(['code' => '[a-z]{2,3}(?:-[a-z0-9]{2,8})?']),
];
