<?php

declare(strict_types=1);

/**
 * Songwunsch -- front controller.
 *
 * Pages (path below the base path, see url() in src/bootstrap.php):
 *   /        songs (start, public)            | /login
 *   /wishes  public view, moderators edit     | /song   (editor)
 *   /users   admin                            | /user   (admin)
 *   /rooms   list of rooms (public)           | /room   (editor: create/edit)
 *   /rooms/<slug>          a room's song list  -- same page as /, in the room
 *   /rooms/<slug>/wishes   a room's wish list  -- same page as /wishes
 *   /rooms/<slug>/manage   pick the room's songs from the master list (editor)
 * Actions (POST to any of these): wish | login | logout
 *                  | delete | clear | reorder | move | pause      (moderator)
 *                  | song_save | song_delete                      (editor)
 *                  | room_save | room_delete                      (editor)
 *                  | room_songs_add | room_songs_remove           (editor)
 *                  | user_save | user_delete | admin_transfer     (admin)
 *                  | pause_all                                    (admin)
 * The admin may do everything. ?lang=<code> switches the UI language.
 * The web server routes every address to this file (.htaccess); addresses of
 * earlier versions (index.php?p=...) are redirected permanently.
 *
 * Before the first data access of a request Schema::ensure() makes sure all
 * tables exist -- regardless of the environment the application runs in.
 */

use Songwunsch\Database;
use Songwunsch\Format;
use Songwunsch\RoomRepository;
use Songwunsch\Schema;
use Songwunsch\Security;
use Songwunsch\Settings;
use Songwunsch\SongRepository;
use Songwunsch\Translator;
use Songwunsch\UserRepository;
use Songwunsch\WishGuard;
use Songwunsch\WishRepository;

require __DIR__ . '/src/bootstrap.php';

$configFile = __DIR__ . '/config.php';
if (!is_file($configFile)) {
    render_fatal(
        'Configuration missing',
        'Please copy <code>config.example.php</code> to <code>config.php</code> and enter the database credentials.'
    );
}

/** @var array<string,mixed> $config */
$config = require $configFile;

// Fix the base path before the first URL is built. If an older config.php
// lacks the value, the BASE_PATH environment variable still applies.
if (isset($config['base_path'])) {
    base_path((string) $config['base_path']);
}
// Cache buster for style.css and app.js; an older config.php without the
// value simply gets no ?v= suffix.
asset_version((string) ($config['version'] ?? getenv('APP_VERSION') ?: ''));

$db     = new Database($config['db']);
$schema = new Schema($db);
$songs  = new SongRepository($db);
$users  = new UserRepository($db);
$rooms  = new RoomRepository($db);
// $wishes and $guard are bound to the room and are created after routing.

// The session cookie is scoped to the base path, so several applications on
// the same domain do not share a session.
$security = new Security($users, base_path() . '/');
$security->startSession();

// --- Language ------------------------------------------------------------
$translator = new Translator(__DIR__ . '/lang');
$lang       = $translator->detect(
    isset($_GET['lang']) ? (string) $_GET['lang'] : null,
    $_SESSION['lang'] ?? null,
    $_COOKIE[Translator::cookieName()] ?? null,
    $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? null,
);
$translator->load($lang);
translator($translator);

// --- Route ---------------------------------------------------------------
// The web server sends every address below the base path here (.htaccess).
// The path names the page; anything unknown is a 404. The prefix is taken
// from SCRIPT_NAME, not from the configuration, so this works in a
// sub-folder as well as behind a proxy that strips the prefix.
$routes = [
    ''        => 'songs',
    '/wishes' => 'wishes',
    '/login'  => 'login',
    '/song'   => 'song',
    '/users'  => 'users',
    '/user'   => 'user',
    '/rooms'  => 'rooms',
    '/room'   => 'room',
];
// Inside a room: /rooms/<slug>, /rooms/<slug>/wishes, /rooms/<slug>/manage.
$roomRoutes = ['' => 'songs', '/wishes' => 'wishes', '/manage' => 'room_songs'];

$requestPath = rawurldecode((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH));
$scriptDir   = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'))), '/');
$route       = $scriptDir !== '' && str_starts_with($requestPath, $scriptDir)
    ? substr($requestPath, strlen($scriptDir))
    : $requestPath;
$route       = rtrim($route, '/');

