<?php

declare(strict_types=1);

/**
 * Songwunsch -- front controller.
 *
 * Pages (path below the base path, see url() in src/bootstrap.php):
 *   /        songs (start, public)            | /login
 *   /wishes  public view, moderators edit     | /song   (editor)
 *   /suggestions  everyone suggests, editors adopt or delete
 *   /name    the visitor's name for wishes    | /settings -> /users/<own id>/settings
 *   /pages/<slug>    a page for everyone (imprint, FAQ, ...)
 *   /rooms   list of rooms (public)           | /rooms/new, /rooms/<id>/edit, /rooms/main/edit (editor)
 * Everything the Administration menu leads to sits below /admin (admins only):
 *   /admin/users     | /admin/users/new, /admin/users/<id>/edit
 *   /admin/logos       header logos
 *   /admin/colors      the colours
 *   /admin/limits      limits on wishing and suggesting
 *   /admin/pages     | /admin/pages/new, /admin/pages/<id>/edit (CKEditor, one tab per language)
 *   /admin/footer      which pages the footer links, in which order
 *   /admin/languages   the fallback order of the languages
 *   /admin           -> /admin/users
 *   /rooms/<slug>          a room's song list  -- same page as /, in the room
 *   /rooms/<slug>/wishes   a room's wish list  -- same page as /wishes
 *   /rooms/<slug>/suggestions  suggest from inside the room: the adopted song joins it
 *   /rooms/<slug>/manage   pick the room's songs from the master list (editor)
 * Actions (POST to any of these): wish | suggest | login | logout | name_save | name_skip
 *                  | room_switch (explicit change of room, clears the memory for the main room)
 *                  | delete | clear | reorder | move | pause      (moderator)
 *                  | song_save | song_delete                      (editor)
 *                  | suggestion_delete | suggestions_clear        (editor)
 *                  | room_save | room_delete | main_room_save | room_start (editor)
 *                  | room_songs_add | room_songs_remove           (editor)
 *                  | user_save | user_delete                      (admin)
 *                  | logo_upload | logo_activate | logo_delete    (admin)
 *                  | colors_save | limits_save                    (admin)
 *                  | page_save | page_delete                      (admin)
 *                  | languages_move | languages_reorder            fallback order of the languages (admin)
 *                  | footer_add | footer_remove | footer_move | footer_reorder | footer_text_save (admin)
 *                  | pause_all                                    (admin)
 * Admins may do everything. ?lang=<code> switches the UI language.
 * The web server routes every address to this file (.htaccess); anything
 * not listed here is a 404.
 *
 * Before the first data access of a request Schema::ensure() makes sure all
 * tables exist -- regardless of the environment the application runs in.
 */

use Songwunsch\Database;
use Songwunsch\Format;
use Songwunsch\GuestName;
use Songwunsch\Html;
use Songwunsch\Limits;
use Songwunsch\PageRepository;
use Songwunsch\RoomMemory;
use Songwunsch\RoomRepository;
use Songwunsch\Schema;
use Songwunsch\Security;
use Songwunsch\Settings;
use Songwunsch\Colors;
use Songwunsch\Uploads;
use Songwunsch\SongRepository;
use Songwunsch\SuggestionRepository;
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

// Fix the base path before the first URL is built. Without the value in
// config.php the BASE_PATH environment variable applies.
if (isset($config['base_path'])) {
    base_path((string) $config['base_path']);
}
// Cache buster for style.css and app.js; without a value there is no ?v= suffix.
asset_version((string) ($config['version'] ?? getenv('APP_VERSION') ?: ''));

$db     = new Database($config['db']);
$schema = new Schema($db);
$songs  = new SongRepository($db);
$suggestions = new SuggestionRepository($db);
$users  = new UserRepository($db);
$rooms  = new RoomRepository($db);
$settings = new Settings($db);
$limits   = new Limits($settings); // wish and suggestion limits the admins set; read on first use
$uploads  = new Uploads($db);
// $wishes and $guard are bound to the room and are created after routing.
// The main room may carry a name of its own (Rooms -> Edit on the main room).
try {
    RoomRepository::nameMainRoom((string) $settings->get(RoomRepository::MAIN_NAME_KEY, ''));
} catch (Throwable $e) {
    // No database yet: the translated default stands; the page reports the problem.
}

// The session cookie is scoped to the base path, so several applications on
// the same domain do not share a session.
$security = new Security($users, base_path() . '/');
$security->startSession();
// The visitor's name for the wish list -- a cookie with the same scope.
$nameCookie = new GuestName(base_path() . '/', Security::isHttps());
// The room the visitor chose last -- a cookie with the same scope.
$roomMemory = new RoomMemory(base_path() . '/', Security::isHttps());

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
// The admins' pages: imprint, FAQ, ...; some linked in the footer. They answer
// in the interface language, so they come after it is known.
$pageRepo = new PageRepository($db, $settings, $translator);

// --- Route ---------------------------------------------------------------
// The web server sends every address below the base path here (.htaccess).
// The path names the page; anything unknown is a 404. The prefix is taken
// from SCRIPT_NAME, not from the configuration, so this works in a
// sub-folder as well as behind a proxy that strips the prefix.
$routes = [
    ''        => 'songs',
    '/wishes' => 'wishes',
    '/suggestions' => 'suggestions',
    '/login'  => 'login',
    '/rooms'  => 'rooms',
    '/settings' => 'settings', // redirects to /users/<own id>/settings
    '/name'   => 'name',
    // The Administration menu: admins only, all below /admin.
    '/admin/users'  => 'users',
    '/admin/logos'  => 'logos',
    '/admin/colors' => 'colors',
    '/admin/pages'  => 'pages',
    '/admin/footer' => 'footer',
    '/admin/limits' => 'limits',
    '/admin/languages' => 'languages',
];
// Pages with an id in the path -- see url(): /song/<id>|new,
// /admin/users/<id>/edit, /admin/users/new, /users/<id>/settings,
// /rooms/<id>/edit, /rooms/new, /rooms/main/edit, /admin/pages/<id>/edit,
// /admin/pages/new, /logo/<id>, /suggestions/<id>/adopt, /pages/<slug>. The
// matched values land in these four.
$routeId         = 0;     // 0 = new
$routeMain       = false; // /rooms/main/edit: rename the main room
$routeSuggestion = 0;     // /suggestions/<id>/adopt: the suggestion to adopt
$routeSlug       = '';    // /pages/<slug>: the page to show
// Inside a room: /rooms/<slug>, /rooms/<slug>/wishes, /rooms/<slug>/suggestions,
// /rooms/<slug>/manage.
$roomRoutes = ['' => 'songs', '/wishes' => 'wishes', '/suggestions' => 'suggestions', '/manage' => 'room_songs'];

$requestPath = rawurldecode((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH));
$scriptDir   = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'))), '/');
$route       = $scriptDir !== '' && str_starts_with($requestPath, $scriptDir)
    ? substr($requestPath, strlen($scriptDir))
    : $requestPath;
$route       = rtrim($route, '/');

$room = RoomRepository::defaultRoom();

