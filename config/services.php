<?php

declare(strict_types=1);

/**
 * What the application is built from: a service id and the factory that
 * makes it.
 *
 * There is no autowiring. At this size an explicit list is shorter to read
 * than the reflection that would work it out, it needs no cache directory,
 * and it puts every dependency of every class on one screen. Everything is
 * lazy: a service nobody asks for is never made, which is what lets a
 * live-update poll answer without a translation catalogue, a repository or a
 * page shell.
 *
 * The file receives $config (config.php) and returns the filled container.
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
use Songwunsch\Controller\Support;
use Songwunsch\Controller\UserController;
use Songwunsch\Controller\WishController;
use Songwunsch\Database;
use Songwunsch\DependencyInjection\Container;
use Songwunsch\ErrorPresenter;
use Songwunsch\EventListener\AccessListener;
use Songwunsch\EventListener\CsrfListener;
use Songwunsch\EventListener\LanguageListener;
use Songwunsch\EventListener\LiveUpdateListener;
use Songwunsch\EventListener\RoomListener;
use Songwunsch\FlashBag;
use Songwunsch\FormMemory;
use Songwunsch\GuestName;
use Songwunsch\Http\Request;
use Songwunsch\Kernel;
use Songwunsch\Limits;
use Songwunsch\LiveTokens;
use Songwunsch\PageRepository;
use Songwunsch\RoomContext;
use Songwunsch\RoomMemory;
use Songwunsch\RoomRepository;
use Songwunsch\RoomServices;
use Songwunsch\Routing\RouteCollection;
use Songwunsch\Routing\RouteMatcher;
use Songwunsch\Routing\UrlGenerator;
use Songwunsch\Schema;
use Songwunsch\Security;
use Songwunsch\Settings;
use Songwunsch\SongRepository;
use Songwunsch\SuggestionRepository;
use Songwunsch\Template\Renderer;
use Songwunsch\Template\ShellContext;
use Songwunsch\Translator;
use Songwunsch\Ui;
use Songwunsch\Uploads;
use Songwunsch\UserRepository;
use Songwunsch\WishGuard;
use Songwunsch\WishRepository;

/** @var array<string,mixed> $config  the return value of config.php */

$root = dirname(__DIR__);

$container = new Container([
    'db'          => $config['db'],
    'auth'        => $config['auth'] ?? [],
    'trust_proxy' => (bool) ($config['trust_proxy'] ?? false),
    'show_errors' => ($config['show_errors'] ?? false) === true,
    'lang_dir'    => $root . '/lang',
    'templates'   => $root . '/templates',
    // The session and the cookies are scoped to the base path, so several
    // applications on the same domain do not share a session.
    'cookie_path' => base_path() . '/',
]);

// ---- Routing --------------------------------------------------------------

$container->set(RouteCollection::class, static fn (): RouteCollection => new RouteCollection(
    require __DIR__ . '/routes.php',
));
$container->set(RouteMatcher::class, static fn (Container $c): RouteMatcher => new RouteMatcher(
    $c->get(RouteCollection::class),
));
$container->set(UrlGenerator::class, static fn (Container $c): UrlGenerator => new UrlGenerator(
    $c->get(RouteCollection::class),
    $c->get(RoomContext::class),
    base_path(),
));

// ---- The database and the schema -----------------------------------------
// The connection is opened on first use, the tables are checked once per
// request; a request that needs neither does neither.

$container->set(Database::class, static fn (Container $c): Database => new Database($c->param('db')));
$container->set(Schema::class, static fn (Container $c): Schema => new Schema($c->get(Database::class)));
$container->set(Settings::class, static fn (Container $c): Settings => new Settings($c->get(Database::class)));

// ---- Repositories ---------------------------------------------------------

$container->set(SongRepository::class, static fn (Container $c): SongRepository => new SongRepository($c->get(Database::class)));
$container->set(UserRepository::class, static fn (Container $c): UserRepository => new UserRepository($c->get(Database::class)));
$container->set(RoomRepository::class, static fn (Container $c): RoomRepository => new RoomRepository($c->get(Database::class)));
$container->set(Uploads::class, static fn (Container $c): Uploads => new Uploads($c->get(Database::class)));
$container->set(PageRepository::class, static fn (Container $c): PageRepository => new PageRepository(
    $c->get(Database::class),
    $c->get(Settings::class),
    $c->get(Translator::class),
));