$room = RoomRepository::defaultRoom();

if ($route === '/index.php') {
    // Address of an earlier version: index.php?p=wishes -> /wishes. Links
    // and bookmarks move permanently; a POST to the old address still works.
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        redirect(url($_GET + ['room' => '']), 301);
    }
    $page = (string) ($_POST['p'] ?? 'songs');
} elseif (isset($routes[$route])) {
    $page = $routes[$route];
} elseif (preg_match('#^/rooms/([^/]+)(/[^/]*)?$#', $route, $m) === 1 && isset($roomRoutes[$m[2] ?? ''])) {
    // The slug is checked against RoomRepository::SLUG_PATTERN before any
    // query; an unknown or malformed room is a 404 like any other address.
    try {
        $schema->ensure();
        $found = $rooms->findBySlug($m[1]);
    } catch (Throwable $e) {
        $found = null;
    }
    if ($found === null) {
        not_found();
    }
    $room = $found;
    $page = $roomRoutes[$m[2] ?? ''];
} else {
    not_found();
}

current_room($room);
$roomId = (int) $room['id'];
$wishes = new WishRepository($db, $roomId);
$guard  = new WishGuard(
    $db,
    new Settings($db),
    (array) ($config['wish_limits'] ?? []),
    (bool) ($config['trust_proxy'] ?? false),
    (int) ($config['wish_min_form_sec'] ?? 2),
    $roomId,
);

if (isset($_GET['lang'])) {
    // Remember the choice and drop the parameter from the address.
    $translator->remember($lang, base_path() . '/', Security::isHttps());
    redirect(url(['p' => $page] + array_diff_key($_GET, ['lang' => true])));
}

$action = (string) ($_POST['a'] ?? '');