if (isset($routes[$route])) {
    $page = $routes[$route];
} elseif (preg_match('#^/song/(new|[1-9][0-9]*)$#', $route, $m) === 1) {
    $page    = 'song';
    $routeId = $m[1] === 'new' ? 0 : (int) $m[1];
} elseif (preg_match('#^(/admin/users|/rooms|/admin/pages)/new$#', $route, $m) === 1) {
    // Create: the list's address plus /new. 'new' is therefore no room's
    // machine name (reserved in RoomRepository); a page's address lives
    // under /pages, apart from the form's /admin/pages/new.
    $page = ['/admin/users' => 'user', '/rooms' => 'room', '/admin/pages' => 'page_edit'][$m[1]];
} elseif (preg_match('#^(/admin/users|/rooms|/admin/pages)/([1-9][0-9]*)/edit$#', $route, $m) === 1) {
    // Edit: the list's address, the id, /edit. /rooms/<id>/edit cannot be
    // mistaken for a room, whose address has no /edit.
    $page    = ['/admin/users' => 'user', '/rooms' => 'room', '/admin/pages' => 'page_edit'][$m[1]];
    $routeId = (int) $m[2];
} elseif ($route === '/admin') {
    // The users -- the menu's entry an admin needs most.
    redirect(url(['p' => 'users']));
} elseif ($route === '/rooms/main/edit') {
    $page      = 'room';
    $routeMain = true;
} elseif (preg_match('#^/users/([1-9][0-9]*)/settings$#', $route, $m) === 1) {
    $page    = 'settings';
    $routeId = (int) $m[1];
} elseif (preg_match('#^/logo/([1-9][0-9]*)$#', $route, $m) === 1) {
    $page    = 'logo';
    $routeId = (int) $m[1];
} elseif (preg_match('#^/pages/([^/]+)$#', $route, $m) === 1 && preg_match(PageRepository::SLUG_PATTERN, $m[1]) === 1) {
    // The page itself is looked up below, once the database is known; an
    // unknown slug is a 404 like any other unknown address.
    $page      = 'page';
    $routeSlug = $m[1];
} elseif (preg_match('#^/suggestions/([1-9][0-9]*)/adopt$#', $route, $m) === 1) {
    $page            = 'song';
    $routeSuggestion = (int) $m[1];
} elseif (preg_match('#^/rooms/([^/]+)(/[^/]*)?$#', $route, $m) === 1 && isset($roomRoutes[$m[2] ?? ''])) {
    // The slug is checked against RoomRepository::SLUG_PATTERN before any
    // query. An unknown or malformed room is no dead end: a link to a room
    // that has since been deleted or renamed leads to the start page, where
    // the remembered room or the start room takes over, with a short notice.
    try {
        $schema->ensure();
        $found = $rooms->findBySlug($m[1]);
    } catch (Throwable $e) {
        $found = null;
    }
    if ($found === null) {
        flash('info', t('There is no room at this address – here is the start page.'));
        redirect(url(['p' => 'songs', 'room' => '']));
    }
    $room = $found;
    $page = $roomRoutes[$m[2] ?? ''];
} else {
    not_found();
}

// --- The remembered room (cookie, see RoomMemory) --------------------------
// /rooms/<slug>/... names its room and is remembered. The bare /, /wishes,
// /suggestions redirect into the remembered room -- every time; only the
// explicit switch to the main room (action room_switch) remembers the main
// room instead. Pages without a room in their address (/rooms, /users, ...)
// stay in the remembered room as well, so the context never changes on its
// own. A visitor without any memory who opens a bare address lands in the
// start room the editors set (Rooms -> "As start room"), if there is one.
$roomBound  = in_array($page, ['songs', 'wishes', 'suggestions', 'room_songs'], true);
$remembered = $roomMemory->slug();
$bareMain   = $roomBound && (int) $room['id'] === RoomRepository::DEFAULT_ID;
if ($remembered === null && $bareMain && $_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $schema->ensure();
        $startId = (int) $settings->get(RoomRepository::START_ROOM_KEY, '0');
        $start   = $startId > 0 ? $rooms->find($startId) : null;
    } catch (Throwable $e) {
        $start = null;
    }
    // An archived start room does not receive visitors; they stay in the main room.
    if ($start !== null && (int) $start['active'] === 1) {
        redirect(url(array_merge(['p' => $page, 'room' => (string) $start['slug']], $_GET)));
    }
}
$useMemory  = $remembered !== null && $remembered !== ''
    && (!$roomBound || (int) $room['id'] === RoomRepository::DEFAULT_ID);
if ($useMemory) {
    try {
        $schema->ensure();
        $kept = $rooms->findBySlug($remembered);
    } catch (Throwable $e) {
        $kept = null;
    }
    if ($kept === null) {
        // The room is gone: the memory goes with it.
        $roomMemory->forget();
    } elseif ($roomBound && $_SERVER['REQUEST_METHOD'] === 'GET') {
        redirect(url(array_merge(['p' => $page, 'room' => (string) $kept['slug']], $_GET)));
    } else {
        // A POST to the bare address, or a page outside any room: handle it
        // in the remembered room.
        $room = $kept;
    }
}
if ($roomBound && (int) $room['id'] !== RoomRepository::DEFAULT_ID) {
    $roomMemory->remember((string) $room['slug']);
}

current_room($room);
$roomId = (int) $room['id'];
$wishes = new WishRepository($db, $roomId);
$guard  = new WishGuard(
    $db,
    $settings,
    $limits,
    (bool) ($config['trust_proxy'] ?? false),
    $roomId,
);

if (isset($_GET['lang'])) {
    // Remember the choice and drop the parameter from the address.
    $translator->remember($lang, base_path() . '/', Security::isHttps());
    // 'slug' keeps a reader's page (/pages/<slug>) on its address; url()
    // ignores it everywhere else.
    redirect(url(['p' => $page, 'slug' => $routeSlug] + array_diff_key($_GET, ['lang' => true])));
}

$action = (string) ($_POST['a'] ?? '');