// Whole-number settings the admins keep: the limits on wishing and
// suggesting, and the interface's message duration and polling pace.
$container->set(Limits::class, static fn (Container $c): Limits => new Limits($c->get(Settings::class)));
$container->set(Ui::class, static fn (Container $c): Ui => new Ui($c->get(Settings::class)));

// ---- The room of the request ----------------------------------------------
// RoomContext is filled by RoomListener; everything room-scoped is built
// from it afterwards, which is why none of these may be asked for earlier.

$container->set(RoomContext::class, static fn (): RoomContext => new RoomContext());
$container->set(RoomServices::class, static fn (Container $c): RoomServices => new RoomServices(
    $c->get(Database::class),
    $c->get(Settings::class),
    $c->get(Limits::class),
    (bool) $c->param('trust_proxy'),
));
$container->set(WishRepository::class, static fn (Container $c): WishRepository => $c->get(RoomServices::class)
    ->wishes($c->get(RoomContext::class)->id()));
$container->set(SuggestionRepository::class, static fn (Container $c): SuggestionRepository => $c->get(RoomServices::class)
    ->suggestions($c->get(RoomContext::class)->id()));
$container->set(WishGuard::class, static fn (Container $c): WishGuard => $c->get(RoomServices::class)
    ->guard($c->get(RoomContext::class)->id()));

// ---- Session, language, cookies -------------------------------------------

$container->set(Security::class, static fn (Container $c): Security => new Security(
    $c->get(UserRepository::class),
    (string) $c->param('cookie_path'),
));
$container->set(GuestName::class, static fn (Container $c): GuestName => new GuestName(
    (string) $c->param('cookie_path'),
    Security::isHttps(),
));
$container->set(RoomMemory::class, static fn (Container $c): RoomMemory => new RoomMemory(
    (string) $c->param('cookie_path'),
    Security::isHttps(),
));

// Which language this request speaks is decided here, on first use: an
// explicit ?lang=, the session, the cookie, the browser's Accept-Language,
// English. A request that shows no text never gets this far.
$container->set(Translator::class, static function (Container $c): Translator {
    $request    = $c->get(Request::class);
    $translator = new Translator((string) $c->param('lang_dir'));
    $translator->load($translator->detect(
        $request->hasQuery('lang') ? $request->query('lang') : null,
        isset($_SESSION['lang']) && is_scalar($_SESSION['lang']) ? (string) $_SESSION['lang'] : null,
        $request->cookie(Translator::cookieName()),
        $request->server('HTTP_ACCEPT_LANGUAGE'),
    ));

    return $translator;
});

// ---- Messages, half-filled forms, failures --------------------------------

$container->set(FlashBag::class, static fn (): FlashBag => new FlashBag());
$container->set(FormMemory::class, static fn (): FormMemory => new FormMemory());
$container->set(ErrorPresenter::class, static fn (Container $c): ErrorPresenter => new ErrorPresenter(
    $c->get(Security::class),
    (bool) $c->param('show_errors'),
));

// ---- Live updates ---------------------------------------------------------

$container->set(LiveTokens::class, static fn (Container $c): LiveTokens => new LiveTokens(
    $c->get(Settings::class),
    $c->get(WishGuard::class),
    $c->get(Ui::class),
));

// ---- The view -------------------------------------------------------------

$container->set(ShellContext::class, static fn (Container $c): ShellContext => new ShellContext(
    $c->get(Request::class),
    $c->get(RouteCollection::class),
    $c->get(UrlGenerator::class),
    $c->get(Security::class),
    $c->get(Settings::class),
    $c->get(Translator::class),
    $c->get(RoomContext::class),
    $c->get(RoomRepository::class),
    $c->get(RoomMemory::class),
    $c->get(SongRepository::class),
    $c->get(WishRepository::class),
    $c->get(SuggestionRepository::class),
    $c->get(PageRepository::class),
    $c->get(Uploads::class),
    $c->get(Ui::class),
    $c->get(LiveTokens::class),
    $c->get(GuestName::class),
    $c->get(FlashBag::class),
));
$container->set(Renderer::class, static fn (Container $c): Renderer => new Renderer(
    (string) $c->param('templates'),
    $c->get(ShellContext::class),
));

// ---- The listeners, in the order the kernel runs them ---------------------