// --- Actions (POST) -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$security->checkCsrf($_POST['csrf'] ?? null)) {
        if (wants_json()) {
            send_json(['ok' => false, 'error' => t('Session expired. Please reload the page.')], 403);
        }
        flash('error', t('The session has expired. Please try again.'));
        redirect(url(['p' => $page]));
    }

    try {
        // Logging out works without the database.
        if ($action !== 'logout') {
            $schema->ensure();
        }

        switch ($action) {
            case 'login':
                // First admin from config.php while there are no users yet.
                $users->ensureAdmin($config['auth']);

                $ok = $security->login(trim((string) ($_POST['user'] ?? '')), (string) ($_POST['pass'] ?? ''));
                if ($ok) {
                    flash('ok', t('Logged in as {name}.', ['name' => $security->username()]));
                    redirect(home_for($security));
                }
                // Do not reveal which part was wrong.
                flash('error', t('Username or password is incorrect.'));
                redirect(url(['p' => 'login']));
                // no break

            case 'logout':
                $security->logout();
                redirect(url(['p' => 'songs']));
                // no break

            case 'wish':
                if ($guard->isPaused()) {
                    flash('info', t('The wish list is closed right now.'));
                    redirect(back());
                }

                // Bot hurdles: honeypot and minimum time since the page loaded.
                switch ($guard->checkForm($_POST['t'] ?? null, $_POST['hp_url'] ?? null)) {
                    case WishGuard::CHECK_BOT:
                        // Discard silently -- a script should get no hint
                        // about what it failed on.
                        flash('ok', t('Thanks, your wish is in.'));
                        redirect(back());
                        // no break
                    case WishGuard::CHECK_FAST:
                        flash('error', t('That was very quick – please click “Wish” once more.'));
                        redirect(back());
                        // no break
                    case WishGuard::CHECK_STALE:
                        flash('error', t('The page has been open for a long time – please reload it and wish again.'));
                        redirect(back());
                        // no break
                }

                if ($security->throttled((int) $config['wish_cooldown_sec'])) {
                    flash('error', t('One moment – please do not wish that quickly in a row.'));
                    redirect(back());
                }

                $limit = $guard->limitReached($wishes->count());
                if ($limit !== null) {
                    flash('error', $limit);
                    redirect(back());
                }

                $song = $songs->find((int) ($_POST['key'] ?? 0), $roomId);
                if ($song === null) {
                    flash('error', t('This song was not found.'));
                    redirect(back());
                }

                if (!($config['allow_duplicates'] ?? false) && $wishes->isPending((int) $song['id'])) {
                    flash('info', t('“{title}” is already on the wish list.', ['title' => (string) $song['title']]));
                    redirect(back());
                }

                $wishes->add($song);
                $guard->record();
                $security->markWish();

                flash('ok', t('“{title}” by {artist} is in.', [
                    'title'  => (string) $song['title'],
                    'artist' => (string) $song['artist'],
                ]));
                redirect(back());
                // no break

            // ---- Wish list (moderator) -------------------------------------

            case 'reorder':
                require_role($security, 'wishes');

                // "3,7,1" -- the ids in their new order from top to bottom.
                $ids = array_filter(array_map(
                    'intval',
                    explode(',', (string) ($_POST['order'] ?? '')),
                ));
                $count = $wishes->reorder($ids);

                if (wants_json()) {
                    send_json(['ok' => $count > 0, 'count' => $count]);
                }

                flash($count > 0 ? 'ok' : 'error', $count > 0
                    ? t('Order saved.')
                    : t('The order could not be saved.'));
                redirect(back(url(['p' => 'wishes'])));
                // no break

            case 'move':
                require_role($security, 'wishes');
                $wishes->move(
                    (int) ($_POST['id'] ?? 0),
                    ($_POST['dir'] ?? 'up') === 'down' ? 1 : -1,
                );
                redirect(back(url(['p' => 'wishes'])));
                // no break

            case 'pause':
                require_role($security, 'wishes');
                $paused = (($_POST['state'] ?? '0') === '1');
                $guard->setPaused($paused);
                flash('ok', $paused
                    ? t('Wishing is paused. The audience can see the song list but cannot submit anything.')
                    : t('Wishing is open again.'));
                redirect(url(['p' => 'wishes']));
                // no break

            case 'pause_all':
                // Admin only: 'users' is the area nobody but the admin holds.
                require_role($security, 'users');
                if (($_POST['state'] ?? '0') === '1') {
                    $guard->pauseEverywhere($rooms->ids());
                    flash('ok', t('Wishing is paused in the main room and in every room.'));
                } else {
                    $guard->resumeEverywhere($rooms->ids());
                    flash('ok', t('The pause has been lifted; every room is back to the state it had before.'));
                }
                redirect(url(['p' => 'rooms']));
                // no break

            case 'delete':
                require_role($security, 'wishes');
                $wishes->delete((int) ($_POST['id'] ?? 0))
                    ? flash('ok', t('Wish deleted.'))
                    : flash('error', t('Wish not found.'));
                redirect(back(url(['p' => 'wishes'])));
                // no break

            case 'clear':
                require_role($security, 'wishes');
                $removed = $wishes->deleteAll();
                flash('ok', tn('{n} wish deleted.', '{n} wishes deleted.', $removed));
                redirect(url(['p' => 'wishes']));
                // no break

            // ---- Song list (editor) ----------------------------------------

            case 'song_save':
                require_role($security, 'songs');
                $key = (int) ($_POST['key'] ?? 0); // 0 = new song

                $input = [
                    'artist' => (string) ($_POST['artist'] ?? ''),
                    'title'  => (string) ($_POST['title'] ?? ''),
                    'length' => (string) ($_POST['length'] ?? ''),
                    'genre'  => (string) ($_POST['genre'] ?? ''),
                ];
                $checked = $songs->validate($input);
                $formUrl = url(['p' => 'song', 'key' => $key > 0 ? $key : null, 'back' => back()]);

                if ($checked['errors'] !== []) {
                    remember_input($input, $checked['errors']);
                    flash('error', t('Please check the highlighted fields.'));
                    redirect($formUrl);
                }

                try {
                    if ($key === 0) {
                        $songs->create($checked['values']);
                        flash('ok', t('“{title}” by {artist} has been added to the song list.', [
                            'title'  => $input['title'],
                            'artist' => $input['artist'],
                        ]));
                    } else {
                        $songs->update($key, $checked['values']);
                        flash('ok', t('“{title}” by {artist} has been saved.', [
                            'title'  => $input['title'],
                            'artist' => $input['artist'],
                        ]));
                    }
                } catch (RuntimeException $e) {
                    // Deleted row, missing write permission: keep the input.
                    remember_input($input, ['form' => error_detail($e, $config, $security)]);
                    flash('error', error_detail($e, $config, $security));
                    redirect($formUrl);
                }

                redirect(back());
                // no break

            case 'song_delete':
                require_role($security, 'songs');
                $song = $songs->find((int) ($_POST['key'] ?? 0));

                if ($song === null) {
                    flash('error', t('This song was not found.'));
                    redirect(back());
                }

                $songs->delete((int) $song['id']);
                $rooms->removeSongEverywhere((int) $song['id']);
                flash('ok', t('“{title}” has been removed from the song list. Wishes already received are kept.', [
                    'title' => (string) $song['title'],
                ]));
                redirect(back());
                // no break

            // ---- Rooms (editor) --------------------------------------------

            case 'room_save':
                require_role($security, 'rooms');
                $id       = (int) ($_POST['id'] ?? 0); // 0 = new room
                $existing = $id > 0 ? $rooms->find($id) : null;

                if ($id > 0 && $existing === null) {
                    flash('error', t('This room was not found.'));
                    redirect(url(['p' => 'rooms']));
                }

                $input = [
                    'slug'   => (string) ($_POST['slug'] ?? ''),
                    'name'   => (string) ($_POST['name'] ?? ''),
                    'active' => (string) ($_POST['active'] ?? ''),
                ];
                $checked = $rooms->validate($input, $existing);
                $formUrl = url(['p' => 'room', 'id' => $id > 0 ? $id : null]);

                if ($checked['errors'] !== []) {
                    remember_input($input, $checked['errors']);
                    flash('error', t('Please check the highlighted fields.'));
                    redirect($formUrl);
                }

                if ($existing === null) {
                    $id = $rooms->create($checked['values']);
                } else {
                    $rooms->update($id, $checked['values']);
                }

                // Archiving closes wishing in that room -- guests may still
                // hold the address. Reactivating does not reopen it; that is
                // the moderator's call on the room's wish list.
                $archivedNow = (int) $checked['values']['active'] === 0
                    && ($existing === null || (int) $existing['active'] === 1);
                if ($archivedNow) {
                    $roomGuard = new WishGuard(
                        $db,
                        new Settings($db),
                        (array) ($config['wish_limits'] ?? []),
                        (bool) ($config['trust_proxy'] ?? false),
                        (int) ($config['wish_min_form_sec'] ?? 2),
                        $id,
                    );
                    $roomGuard->setPaused(true);
                }

                if ($existing === null) {
                    flash('ok', t('Room “{name}” has been created. Now pick its songs from the master list.', ['name' => $checked['values']['name']]));
                    redirect(url(['p' => 'room_songs', 'room' => $checked['values']['slug']]));
                }

                flash('ok', $archivedNow
                    ? t('Room “{name}” has been archived; wishing there is paused.', ['name' => $checked['values']['name']])
                    : t('Room “{name}” has been saved.', ['name' => $checked['values']['name']]));
                redirect(url(['p' => 'rooms']));
                // no break

            case 'room_delete':
                require_role($security, 'rooms');
                $target = $rooms->find((int) ($_POST['id'] ?? 0));

                if ($target === null) {
                    flash('error', t('This room was not found.'));
                } elseif ($rooms->delete((int) $target['id'])) {
                    flash('ok', t('Room “{name}” has been deleted together with its wishes.', ['name' => (string) $target['name']]));
                } else {
                    flash('error', t('Deleting was not possible.'));
                }
                redirect(url(['p' => 'rooms']));
                // no break

            case 'room_songs_add':
            case 'room_songs_remove':
                // Pick a room's songs: single ids in key[] or, with all=1,
                // every master song matching the search q.
                require_role($security, 'rooms');
                if ($roomId === RoomRepository::DEFAULT_ID) {
                    flash('error', t('The main room always offers the whole song list.'));
                    redirect(url(['p' => 'songs']));
                }

                $ids = ($_POST['all'] ?? '') === '1'
                    ? $songs->idsMatching((string) ($_POST['q'] ?? ''))
                    : array_map('intval', (array) ($_POST['key'] ?? []));

                $n = $action === 'room_songs_add'
                    ? $rooms->addSongs($roomId, $ids)
                    : $rooms->removeSongs($roomId, $ids);

                flash('ok', $action === 'room_songs_add'
                    ? tn('{n} song added to the room.', '{n} songs added to the room.', $n)
                    : tn('{n} song removed from the room.', '{n} songs removed from the room.', $n));
                redirect(back(url(['p' => 'room_songs'])));
                // no break

            // ---- Users (admin) ---------------------------------------------

            case 'user_save':
                require_role($security, 'users');
                $id       = (int) ($_POST['id'] ?? 0); // 0 = new user
                $existing = $id > 0 ? $users->find($id) : null;

                if ($id > 0 && $existing === null) {
                    flash('error', t('This user was not found.'));
                    redirect(url(['p' => 'users']));
                }

                $input = [
                    'username'        => (string) ($_POST['username'] ?? ''),
                    'password'        => (string) ($_POST['password'] ?? ''),
                    'password2'       => (string) ($_POST['password2'] ?? ''),
                    'role_moderator' => (string) ($_POST['role_moderator'] ?? ''),
                    'role_editor'  => (string) ($_POST['role_editor'] ?? ''),
                    'active'          => (string) ($_POST['active'] ?? ''),
                ];
                $checked = $users->validate($input, $existing);

                // The admin stays active -- otherwise they lock themselves out.
                if ($existing !== null && (int) $existing['is_admin'] === 1) {
                    $checked['values']['active'] = 1;
                }

                $formUrl = url(['p' => 'user', 'id' => $id > 0 ? $id : null]);

                if ($checked['errors'] !== []) {
                    // Passwords never go into the session.
                    unset($input['password'], $input['password2']);
                    remember_input($input, $checked['errors']);
                    flash('error', t('Please check the highlighted fields.'));
                    redirect($formUrl);
                }

                if ($existing === null) {
                    $users->create($checked['values']);
                    flash('ok', t('User “{name}” has been created.', ['name' => $checked['values']['username']]));
                } else {
                    $users->update($id, $checked['values']);
                    // Own password changed: the default-password warning follows suit.
                    if ($id === (int) ($security->user()['id'] ?? 0) && isset($checked['values']['password_hash'])) {
                        $security->notePassword($input['password']);
                    }
                    flash('ok', t('User “{name}” has been saved.', ['name' => $checked['values']['username']]));
                }
                redirect(url(['p' => 'users']));
                // no break

            case 'user_delete':
                require_role($security, 'users');
                $target = $users->find((int) ($_POST['id'] ?? 0));

                if ($target === null) {
                    flash('error', t('This user was not found.'));
                } elseif ((int) $target['is_admin'] === 1) {
                    flash('error', t('The admin cannot be deleted – hand over the admin role first.'));
                } elseif ($users->delete((int) $target['id'])) {
                    flash('ok', t('User “{name}” has been deleted.', ['name' => (string) $target['username']]));
                } else {
                    flash('error', t('Deleting was not possible.'));
                }
                redirect(url(['p' => 'users']));
                // no break

            case 'admin_transfer':
                require_role($security, 'users');
                $self   = $security->user();
                $target = $users->find((int) ($_POST['id'] ?? 0));

                if ($target === null) {
                    flash('error', t('This user was not found.'));
                    redirect(url(['p' => 'users']));
                }

                try {
                    $users->transferAdmin((int) $self['id'], (int) $target['id']);
                    flash('ok', t('The admin role now belongs to “{name}”. You keep your other roles.', [
                        'name' => (string) $target['username'],
                    ]));
                } catch (RuntimeException $e) {
                    flash('error', $e->getMessage());
                }
                // The former admin no longer has access to user management.
                redirect(url(['p' => 'songs']));
                // no break

            default:
                redirect(url(['p' => 'songs']));
        }
    } catch (Throwable $e) {
        flash('error', t('An error occurred: {detail}', ['detail' => error_detail($e, $config, $security)]));
        redirect(url(['p' => $page]));
    }
}