// --- Actions (POST) -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$security->checkCsrf($_POST['csrf'] ?? null)) {
        if (wants_json()) {
            send_json(['ok' => false, 'error' => t('Session expired. Please reload the page.')], 403);
        }
        flash('error', t('The session has expired. Please try again.'));
        redirect(url(['p' => $page, 'slug' => $routeSlug]));
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

            case 'settings_save':
                // Which deletions ask this user for confirmation -- only the
                // kinds their roles may delete. Unchecked boxes are not
                // posted, so every kind is written explicitly.
                require_login($security);
                $selfId = (int) $security->user()['id'];
                foreach (deletable_kinds($security) as $what) {
                    $settings->setConfirmDelete($selfId, $what, (string) ($_POST['confirm_' . $what] ?? '') === '1');
                }
                flash('ok', t('Settings saved.'));
                redirect(url(['p' => 'settings', 'id' => $selfId]));
                // no break

            case 'colors_save':
                // The site's colours (Colours), admins only: one hex colour per
                // area of use, or nothing for the built-in colour.
                require_role($security, 'users');
                $input = [];
                foreach (Colors::AREAS as $area) {
                    $input[$area] = (string) ($_POST[$area] ?? '');
                }
                $checked = Colors::validate($input);
                if ($checked['errors'] !== []) {
                    remember_input($input, $checked['errors']);
                    flash('error', t('Please check the highlighted fields.'));
                    redirect(url(['p' => 'colors']));
                }
                Colors::save($settings, $checked['values']);
                flash('ok', t('The colours have been saved.'));
                redirect(url(['p' => 'colors']));
                // no break

            case 'limits_save':
                // Limits on wishing and suggesting, admins only, for every room.
                require_role($security, 'users');
                $input = [];
                foreach (array_keys(Limits::FIELDS) as $name) {
                    $input[$name] = (string) ($_POST[$name] ?? '');
                }
                $checked = $limits->validate($input);
                if ($checked['errors'] !== []) {
                    remember_input($input, $checked['errors']);
                    flash('error', t('Please check the highlighted fields.'));
                    redirect(url(['p' => 'limits']));
                }
                $limits->save($checked['values']);
                flash('ok', t('The limits have been saved.'));
                redirect(url(['p' => 'limits']));
                // no break

            case 'logo_upload':
                // Header logos, admins only. Kept in the database (Uploads);
                // the new one may go live right away.
                require_role($security, 'users');
                $check = $uploads->check($_FILES['logo'] ?? []);
                if ($check['errors'] !== []) {
                    flash('error', implode(' ', $check['errors']));
                    redirect(url(['p' => 'logos']));
                }
                $newId = $uploads->add(Uploads::LOGO, $check);
                if ((string) ($_POST['activate'] ?? '') === '1') {
                    $settings->set(Settings::LOGO_ID, (string) $newId);
                    flash('ok', t('The logo has been uploaded and is live in the header.'));
                } else {
                    flash('ok', t('The logo has been uploaded – switch it live below when you want it shown.'));
                }
                redirect(url(['p' => 'logos']));
                // no break

            case 'logo_activate':
                // Exactly one logo is live -- or none: id 0 brings the word mark back.
                require_role($security, 'users');
                $id = (int) ($_POST['id'] ?? 0);
                if ($id > 0 && $uploads->info($id) === null) {
                    flash('error', t('This logo was not found.'));
                    redirect(url(['p' => 'logos']));
                }
                $settings->set(Settings::LOGO_ID, (string) $id);
                flash('ok', $id > 0 ? t('The header shows this logo now.') : t('The header shows the word mark again.'));
                redirect(url(['p' => 'logos']));
                // no break

            case 'logo_delete':
                require_role($security, 'users');
                $id = (int) ($_POST['id'] ?? 0);
                if (!$uploads->delete($id)) {
                    flash('error', t('This logo was not found.'));
                    redirect(url(['p' => 'logos']));
                }
                if ((int) $settings->get(Settings::LOGO_ID, '0') === $id) {
                    $settings->set(Settings::LOGO_ID, '0');
                    flash('ok', t('The logo has been deleted – the header shows the word mark again.'));
                } else {
                    flash('ok', t('The logo has been deleted.'));
                }
                redirect(url(['p' => 'logos']));
                // no break

            case 'page_save':
                // A page: imprint, FAQ, ... Admins only. One form for every
                // language of the menu: title[<code>] and body[<code>]; a
                // language left empty is not part of the page. The bodies
                // are HTML from the editor and are reduced to the allowed
                // tags in validate() -- what is stored is what visitors get.
                require_role($security, 'users');
                $id       = (int) ($_POST['id'] ?? 0); // 0 = new page
                $existing = $id > 0 ? $pageRepo->find($id) : null;

                if ($id > 0 && $existing === null) {
                    flash('error', t('This page was not found.'));
                    redirect(url(['p' => 'pages']));
                }

                // "Remove <language>" on a tab: the language goes right away,
                // nothing else of the form is saved. A page keeps at least one.
                $remove = strtolower((string) ($_POST['remove_lang'] ?? ''));
                if ($remove !== '' && $existing !== null) {
                    $label = $translator->available()[$remove] ?? strtoupper($remove);
                    if ($pageRepo->removeLanguage($id, $remove)) {
                        flash('ok', t('“{title}” is no longer available in {language}.', ['title' => (string) $existing['title'], 'language' => $label]));
                    } else {
                        flash('error', t('{language} could not be removed: a page needs at least one language.', ['language' => $label]));
                    }
                    redirect(url(['p' => 'page_edit', 'id' => $id]) . '#lang-' . rawurlencode($remove));
                }

                $strings = static fn (mixed $list): array => is_array($list)
                    ? array_map(static fn (mixed $v): string => is_scalar($v) ? (string) $v : '', $list)
                    : [];
                $input = [
                    'slug'  => (string) ($_POST['slug'] ?? ''),
                    'title' => $strings($_POST['title'] ?? null),
                    'body'  => $strings($_POST['body'] ?? null),
                ];
                $checked = $pageRepo->validate($input, $existing);
                $formUrl = url(['p' => 'page_edit', 'id' => $id > 0 ? $id : null]);

                if ($checked['errors'] !== []) {
                    remember_input($input, $checked['errors']);
                    flash('error', t('Please check the highlighted fields.'));
                    redirect($formUrl);
                }

                $title = $pageRepo->titleOf($checked['values']['versions']);
                if ($existing === null) {
                    $pageRepo->create($checked['values']);
                    flash('ok', t('Page “{title}” has been created. It is reachable under its address; the footer links it once you add it there.', ['title' => $title]));
                } else {
                    $pageRepo->update($id, $checked['values']);
                    flash('ok', t('Page “{title}” has been saved.', ['title' => $title]));
                }
                redirect(url(['p' => 'pages']));
                // no break

            case 'page_delete':
                // Deleting takes the page out of the footer as well, and its
                // versions in other languages with it.
                require_role($security, 'users');
                $target = $pageRepo->find((int) ($_POST['id'] ?? 0));

                if ($target === null) {
                    flash('error', t('This page was not found.'));
                } elseif ($pageRepo->delete((int) $target['id'])) {
                    flash('ok', t('Page “{title}” has been deleted.', ['title' => (string) $target['title']]));
                } else {
                    flash('error', t('Deleting was not possible.'));
                }
                redirect(url(['p' => 'pages']));
                // no break

            // ---- The languages (admins) ------------------------------------

            case 'languages_move':
                // The fallback order of the languages: one step (up, down) or
                // to the very end (top, bottom) -- the keyboard's way and the
                // way without JavaScript.
                require_role($security, 'users');
                $dir = (string) ($_POST['dir'] ?? 'up');
                $pageRepo->moveLanguage((string) ($_POST['code'] ?? ''), in_array($dir, ['up', 'down', 'top', 'bottom'], true) ? $dir : 'up');
                redirect(url(['p' => 'languages']));
                // no break

            case 'languages_reorder':
                // "de,en,fr" -- the languages in their new order, from drag &
                // drop (app.js, the same code as the wish list's).
                require_role($security, 'users');
                $saved = $pageRepo->reorderLanguages(explode(',', (string) ($_POST['order'] ?? '')));

                if (wants_json()) {
                    send_json(['ok' => $saved]);
                }

                flash($saved ? 'ok' : 'error', $saved ? t('Order saved.') : t('The order could not be saved.'));
                redirect(url(['p' => 'languages']));
                // no break

            // ---- The footer (admins) ---------------------------------------

            case 'footer_text_save':
                // The operator's own footer line -- credits, a link -- below
                // the page links, one per language of the menu (text[<code>])
                // like a page's versions. Admins only; the HTML is reduced to
                // what the pages may contain (Html), an empty language drops
                // its line.
                require_role($security, 'users');
                $texts = is_array($_POST['text'] ?? null)
                    ? array_map(static fn (mixed $v): string => is_scalar($v) ? (string) $v : '', $_POST['text'])
                    : [];
                $kept = $pageRepo->saveFooterLines($texts);
                flash('ok', $kept === 0 ? t('The footer line has been removed.') : t('The footer line has been saved.'));
                redirect(url(['p' => 'footer']));
                // no break

            case 'footer_add':
                // Link a page at the end of the footer.
                require_role($security, 'users');
                $target = $pageRepo->find((int) ($_POST['id'] ?? 0));
                if ($target === null) {
                    flash('error', t('This page was not found.'));
                } else {
                    $pageRepo->addToFooter((int) $target['id']);
                    flash('ok', t('“{title}” is linked in the footer now.', ['title' => (string) $target['title']]));
                }
                redirect(url(['p' => 'footer']));
                // no break

            case 'footer_remove':
                // Take a page out of the footer; it keeps its address.
                require_role($security, 'users');
                $target = $pageRepo->find((int) ($_POST['id'] ?? 0));
                if ($target === null) {
                    flash('error', t('This page was not found.'));
                } else {
                    $pageRepo->removeFromFooter((int) $target['id']);
                    flash('ok', t('“{title}” is no longer linked in the footer. It stays reachable under its address.', ['title' => (string) $target['title']]));
                }
                redirect(url(['p' => 'footer']));
                // no break

            case 'footer_move':
                // One step (up, down) or to the very end (top, bottom) -- the
                // keyboard's way and the way without JavaScript.
                require_role($security, 'users');
                $dir = (string) ($_POST['dir'] ?? 'up');
                $pageRepo->moveInFooter((int) ($_POST['id'] ?? 0), in_array($dir, ['up', 'down', 'top', 'bottom'], true) ? $dir : 'up');
                redirect(url(['p' => 'footer']));
                // no break

            case 'footer_reorder':
                // "3,7,1" -- the footer's links in their new order, from drag
                // & drop (app.js, the same code as the wish list's).
                require_role($security, 'users');
                $count = $pageRepo->reorderFooter(array_filter(array_map(
                    'intval',
                    explode(',', (string) ($_POST['order'] ?? '')),
                )));

                if (wants_json()) {
                    send_json(['ok' => $count > 0, 'count' => $count]);
                }

                flash($count > 0 ? 'ok' : 'error', $count > 0
                    ? t('Order saved.')
                    : t('The order could not be saved.'));
                redirect(url(['p' => 'footer']));
                // no break

            case 'password_save':
                // A signed-in user -- admins included -- changes their own
                // password: the current one proves it is really them (a
                // forgotten unlocked screen must not be enough).
                require_login($security);
                $self = $security->user();

                $current = (string) ($_POST['current_password'] ?? '');
                $new     = (string) ($_POST['password'] ?? '');
                $errors  = [];
                if (!password_verify($current, (string) $self['password_hash'])) {
                    $errors['current_password'] = t('The current password is not right.');
                }
                $check = $new === ''
                    ? ['errors' => ['password' => t('{field} is required.', ['field' => t('New password')])], 'hash' => null]
                    : $users->checkPassword($new, (string) ($_POST['password2'] ?? ''));
                $errors += $check['errors'];

                if ($errors !== []) {
                    // Passwords never go into the session -- only which field failed.
                    remember_input([], $errors);
                    flash('error', t('Please check the highlighted fields.'));
                    redirect(url(['p' => 'settings', 'id' => (int) $self['id']]));
                }

                $users->setPassword((int) $self['id'], (string) $check['hash']);
                $security->notePassword($new);
                flash('ok', t('Your password has been changed.'));
                redirect(url(['p' => 'settings', 'id' => (int) $self['id']]));
                // no break

            case 'logout':
                $security->logout();
                redirect(url(['p' => 'songs']));
                // no break

            case 'name_save':
                // The visitor's name for the wish list, kept in a cookie. An
                // empty name removes it; wishes then carry no name.
                $name = GuestName::clean((string) ($_POST['name'] ?? ''));
                $nameCookie->remember($name);
                flash('ok', $name === ''
                    ? t('Your wishes now carry no name.')
                    : t('Hello {name}! Your wishes now carry your name.', ['name' => $name]));
                redirect(back(url(['p' => 'songs'])));
                // no break

            case 'name_skip':
                // "Not now" -- stop asking for this session.
                $nameCookie->skip();
                redirect(back(url(['p' => 'songs'])));
                // no break

            case 'room_switch':
                // Explicit change of room from the switcher or the room list.
                // Rooms are reached through their address; this is the way to
                // the main room, which has none of its own: the memory is
                // cleared, so / means the main room again. 'to' names the
                // page to land on -- the same sub-page the visitor is on.
                // From a page without a room in its address (users, admin,
                // login, ...) 'back' names that page: only the remembered
                // room changes, and the visitor stays where they are.
                $to = (string) ($_POST['to'] ?? 'songs');
                $to = in_array($to, ['songs', 'wishes', 'suggestions'], true) ? $to : 'songs';
                $slug = (string) ($_POST['slug'] ?? '');
                $stay = safe_target($_POST['back'] ?? null);
                if ($slug === '') {
                    // The main room, chosen on purpose -- remembered as such,
                    // so the start room does not take over on the next visit.
                    $roomMemory->remember('');
                    redirect($stay ?? url(['p' => $to, 'room' => '']));
                }
                $target = $rooms->findBySlug($slug);
                if ($target === null) {
                    flash('error', t('This room was not found.'));
                    redirect(url(['p' => 'rooms']));
                }
                $roomMemory->remember($slug);
                redirect($stay ?? url(['p' => $to, 'room' => $slug]));
                // no break

            case 'guest_view':
                // Look at the site as a visitor without a login, or back. The
                // account is checked directly: in the guest view isLoggedIn()
                // already answers no, which is the whole point.
                if ($security->account() === null) {
                    flash('info', t('Please log in first.'));
                    redirect(url(['p' => 'login']));
                }
                $on = (string) ($_POST['on'] ?? '') === '1';
                $security->setGuestView($on);
                flash('info', $on
                    ? t('You now see the site as a guest does. Your own view is back in the account menu.')
                    : t('Back to your own view.'));
                // Stay on the page -- unless it is one a guest may not see;
                // then the song list, like for any stranger. The form posts
                // to the current page, so $page is the one being looked at.
                $public = in_array($page, ['songs', 'wishes', 'suggestions', 'rooms', 'login', 'page'], true);
                redirect($on && !$public ? url(['p' => 'songs']) : back(url(['p' => $page])));
                // no break

            case 'wish':
                if ($guard->isPaused()) {
                    flash('info', t('The room is closed right now.'));
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

                if ($security->throttled($limits->get('wish_cooldown_sec'))) {
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

                if (!$limits->allowDuplicates() && $wishes->isPending((int) $song['id'])) {
                    flash('info', t('“{title}” is already on the wish list.', ['title' => (string) $song['title']]));
                    redirect(back());
                }

                $wishes->add($song, $nameCookie->current());
                $guard->touch();
                $guard->record();
                $security->markWish();

                flash('ok', t('“{title}” by {artist} is in.', [
                    'title'  => (string) $song['title'],
                    'artist' => (string) $song['artist'],
                ]));
                redirect(back());
                // no break

            case 'suggest':
                // A guest names a song that is missing from the repertoire.
                // Same bot hurdles as wishing (honeypot, signed timestamp),
                // its own session cooldown and a cap on open suggestions.
                $suggestUrl = url(['p' => 'suggestions']);

                // The moderator's pause closes suggesting in the room as well.
                if ($guard->isPaused()) {
                    flash('info', t('The room is closed right now – no wishes and no suggestions.'));
                    redirect($suggestUrl);
                }

                switch ($guard->checkForm($_POST['t'] ?? null, $_POST['hp_url'] ?? null)) {
                    case WishGuard::CHECK_BOT:
                        flash('ok', t('Thanks, your suggestion is in.'));
                        redirect($suggestUrl);
                        // no break
                    case WishGuard::CHECK_FAST:
                        flash('error', t('That was very quick – please click “Suggest” once more.'));
                        redirect($suggestUrl);
                        // no break
                    case WishGuard::CHECK_STALE:
                        flash('error', t('The page has been open for a long time – please reload it and suggest again.'));
                        redirect($suggestUrl);
                        // no break
                }

                if ($security->throttled($limits->get('suggestion_cooldown_sec'), 'suggestion')) {
                    flash('error', t('One moment – please do not suggest that quickly in a row.'));
                    redirect($suggestUrl);
                }

                $input = [
                    'artist' => (string) ($_POST['artist'] ?? ''),
                    'title'  => (string) ($_POST['title'] ?? ''),
                ];
                $checked = $suggestions->validate($input);

                if ($checked['errors'] !== []) {
                    remember_input($input, $checked['errors']);
                    flash('error', t('Please check the highlighted fields.'));
                    redirect($suggestUrl);
                }

                $maxOpen = $limits->get('suggestion_max_open');
                if ($maxOpen > 0 && $suggestions->count() >= $maxOpen) {
                    remember_input($input, []);
                    flash('error', t('The suggestion box is full – please try again later.'));
                    redirect($suggestUrl);
                }

                $artist = $checked['values']['artist'];
                $title  = $checked['values']['title'];

                if ($songs->exists($artist, $title)) {
                    flash('info', t('“{title}” by {artist} is already on the repertoire – you can wish for it right away.', [
                        'title'  => $title,
                        'artist' => $artist,
                    ]));
                    redirect($suggestUrl);
                }

                if ($suggestions->isPending($artist, $title)) {
                    flash('info', t('“{title}” by {artist} has already been suggested.', [
                        'title'  => $title,
                        'artist' => $artist,
                    ]));
                    redirect($suggestUrl);
                }

                // The room the guest is in goes with the suggestion: once
                // adopted, the song is offered there right away.
                $suggestions->add($checked['values'], $nameCookie->current(), $roomId);
                $settings->increment(SuggestionRepository::REVISION_KEY);
                $security->markWish('suggestion');

                flash('ok', t('Thanks! “{title}” by {artist} has been passed on to the editors.', [
                    'title'  => $title,
                    'artist' => $artist,
                ]));
                redirect($suggestUrl);
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
                if ($count > 0) {
                    $guard->touch();
                }

                if (wants_json()) {
                    send_json(['ok' => $count > 0, 'count' => $count]);
                }

                flash($count > 0 ? 'ok' : 'error', $count > 0
                    ? t('Order saved.')
                    : t('The order could not be saved.'));
                redirect(back(url(['p' => 'wishes'])));
                // no break

            case 'move':
                // One step (up, down) or to the very end (top, bottom).
                require_role($security, 'wishes');
                $dir = (string) ($_POST['dir'] ?? 'up');
                $moved = $dir === 'top' || $dir === 'bottom'
                    ? $wishes->moveToEnd((int) ($_POST['id'] ?? 0), $dir === 'top')
                    : $wishes->move((int) ($_POST['id'] ?? 0), $dir === 'down' ? 1 : -1);
                if ($moved) {
                    $guard->touch();
                }
                redirect(back(url(['p' => 'wishes'])));
                // no break

            case 'pause':
                // Close or open a room: the current one (button in the header
                // notice) or any room named by id (button in the room list).
                require_role($security, 'wishes');
                $targetId   = isset($_POST['id']) ? (int) $_POST['id'] : $roomId;
                $targetRoom = $targetId === RoomRepository::DEFAULT_ID ? RoomRepository::defaultRoom() : $rooms->find($targetId);
                if ($targetRoom === null) {
                    flash('error', t('This room was not found.'));
                    redirect(back(url(['p' => 'rooms'])));
                }

                $targetGuard = $targetId === $roomId ? $guard : new WishGuard(
                    $db,
                    $settings,
                    $limits,
                    (bool) ($config['trust_proxy'] ?? false),
                    $targetId,
                );
                $paused = (($_POST['state'] ?? '0') === '1');
                $targetGuard->setPaused($paused);
                flash('ok', $paused
                    ? t('“{name}” is closed. The audience can see its repertoire but cannot wish or suggest anything there.', ['name' => (string) $targetRoom['name']])
                    : t('“{name}” is open again.', ['name' => (string) $targetRoom['name']]));
                redirect(back(url(['p' => $page])));
                // no break

            case 'pause_all':
                // Admins only: 'users' is the area nobody but admins hold.
                require_role($security, 'users');
                if (($_POST['state'] ?? '0') === '1') {
                    $guard->pauseEverywhere($rooms->ids());
                    flash('ok', t('“General” and every room are closed.'));
                } else {
                    $guard->resumeEverywhere($rooms->ids());
                    flash('ok', t('The closing is lifted; every room is back to the state it had before.'));
                }
                redirect(url(['p' => 'rooms']));
                // no break

            case 'delete':
                require_role($security, 'wishes');
                if ($wishes->delete((int) ($_POST['id'] ?? 0))) {
                    $guard->touch();
                    flash('ok', t('Wish deleted.'));
                } else {
                    flash('error', t('Wish not found.'));
                }
                redirect(back(url(['p' => 'wishes'])));
                // no break

            case 'clear':
                require_role($security, 'wishes');
                $removed = $wishes->deleteAll();
                $guard->touch();
                flash('ok', tn('{n} wish deleted.', '{n} wishes deleted.', $removed));
                redirect(url(['p' => 'wishes']));
                // no break

            // ---- Song list (editor) ----------------------------------------

            case 'song_save':
                require_role($security, 'songs');
                $key = (int) ($_POST['key'] ?? 0); // 0 = new song
                // A new song may adopt a suggestion: the suggestion's id
                // travels with the form and is deleted once the song is in.
                $adopting = $key === 0 ? (int) ($_POST['suggestion'] ?? 0) : 0;

                $input = [
                    'artist' => (string) ($_POST['artist'] ?? ''),
                    'title'  => (string) ($_POST['title'] ?? ''),
                    'length' => (string) ($_POST['length'] ?? ''),
                    'genre'  => (string) ($_POST['genre'] ?? ''),
                ];
                $checked = $songs->validate($input);
                $formUrl = url([
                    'p'          => 'song',
                    'id'         => $key > 0 ? $key : null,
                    'suggestion' => $adopting > 0 ? $adopting : null,
                    'back'       => back(),
                ]);

                if ($checked['errors'] !== []) {
                    remember_input($input, $checked['errors']);
                    flash('error', t('Please check the highlighted fields.'));
                    redirect($formUrl);
                }

                try {
                    if ($key === 0) {
                        $newId = $songs->create($checked['values']);
                        $args  = ['title' => $input['title'], 'artist' => $input['artist']];

                        // The suggestion has served its purpose. If someone
                        // deleted it meanwhile, the song is in all the same.
                        // A suggestion made inside a room puts the song into
                        // that room as well -- unless the room is gone.
                        $adopted  = $adopting > 0 ? $suggestions->find($adopting) : null;
                        $joinRoom = $adopted !== null && (int) $adopted['room_id'] > 0
                            ? $rooms->find((int) $adopted['room_id'])
                            : null;
                        if ($adopting > 0) {
                            $suggestions->delete($adopting);
                            $settings->increment(SuggestionRepository::REVISION_KEY);
                        }
                        if ($joinRoom !== null) {
                            $rooms->addSongs((int) $joinRoom['id'], [$newId]);
                        }
                        // An adopted suggestion is a wish already: it goes
                        // onto the wish list of the room it was made in, in
                        // the name of whoever suggested it.
                        if ($adopted !== null) {
                            $wishRoomId = $joinRoom !== null ? (int) $joinRoom['id'] : RoomRepository::DEFAULT_ID;
                            $newSong    = $songs->find($newId);
                            if ($newSong !== null) {
                                (new WishRepository($db, $wishRoomId))->add($newSong, (string) ($adopted['suggester'] ?? ''));
                                $wishGuard = $wishRoomId === $roomId ? $guard : new WishGuard(
                                    $db,
                                    $settings,
                                    $limits,
                                    (bool) ($config['trust_proxy'] ?? false),
                                    $wishRoomId,
                                );
                                $wishGuard->touch();
                            }
                        }

                        flash('ok', match (true) {
                            $joinRoom !== null => t('“{title}” by {artist} has been added to the repertoire and to room “{room}”, put on its wish list and taken off the suggestions.', $args + ['room' => (string) $joinRoom['name']]),
                            $adopted !== null  => t('“{title}” by {artist} has been added to the repertoire, put on the wish list and taken off the suggestions.', $args),
                            default            => t('“{title}” by {artist} has been added to the repertoire.', $args),
                        });
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
                flash('ok', t('“{title}” has been removed from the repertoire. Wishes already received are kept.', [
                    'title' => (string) $song['title'],
                ]));
                redirect(back());
                // no break

            // ---- Song suggestions (editor) ---------------------------------

            case 'suggestion_delete':
                require_role($security, 'suggestions');
                if ($suggestions->delete((int) ($_POST['id'] ?? 0))) {
                    $settings->increment(SuggestionRepository::REVISION_KEY);
                    flash('ok', t('Suggestion deleted.'));
                } else {
                    flash('error', t('This suggestion was not found.'));
                }
                redirect(back(url(['p' => 'suggestions'])));
                // no break

            case 'suggestions_clear':
                require_role($security, 'suggestions');
                $removed = $suggestions->deleteAll();
                $settings->increment(SuggestionRepository::REVISION_KEY);
                flash('ok', tn('{n} suggestion deleted.', '{n} suggestions deleted.', $removed));
                redirect(url(['p' => 'suggestions']));
                // no break

            // ---- Rooms (editor) --------------------------------------------

            case 'room_start':
                // Which room a visitor without any remembered room lands in
                // when opening the bare address. 0 = the main room (no setting).
                require_role($security, 'rooms');
                $startId = (int) ($_POST['id'] ?? 0);
                if ($startId === RoomRepository::DEFAULT_ID) {
                    $settings->delete(RoomRepository::START_ROOM_KEY);
                    flash('ok', t('New visitors start in “General” again.'));
                } else {
                    $startRoom = $rooms->find($startId);
                    if ($startRoom === null) {
                        flash('error', t('This room was not found.'));
                        redirect(url(['p' => 'rooms']));
                    }
                    $settings->set(RoomRepository::START_ROOM_KEY, (string) $startId);
                    flash('ok', t('New visitors now start in “{name}”.', ['name' => (string) $startRoom['name']]));
                }
                redirect(back(url(['p' => 'rooms'])));
                // no break

            case 'main_room_save':
                // Rename the main room. It has no row of its own; the name
                // lives in the settings. Empty means back to the default.
                require_role($security, 'rooms');
                $name = trim(preg_replace('/\s+/u', ' ', (string) ($_POST['name'] ?? '')) ?? '');
                if (mb_strlen($name) > RoomRepository::MAX_NAME) {
                    remember_input(['name' => $name], ['name' => t('{field} is too long: at most {max} characters.', ['field' => t('Name'), 'max' => RoomRepository::MAX_NAME])]);
                    flash('error', t('Please check the highlighted fields.'));
                    redirect(url(['p' => 'room', 'main' => 1]));
                }
                if ($name === '') {
                    $settings->delete(RoomRepository::MAIN_NAME_KEY);
                    RoomRepository::nameMainRoom('');
                    flash('ok', t('“General” has its default name again.'));
                } else {
                    $settings->set(RoomRepository::MAIN_NAME_KEY, $name);
                    RoomRepository::nameMainRoom($name);
                    flash('ok', t('“General” is now called “{name}”.', ['name' => $name]));
                }
                redirect(url(['p' => 'rooms']));
                // no break

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
                    'listed' => (string) ($_POST['listed'] ?? ''),
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
                        $settings,
                        $limits,
                        (bool) ($config['trust_proxy'] ?? false),
                        $id,
                    );
                    $roomGuard->setPaused(true);
                }

                if ($existing === null) {
                    flash('ok', t('Room “{name}” has been created. Now pick its songs from the master list.', ['name' => $checked['values']['name']]));
                    redirect(url(['p' => 'room_songs', 'room' => $checked['values']['slug']]));
                }

                flash('ok', $archivedNow
                    ? t('Room “{name}” has been archived and closed.', ['name' => $checked['values']['name']])
                    : t('Room “{name}” has been saved.', ['name' => $checked['values']['name']]));
                redirect(url(['p' => 'rooms']));
                // no break

            case 'room_delete':
                require_role($security, 'rooms');
                $target = $rooms->find((int) ($_POST['id'] ?? 0));

                if ($target === null) {
                    flash('error', t('This room was not found.'));
                } elseif ($rooms->delete((int) $target['id'])) {
                    if ((int) $settings->get(RoomRepository::START_ROOM_KEY, '0') === (int) $target['id']) {
                        $settings->delete(RoomRepository::START_ROOM_KEY);
                    }
                    // Its pause switch and revision counter go with it.
                    $guard->forgetRoom((int) $target['id']);
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
                    flash('error', t('“General” always offers the whole repertoire.'));
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

            // ---- Users (admins) --------------------------------------------

            case 'user_save':
                require_role($security, 'users');
                $id       = (int) ($_POST['id'] ?? 0); // 0 = new user
                $selfId   = (int) $security->user()['id'];
                $existing = $id > 0 ? $users->find($id) : null;

                if ($id > 0 && $existing === null) {
                    flash('error', t('This user was not found.'));
                    redirect(url(['p' => 'users']));
                }

                $input = [
                    'username'       => (string) ($_POST['username'] ?? ''),
                    'password'       => (string) ($_POST['password'] ?? ''),
                    'password2'      => (string) ($_POST['password2'] ?? ''),
                    'role_admin'     => (string) ($_POST['role_admin'] ?? ''),
                    'role_moderator' => (string) ($_POST['role_moderator'] ?? ''),
                    'role_editor'    => (string) ($_POST['role_editor'] ?? ''),
                    'active'         => (string) ($_POST['active'] ?? ''),
                ];
                $checked = $users->validate($input, $existing);

                if ($id === $selfId) {
                    // Nobody locks themselves out of a running session.
                    $checked['values']['active'] = 1;
                    // The last active admin keeps the role -- otherwise nobody
                    // could manage users any more. Only oneself can be that
                    // last admin: whoever gets here is an active admin already.
                    if ($checked['values']['role_admin'] !== 1 && $users->activeAdmins($id) === 0) {
                        $checked['errors']['role_admin'] = t('You are the only active admin – make another user admin first.');
                    }
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
                    if ($id === $selfId && isset($checked['values']['password_hash'])) {
                        $security->notePassword($input['password']);
                    }
                    // Own admin role given up: the user list is closed from now
                    // on, so the way leads to the pages the remaining roles open.
                    if ($id === $selfId && $checked['values']['role_admin'] !== 1) {
                        flash('ok', t('You are no longer admin. Your other roles stay; another admin can give the role back.'));
                        redirect(url(['p' => $checked['values']['role_moderator'] === 1 ? 'wishes' : 'songs']));
                    }
                    flash('ok', t('User “{name}” has been saved.', ['name' => $checked['values']['username']]));
                }
                redirect(url(['p' => 'users']));
                // no break

            case 'user_delete':
                require_role($security, 'users');
                $selfId = (int) $security->user()['id'];
                $target = $users->find((int) ($_POST['id'] ?? 0));

                if ($target === null) {
                    flash('error', t('This user was not found.'));
                } elseif ((int) $target['id'] === $selfId) {
                    // Also keeps the last active admin: whoever deletes is one.
                    flash('error', t('You cannot delete yourself.'));
                } elseif ($users->delete((int) $target['id'])) {
                    $settings->forgetUser((int) $target['id']);
                    flash('ok', t('User “{name}” has been deleted.', ['name' => (string) $target['username']]));
                } else {
                    flash('error', t('Deleting was not possible.'));
                }
                redirect(url(['p' => 'users']));
                // no break

            default:
                redirect(url(['p' => 'songs']));
        }
    } catch (Throwable $e) {
        flash('error', t('An error occurred: {detail}', ['detail' => error_detail($e, $config, $security)]));
        redirect(url(['p' => $page, 'slug' => $routeSlug]));
    }
}