$container->set(RoomListener::class, static fn (Container $c): RoomListener => new RoomListener(
    $c->get(RouteCollection::class),
    $c->get(RoomContext::class),
    $c->get(RoomRepository::class),
    $c->get(RoomMemory::class),
    $c->get(Schema::class),
    $c->get(Security::class),
    $c->get(Settings::class),
    $c->get(UrlGenerator::class),
    $c->get(FlashBag::class),
));
$container->set(LiveUpdateListener::class, static fn (Container $c): LiveUpdateListener => new LiveUpdateListener(
    $c->get(RouteCollection::class),
    $c->get(Schema::class),
    $c->get(LiveTokens::class),
));
$container->set(LanguageListener::class, static fn (Container $c): LanguageListener => new LanguageListener(
    $c->get(Translator::class),
    $c->get(UrlGenerator::class),
    (string) $c->param('cookie_path'),
));
$container->set(CsrfListener::class, static fn (Container $c): CsrfListener => new CsrfListener(
    $c->get(Security::class),
    $c->get(UrlGenerator::class),
    $c->get(FlashBag::class),
));
$container->set(AccessListener::class, static fn (Container $c): AccessListener => new AccessListener(
    $c->get(Security::class),
    require __DIR__ . '/access.php',
));

// ---- Controllers ----------------------------------------------------------

$container->set(Support::class, static fn (Container $c): Support => new Support(
    $c->get(Request::class),
    $c->get(UrlGenerator::class),
    $c->get(FlashBag::class),
    $c->get(FormMemory::class),
    $c->get(Security::class),
));

$container->set(SongController::class, static fn (Container $c): SongController => new SongController(
    $c->get(Support::class),
    $c->get(SongRepository::class),
    $c->get(SuggestionRepository::class),
    $c->get(RoomRepository::class),
    $c->get(WishRepository::class),
    $c->get(Settings::class),
    $c->get(Limits::class),
    $c->get(WishGuard::class),
    $c->get(RoomContext::class),
    $c->get(RoomServices::class),
    $c->get(ErrorPresenter::class),
));
$container->set(WishController::class, static fn (Container $c): WishController => new WishController(
    $c->get(Support::class),
    $c->get(WishRepository::class),
    $c->get(SongRepository::class),
    $c->get(WishGuard::class),
    $c->get(Limits::class),
    $c->get(GuestName::class),
    $c->get(RoomContext::class),
));
$container->set(SuggestionController::class, static fn (Container $c): SuggestionController => new SuggestionController(
    $c->get(Support::class),
    $c->get(SuggestionRepository::class),
    $c->get(SongRepository::class),
    $c->get(WishGuard::class),
    $c->get(Limits::class),
    $c->get(Settings::class),
    $c->get(GuestName::class),
));
$container->set(RoomController::class, static fn (Container $c): RoomController => new RoomController(
    $c->get(Support::class),
    $c->get(RoomRepository::class),
    $c->get(SongRepository::class),
    $c->get(Settings::class),
    $c->get(Limits::class),
    $c->get(WishGuard::class),
    $c->get(RoomContext::class),
    $c->get(RoomMemory::class),
    $c->get(RoomServices::class),
));
$container->set(AuthController::class, static fn (Container $c): AuthController => new AuthController(
    $c->get(Support::class),
    $c->get(UserRepository::class),
    (array) $c->param('auth'),
));
$container->set(GuestController::class, static fn (Container $c): GuestController => new GuestController(
    $c->get(Support::class),
    $c->get(GuestName::class),
));
$container->set(UserController::class, static fn (Container $c): UserController => new UserController(
    $c->get(Support::class),
    $c->get(UserRepository::class),
    $c->get(Settings::class),
    $c->get(Limits::class),
));
$container->set(AdminController::class, static fn (Container $c): AdminController => new AdminController(
    $c->get(Support::class),
    $c->get(Settings::class),
    $c->get(Uploads::class),
    $c->get(Ui::class),
    $c->get(Limits::class),
));
$container->set(PageController::class, static fn (Container $c): PageController => new PageController(
    $c->get(Support::class),
    $c->get(PageRepository::class),
    $c->get(Translator::class),
    $c->get(Limits::class),
));
$container->set(FooterController::class, static fn (Container $c): FooterController => new FooterController(
    $c->get(Support::class),
    $c->get(PageRepository::class),
    $c->get(Translator::class),
    $c->get(Limits::class),
));
$container->set(LanguageController::class, static fn (Container $c): LanguageController => new LanguageController(
    $c->get(Support::class),
    $c->get(PageRepository::class),
    $c->get(Translator::class),
));

// ---- The kernel -----------------------------------------------------------

$container->set(Kernel::class, static fn (Container $c): Kernel => new Kernel($c, [
    RoomListener::class,
    LiveUpdateListener::class,
    LanguageListener::class,
    CsrfListener::class,
    AccessListener::class,
]));

return $container;