// --- Pages (GET) ------------------------------------------------------------
$view = [
    'page'       => $page,
    'room'       => $room,
    'security'   => $security,
    'translator' => $translator,
    'csrf'       => $security->csrfToken(),
    'flash'      => flash_take(),
    'wishCount'  => null,
    'paused'     => false, // wishing closed by the moderator -- notice in the header
    'roomList'   => [],    // rooms for the switcher in the header
];

try {
    $schema->ensure();
    $view['paused']   = $guard->isPaused();
    $view['roomList'] = $rooms->names();

    switch ($page) {
        case 'login':
            if ($security->isLoggedIn()) {
                redirect(home_for($security));
            }
            $view['title']    = t('Log in');
            $view['template'] = 'login';
            break;

        case 'wishes':
            // Everyone may look at the list; only moderators get controls,
            // and only they may change the sorting -- guests always see the
            // manual (play) order.
            $canEdit = $security->can('wishes');
            $sort    = $canEdit ? (string) ($_GET['sort'] ?? 'manual') : 'manual';
            $dir     = $canEdit ? (string) ($_GET['dir'] ?? 'asc') : 'asc';

            $view['title']     = t('Wish list');
            $view['template']  = 'wishes';
            $view['canEdit']   = $canEdit;
            $view['rows']      = $wishes->all($sort, $dir);
            $view['sort']      = array_key_exists($sort, $wishes->sortableFields()) ? $sort : 'manual';
            $view['dir']       = strtolower($dir) === 'desc' ? 'desc' : 'asc';
            $view['wishCount'] = count($view['rows']);
            break;

        case 'song':
            require_role($security, 'songs');
            $key  = (int) ($_GET['key'] ?? 0); // 0 = new song
            $song = null;

            if ($key > 0) {
                $song = $songs->find($key);
                if ($song === null) {
                    flash('error', t('This song was not found.'));
                    redirect(url(['p' => 'songs']));
                }
            }

            // After a failed validation the input and errors are in the
            // session; otherwise the values come from the database.
            $kept = remembered_input();

            $view['title']    = $key === 0 ? t('Add song') : t('Edit song');
            $view['template'] = 'song';
            $view['repo']     = $songs;
            $view['key']      = $key;
            $view['errors']   = $kept['errors'] ?? [];
            $view['back']     = safe_target($_GET['back'] ?? null) ?? url(['p' => 'songs']);
            $view['values']   = $kept['values'] ?? [
                'artist' => (string) ($song['artist'] ?? ''),
                'title'  => (string) ($song['title'] ?? ''),
                'length' => Format::lengthInput($song['length_sec'] ?? null),
                'genre'  => (string) ($song['genre'] ?? ''),
            ];
            break;

        case 'users':
            require_role($security, 'users');

            $view['title']    = t('Users');
            $view['template'] = 'users';
            $view['rows']     = $users->all();
            $view['selfId']   = (int) $security->user()['id'];
            break;

        case 'user':
            require_role($security, 'users');
            $id   = (int) ($_GET['id'] ?? 0); // 0 = new user
            $user = null;

            if ($id > 0) {
                $user = $users->find($id);
                if ($user === null) {
                    flash('error', t('This user was not found.'));
                    redirect(url(['p' => 'users']));
                }
            }

            $kept = remembered_input();

            $view['title']    = $id === 0 ? t('Add user') : t('Edit user');
            $view['template'] = 'user';
            $view['id']       = $id;
            $view['user']     = $user;
            $view['selfId']   = (int) $security->user()['id'];
            $view['errors']   = $kept['errors'] ?? [];
            $view['values']   = $kept['values'] ?? [
                'username'        => (string) ($user['username'] ?? ''),
                'role_moderator' => (string) ($user['role_moderator'] ?? '0'),
                'role_editor'  => (string) ($user['role_editor'] ?? '0'),
                'active'          => (string) ($user['active'] ?? '1'),
            ];
            break;

        case 'rooms':
            // Everyone sees the active rooms and may switch; editors also
            // manage them and may list archived rooms.
            $canEdit = $security->can('rooms');
            $perPage = max(10, (int) $config['per_page']);
            $q       = trim((string) ($_GET['q'] ?? ''));
            // Editors see every room by default (archived ones tagged), guests
            // only the active ones -- whatever the parameter says.
            $filter  = (string) ($_GET['filter'] ?? 'all');
            $filter  = $canEdit && in_array($filter, RoomRepository::FILTERS, true) ? $filter : 'active';
            $pageNo  = max(1, (int) ($_GET['page'] ?? 1));

            $roomResult = $rooms->search($q, $filter, $pageNo, $perPage);

            $view['title']        = t('Rooms');
            $view['template']     = 'rooms';
            $view['rows']         = $roomResult['rows'];
            $view['total']        = $roomResult['total'];
            $view['q']            = $q;
            $view['filter']       = $filter;
            $view['pageNo']       = $pageNo;
            $view['pages']        = max(1, (int) ceil($roomResult['total'] / $perPage));
            $view['canEdit']      = $canEdit;
            // The admin's switch closes wishing in every room at once and later
            // hands every room its previous state back.
            $view['isAdmin']      = $security->isAdmin();
            $view['pausedAll']    = $view['isAdmin'] && $guard->isPausedEverywhere();
            $view['masterSongs']  = $songs->count();
            $view['masterWishes'] = (new WishRepository($db))->count();
            break;

        case 'room':
            require_role($security, 'rooms');
            $id   = (int) ($_GET['id'] ?? 0); // 0 = new room
            $edit = null;

            if ($id > 0) {
                $edit = $rooms->find($id);
                if ($edit === null) {
                    flash('error', t('This room was not found.'));
                    redirect(url(['p' => 'rooms']));
                }
            }

            $kept = remembered_input();

            $view['title']    = $id === 0 ? t('Add room') : t('Edit room');
            $view['template'] = 'room';
            $view['id']       = $id;
            $view['errors']   = $kept['errors'] ?? [];
            $view['values']   = $kept['values'] ?? [
                'slug'   => (string) ($edit['slug'] ?? ''),
                'name'   => (string) ($edit['name'] ?? ''),
                'active' => (string) ($edit['active'] ?? '1'),
            ];
            break;

        case 'room_songs':
            require_role($security, 'rooms');
            if ($roomId === RoomRepository::DEFAULT_ID) {
                redirect(url(['p' => 'songs']));
            }

            $perPage = max(10, (int) $config['per_page']);
            $sort    = (string) ($_GET['sort'] ?? 'artist');
            $dir     = (string) ($_GET['dir'] ?? 'asc');
            $q       = trim((string) ($_GET['q'] ?? ''));
            $pageNo  = max(1, (int) ($_GET['page'] ?? 1));

            // Two columns: left the master songs not yet in the room (paged),
            // right the room's songs; one search filters both.
            // Local names differ from the view keys: extract() below skips
            // variables that already exist.
            $availableResult = $songs->searchAvailable($q, $sort, $dir, $pageNo, $perPage, $roomId);
            $roomResult      = $songs->search($q, $sort, $dir, 1, 1000, $roomId);

            $view['title']         = t('Songs of the room');
            $view['template']      = 'room_songs';
            $view['available']     = $availableResult['rows'];
            $view['availableTotal'] = $availableResult['total'];
            $view['roomRows']      = $roomResult['rows'];
            $view['roomTotal']     = $roomResult['total'];
            $view['roomSongCount'] = $songs->count($roomId);
            $view['masterCount']   = $songs->count();
            $view['q']             = $q;
            $view['pageNo']        = $pageNo;
            $view['pages']         = max(1, (int) ceil($availableResult['total'] / $perPage));
            break;

        case 'songs':
        default:
            $perPage = max(10, (int) $config['per_page']);
            $sort    = (string) ($_GET['sort'] ?? 'artist');
            $dir     = (string) ($_GET['dir'] ?? 'asc');
            $q       = trim((string) ($_GET['q'] ?? ''));
            $pageNo  = max(1, (int) ($_GET['page'] ?? 1));

            $result = $songs->search($q, $sort, $dir, $pageNo, $perPage, $roomId);

            $view['title']     = t('Song list');
            $view['template']  = 'home';
            $view['formToken'] = $view['paused'] ? '' : $guard->formToken();
            $view['repo']      = $songs;
            $view['rows']      = $result['rows'];
            $view['total']     = $result['total'];
            $view['q']         = $q;
            $view['sort']      = array_key_exists($sort, $songs->sortableFields()) ? $sort : 'artist';
            $view['dir']       = strtolower($dir) === 'desc' ? 'desc' : 'asc';
            $view['pageNo']    = $pageNo;
            $view['perPage']   = $perPage;
            $view['pages']     = max(1, (int) ceil($result['total'] / $perPage));
            break;
    }

    if ($security->can('wishes') && $view['wishCount'] === null) {
        $view['wishCount'] = $wishes->count();
    }
} catch (Throwable $e) {
    $view['title']    = t('Error');
    $view['template'] = 'error';
    $view['message']  = error_detail($e, $config, $security);
}

extract($view, EXTR_SKIP);
require __DIR__ . '/templates/layout.php';