// --- Pages (GET) ------------------------------------------------------------
$view = [
    'page'       => $page,
    'room'       => $room,
    'security'   => $security,
    'settings'   => $settings,
    'translator' => $translator,
    'csrf'       => $security->csrfToken(),
    'flash'      => flash_take(),
    'wishCount'  => null,
    'suggestionCount' => null, // badge on the Suggestions tab, editors only
    'live'       => null,  // polling for live updates: ['url' => ..., 'rev' => ...], wish list and suggestions
    'paused'     => false, // wishing closed by the moderator -- notice in the header
    'roomList'   => [],    // rooms for the switcher in the header
    'guestName'  => $nameCookie->current(), // the visitor's name for wishes, account menu
    'askName'    => false, // first visit: ask for the name (dialog in the layout)
    'footer'     => '',    // the operator's own footer line (Administration -> Footer), HTML printed as is; filled below
    'footerLang' => '',    // the language that line is written in, '' when it is the interface language
    'footerPages' => [],  // the admins' pages the footer links (Administration -> Footer), in order
    'editor'     => false, // load CKEditor (assets/vendor/ckeditor5) for a textarea[data-editor] on this page
    'colorsCss'  => '',    // colour overrides the admins set under Colours, '' = stylesheet defaults; filled below
];

try {
    $schema->ensure();
    $view['paused']   = $guard->isPaused();

    // Live updates (app.js): the wish list and the suggestions poll a token
    // that changes with every change of what they show; ?poll=1 answers with
    // that token alone. The suggestions' token includes the room's wish
    // revision, since closing the room hides their form as well.
    $liveToken = match ($page) {
        'wishes'      => (string) $guard->revision(),
        'suggestions' => $settings->get(SuggestionRepository::REVISION_KEY, '0') . '.' . $guard->revision(),
        default       => null,
    };
    if ($liveToken !== null && isset($_GET['poll'])) {
        header('Cache-Control: no-store');
        send_json(['rev' => $liveToken]);
    }
    if ($liveToken !== null) {
        $view['live'] = ['url' => url(['p' => $page, 'poll' => 1]), 'rev' => $liveToken];
    }
    // The room switcher: guests get the listed rooms only.
    $view['roomList'] = $rooms->names(!$security->isLoggedIn());
    // The footer links the pages the admins put there, on every screen, and
    // carries the operator's own line below them.
    $view['footerPages'] = $pageRepo->footerLinks();
    $footerLine          = $pageRepo->footerLine();
    $view['footer']      = $footerLine['html'] ?? '';
    $view['footerLang']  = $footerLine !== null && $footerLine['lang'] !== $translator->code() ? $footerLine['lang'] : '';
    // Visitors without a login are asked for their name once, on the public
    // pages where wishing happens. Staff in the guest view see it as well --
    // that is what the guest view is for.
    $view['askName']  = !$security->isLoggedIn()
        && $nameCookie->shouldAsk()
        && in_array($page, ['songs', 'wishes', 'suggestions', 'rooms'], true);

    // The live logo takes the word mark's place in the header (layout); a
    // deleted one falls back to the word mark by itself.
    $logoId       = (int) $settings->get(Settings::LOGO_ID, '0');
    $view['logo'] = $logoId > 0 ? $uploads->info($logoId) : null;
    // The colours set under Colours, as a :root block over the stylesheet.
    $view['colorsCss'] = Colors::css(Colors::load($settings));

    switch ($page) {
        case 'logo':
            // An uploaded logo as its own resource for <img src="/logo/<id>">.
            // The bytes of an id never change, so the browser may keep them
            // for good. nosniff and a CSP keep an SVG opened directly from
            // doing anything but drawing.
            if ($uploads->info($routeId) === null) {
                not_found();
            }
            // session_start() has queued the headers that keep pages out of
            // the cache (Expires in the past, Pragma: no-cache); browsers
            // honour Pragma over Cache-Control and would fetch the picture
            // again on every page. An image needs none of them, nor a fresh
            // session cookie.
            header_remove('Expires');
            header_remove('Pragma');
            header_remove('Set-Cookie');
            header('Cache-Control: public, max-age=31536000, immutable');
            // A browser that asks anyway (hard reload) gets a 304 without
            // the bytes leaving the database.
            $etag = '"logo-' . $routeId . '"';
            header('ETag: ' . $etag);
            if (str_contains((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''), $etag)) {
                http_response_code(304);
                exit;
            }
            $file = $uploads->load($routeId);
            if ($file === null) {
                not_found();
            }
            header('Content-Type: ' . $file['mime']);
            header('Content-Length: ' . (string) strlen($file['data']));
            header('X-Content-Type-Options: nosniff');
            header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'");
            echo $file['data'];
            exit;

        case 'logos':
            // Admins only: every uploaded logo, one of them live.
            require_role($security, 'users');

            $view['title']    = t('Logos');
            $view['template'] = 'logos';
            $view['logos']    = $uploads->all(Uploads::LOGO);
            $view['activeId'] = $logoId;
            break;

        case 'colors':
            // Admins only: the site's colours, one per area of use. After a
            // failed save the typed values come back with their errors.
            require_role($security, 'users');
            $kept = remembered_input();

            $view['title']    = t('Colours');
            $view['template'] = 'colors';
            $view['values']   = $kept['values'] ?? Colors::load($settings);
            $view['errors']   = $kept['errors'] ?? [];
            break;

        case 'limits':
            // Admins only: the limits on wishing and suggesting.
            require_role($security, 'users');
            $kept = remembered_input();

            $view['title']    = t('Limits');
            $view['template'] = 'limits';
            $view['values']   = $kept['values'] ?? array_map('strval', $limits->all());
            $view['errors']   = $kept['errors'] ?? [];
            break;

        case 'pages':
            // Admins only: every page, with a mark on the ones the footer links.
            require_role($security, 'users');

            $q = trim((string) ($_GET['q'] ?? ''));

            $view['title']     = t('Pages');
            $view['template']  = 'pages';
            $view['q']         = $q;
            $view['rows']      = $pageRepo->all($q);
            $view['languages'] = $translator->available();   // code => native name
            $view['order']     = $pageRepo->languageOrder();    // the fallback order, codes
            break;

        case 'languages':
            // Admins only: the languages of the menu in their fallback order
            // -- drag & drop or arrow buttons, like the wish list.
            require_role($security, 'users');

            $view['title']     = t('Languages');
            $view['template']  = 'languages';
            $view['languages'] = $translator->available();   // code => native name
            $view['order']     = $pageRepo->languageOrder();    // the fallback order, codes
            break;

        case 'footer':
            // Admins only: which pages the footer links, in which order --
            // drag & drop or arrow buttons, like the wish list -- and which
            // pages could be added; below that the operator's own line, one
            // tab per language like a page's form.
            require_role($security, 'users');

            $footerTexts = $pageRepo->footerLines();
            $languages   = $translator->available();

            $view['title']       = t('Footer');
            $view['template']    = 'footer';
            $view['linked']      = $pageRepo->inFooter();
            $view['available']   = $pageRepo->outsideFooter();
            $view['footerTexts'] = $footerTexts;                 // code => cleaned HTML, the languages with a line
            $view['languages']   = $languages;                   // code => native name, the tabs
            // The tab to start on: the interface language's, or the first that has a line.
            $view['activeLang']  = isset($footerTexts[$translator->code()]) ? $translator->code() : (array_key_first($footerTexts) ?? $translator->code());
            $view['editor']      = true;
            break;

        case 'page_edit':
            // Add or edit a page; the body is written in CKEditor.
            require_role($security, 'users');
            $id       = $routeId; // 0 = new page
            $existing = null;

            if ($id > 0) {
                $existing = $pageRepo->find($id);
                if ($existing === null) {
                    flash('error', t('This page was not found.'));
                    redirect(url(['p' => 'pages']));
                }
            }

            $kept = remembered_input();

            // The form holds every language of the menu; the saved ones fill
            // their tabs. An error puts its tab in front, otherwise the
            // interface language's if the page has it, otherwise the first
            // the page has, otherwise the interface language's.
            $versions  = $id > 0 ? $pageRepo->versions($id) : [];
            $errors    = $kept['errors'] ?? [];
            $languages = $translator->available();
            $withError = null;
            foreach (array_keys($languages) as $code) {
                if (isset($errors['title.' . $code]) || isset($errors['body.' . $code])) {
                    $withError = $code;
                    break;
                }
            }
            $filled = array_values(array_intersect(array_keys($languages), array_keys($kept['values']['title'] ?? $versions)));

            $view['title']     = $id === 0 ? t('Add page') : t('Edit page');
            $view['template']  = 'page_edit';
            $view['editor']    = true;
            $view['id']        = $id;
            $view['errors']    = $errors;
            $view['values']    = $kept['values'] ?? [
                'slug'  => (string) ($existing['slug'] ?? ''),
                'title' => array_map(static fn (array $v): string => $v['title'], $versions),
                'body'  => array_map(static fn (array $v): string => $v['body'], $versions),
            ];
            $view['languages'] = $languages;                    // code => native name, the tabs
            $view['saved']     = array_keys($versions);         // codes the page is saved in (tab marks)
            $view['activeLang'] = $withError
                ?? (isset($versions[$translator->code()]) ? $translator->code() : ($filled[0] ?? $translator->code()));
            break;

        case 'page':
            // A page for everyone: /pages/<slug> -- in the footer or not.
            $found = $pageRepo->findBySlug($routeSlug);
            if ($found === null) {
                not_found();
            }

            // findBySlug() answers in the interface language, or in the first
            // language of the fallback order the page has.
            $view['title']    = (string) $found['title'];
            $view['template'] = 'page';
            $view['content']  = $found;
            $view['canEdit']  = $security->can('users');
            break;

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
            $key  = $routeId; // 0 = new song
            $song = null;
            // Adopting a suggestion: a new song whose artist and title come
            // from the suggestion; the editor adds length and genre.
            $adopt = null;

            if ($key > 0) {
                $song = $songs->find($key);
                if ($song === null) {
                    flash('error', t('This song was not found.'));
                    redirect(url(['p' => 'songs']));
                }
            } elseif ($routeSuggestion > 0) {
                $adopt = $suggestions->find($routeSuggestion);
                if ($adopt === null) {
                    flash('error', t('This suggestion was not found.'));
                    redirect(url(['p' => 'suggestions']));
                }
            }

            // After a failed validation the input and errors are in the
            // session; otherwise the values come from the database.
            $kept = remembered_input();

            $view['title']    = match (true) {
                $adopt !== null => t('Adopt suggestion'),
                $key === 0      => t('Add song'),
                default         => t('Edit song'),
            };
            $view['template'] = 'song';
            $view['repo']     = $songs;
            $view['key']      = $key;
            $view['adopt']    = $adopt;
            // The room the suggestion was made in -- the song will join it.
            $view['adoptRoom'] = $adopt !== null && (int) $adopt['room_id'] > 0 ? $rooms->find((int) $adopt['room_id']) : null;
            $view['errors']   = $kept['errors'] ?? [];
            $view['back']     = safe_target($_GET['back'] ?? null) ?? url(['p' => $adopt !== null ? 'suggestions' : 'songs']);
            $view['values']   = $kept['values'] ?? [
                'artist' => (string) ($adopt['artist'] ?? $song['artist'] ?? ''),
                'title'  => (string) ($adopt['title'] ?? $song['title'] ?? ''),
                'length' => Format::lengthInput($song['length_sec'] ?? null),
                'genre'  => (string) ($song['genre'] ?? ''),
            ];
            break;

        case 'suggestions':
            // Everyone sees the open suggestions and may search them; editors
            // also adopt and delete. The badge keeps the full count.
            $canEdit = $security->can('suggestions');
            $kept    = remembered_input();
            $q       = trim((string) ($_GET['q'] ?? ''));
            $perPage = $limits->get('per_page');
            $pageNo  = max(1, (int) ($_GET['page'] ?? 1));

            $result = $suggestions->search($q, $pageNo, $perPage);

            $view['title']     = t('Song suggestions');
            $view['template']  = 'suggestions';
            $view['canEdit']   = $canEdit;
            $view['q']         = $q;
            $view['rows']      = $result['rows'];
            $view['found']     = $result['total'];
            $view['pageNo']    = $pageNo;
            $view['pages']     = max(1, (int) ceil($result['total'] / $perPage));
            // Room names for the tags on the rows, by id -- archived rooms too.
            // Guests get the listed rooms only, plus the room they are in; a
            // row whose room is missing here shows no room tag.
            $view['roomNames'] = $rooms->namesById(!$security->isLoggedIn());
            if ($roomId > 0) {
                $view['roomNames'][$roomId] = (string) $room['name'];
            }
            // No form while wishing is paused in this room.
            $view['formToken'] = $view['paused'] ? '' : $guard->formToken();
            $view['errors']    = $kept['errors'] ?? [];
            $view['values']    = $kept['values'] ?? ['artist' => '', 'title' => ''];
            $view['suggestionCount'] = $q === '' ? $result['total'] : $suggestions->count();
            break;

        case 'name':
            // The visitor's name for the wish list -- the same form the
            // first visit shows in a dialog, as a page for later changes.
            $view['title']    = t('Your name');
            $view['template'] = 'name';
            $view['back']     = safe_target($_GET['back'] ?? null) ?? url(['p' => 'songs']);
            break;

        case 'settings':
            // Every signed-in user, for their own account: /users/<own id>/settings
            // -- not an admin page, so not below /admin.
            // Any other id -- and the bare /settings -- leads to the own page.
            require_login($security);
            $selfId = (int) $security->user()['id'];
            if ($routeId !== $selfId) {
                redirect(url(['p' => 'settings', 'id' => $selfId]));
            }

            $view['title']    = t('User settings');
            $view['template'] = 'settings';
            $view['selfId']   = $selfId;
            $view['kinds']    = deletable_kinds($security);
            $view['account']  = $security->user();
            $view['errors']   = remembered_input()['errors'] ?? [];
            break;

        case 'users':
            require_role($security, 'users');

            $q = trim((string) ($_GET['q'] ?? ''));

            $view['title']    = t('Users');
            $view['template'] = 'users';
            $view['q']        = $q;
            $view['rows']     = $users->all($q);
            $view['selfId']   = (int) $security->user()['id'];
            break;

        case 'user':
            require_role($security, 'users');
            $id   = $routeId; // 0 = new user
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
            // The only active admin (oneself, necessarily) keeps the role; the
            // form says so instead of offering a box that user_save would refuse.
            $view['onlyAdmin'] = $user !== null
                && (int) $user['role_admin'] === 1
                && (int) $user['active'] === 1
                && $users->activeAdmins($id) === 0;
            $view['errors']   = $kept['errors'] ?? [];
            $view['values']   = $kept['values'] ?? [
                'username'       => (string) ($user['username'] ?? ''),
                'role_admin'     => (string) ($user['role_admin'] ?? '0'),
                'role_moderator' => (string) ($user['role_moderator'] ?? '0'),
                'role_editor'    => (string) ($user['role_editor'] ?? '0'),
                'active'         => (string) ($user['active'] ?? '1'),
            ];
            break;

        case 'rooms':
            // Everyone sees the active rooms and may switch -- guests only
            // the listed ones, signed-in users every active room; editors
            // also manage them and may list archived rooms.
            $canEdit = $security->can('rooms');
            $perPage = $limits->get('per_page');
            $q       = trim((string) ($_GET['q'] ?? ''));
            // Editors see every room by default (archived ones tagged), guests
            // only the active ones -- whatever the parameter says.
            $filter  = (string) ($_GET['filter'] ?? 'all');
            $filter  = $canEdit && in_array($filter, RoomRepository::FILTERS, true) ? $filter : 'active';
            $pageNo  = max(1, (int) ($_GET['page'] ?? 1));

            $roomResult = $rooms->search($q, $filter, $pageNo, $perPage, !$security->isLoggedIn());

            $view['title']        = t('Rooms');
            $view['template']     = 'rooms';
            $view['rows']         = $roomResult['rows'];
            $view['total']        = $roomResult['total'];
            $view['q']            = $q;
            $view['filter']       = $filter;
            $view['pageNo']       = $pageNo;
            $view['pages']        = max(1, (int) ceil($roomResult['total'] / $perPage));
            $view['canEdit']      = $canEdit;
            $view['startRoomId']  = (int) $settings->get(RoomRepository::START_ROOM_KEY, '0');
            // The admins' switch closes wishing in every room at once and later
            // hands every room its previous state back.
            $view['isAdmin']      = $security->isAdmin();
            $view['pausedAll']    = $view['isAdmin'] && $guard->isPausedEverywhere();
            // Moderators close and open rooms from the list: which are closed?
            $view['canPause']     = $security->can('wishes');
            $view['pausedRooms']  = [];
            if ($view['canPause']) {
                foreach ([RoomRepository::DEFAULT_ID, ...array_map('intval', array_column($roomResult['rows'], 'id'))] as $id) {
                    $view['pausedRooms'][$id] = $guard->pausedIn($id);
                }
            }
            $view['masterSongs']  = $songs->count();
            $view['masterWishes'] = (new WishRepository($db))->count();
            break;

        case 'room':
            require_role($security, 'rooms');
            $id   = $routeId; // 0 = new room
            $edit = null;
            // /rooms/main/edit: rename the main room -- only its name, kept in the settings.
            $main = $routeMain;

            if ($main) {
                $kept = remembered_input();
                $view['title']    = t('Rename “General”');
                $view['template'] = 'room';
                $view['id']       = 0;
                $view['main']     = true;
                $view['startRoomId'] = (int) $settings->get(RoomRepository::START_ROOM_KEY, '0');
                $view['roomClosed']  = $guard->pausedIn(RoomRepository::DEFAULT_ID);
                $view['roomActive']  = true;
                $view['errors']   = $kept['errors'] ?? [];
                $view['values']   = $kept['values'] ?? ['name' => (string) $settings->get(RoomRepository::MAIN_NAME_KEY, '')];
                break;
            }

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
            $view['main']     = false;
            // The switches beside the title (start room, close/open) -- for an existing room.
            $view['startRoomId'] = (int) $settings->get(RoomRepository::START_ROOM_KEY, '0');
            $view['roomClosed']  = $id > 0 && $guard->pausedIn($id);
            $view['roomActive']  = (int) ($edit['active'] ?? 1) === 1;
            $view['errors']   = $kept['errors'] ?? [];
            $view['values']   = $kept['values'] ?? [
                'slug'   => (string) ($edit['slug'] ?? ''),
                'name'   => (string) ($edit['name'] ?? ''),
                'active' => (string) ($edit['active'] ?? '1'),
                'listed' => (string) ($edit['listed'] ?? '0'), // a new room starts unlisted
            ];
            break;

        case 'room_songs':
            require_role($security, 'rooms');
            if ($roomId === RoomRepository::DEFAULT_ID) {
                redirect(url(['p' => 'songs']));
            }

            $perPage = $limits->get('per_page');
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
            $perPage = $limits->get('per_page');
            $sort    = (string) ($_GET['sort'] ?? 'artist');
            $dir     = (string) ($_GET['dir'] ?? 'asc');
            $q       = trim((string) ($_GET['q'] ?? ''));
            $pageNo  = max(1, (int) ($_GET['page'] ?? 1));

            $result = $songs->search($q, $sort, $dir, $pageNo, $perPage, $roomId);

            $view['title']     = t('Repertoire');
            $view['template']  = 'home';
            $view['startRoomId'] = (int) $settings->get(RoomRepository::START_ROOM_KEY, '0');
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
    if ($security->can('suggestions') && $view['suggestionCount'] === null) {
        $view['suggestionCount'] = $suggestions->count();
    }
} catch (Throwable $e) {
    $view['title']    = t('Error');
    $view['template'] = 'error';
    $view['message']  = error_detail($e, $config, $security);
}

extract($view, EXTR_SKIP);
require __DIR__ . '/templates/layout.php';
