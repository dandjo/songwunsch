<?php

declare(strict_types=1);

/**
 * Check the route table against itself:
 *
 *   php tools/check-routes.php            # report and exit code
 *   php tools/check-routes.php --list     # every address the table holds
 *
 * For every route, the address is generated and then matched again. It must
 * come back as the same route with the same values -- a round trip. That is
 * the one property a hand-written router can silently lose: the matcher and
 * the generator drifting apart, so that a link points at an address no
 * pattern claims, or claims the wrong one. Cheap enough to run before every
 * commit, and it needs neither a database nor a web server.
 *
 * Exit code 0 when every route round-trips.
 */

use Songwunsch\Http\Request;
use Songwunsch\RoomContext;
use Songwunsch\RoomRepository;
use Songwunsch\Routing\Route;
use Songwunsch\Routing\RouteCollection;
use Songwunsch\Routing\RouteMatcher;
use Songwunsch\Routing\UrlGenerator;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/src/bootstrap.php';

$list = in_array('--list', $argv, true);

$routes  = new RouteCollection(require dirname(__DIR__) . '/config/routes.php');
$matcher = new RouteMatcher($routes);
$context = new RoomContext();
// The main room, so that a room-scoped address only carries a machine name
// when the check passes one.
$context->set(RoomRepository::defaultRoom());
$urls = new UrlGenerator($routes, $context, base_path());

/**
 * A value that satisfies a placeholder's requirement. The check does not
 * try to be clever: these are the four shapes the table uses.
 */
$sample = static function (string $name, Route $route): string {
    $requirement = $route->compile('{' . $name . '}')[0];
    foreach (['42', '7', 'new', 'svg', 'de', 'faq'] as $candidate) {
        if (preg_match($requirement, $candidate) === 1) {
            return $candidate;
        }
    }

    return 'x';
};

$failures = 0;
$checked  = 0;

foreach ($routes->all() as $name => $route) {
    // Both forms of a room's page, where both exist.
    $rooms = $route->isRoomScoped()
        ? ($route->unprefixedPath() !== null ? ['', 'kellerbar'] : ['kellerbar'])
        : [null];

    foreach ($rooms as $room) {
        $params = $room === null ? [] : ['room' => $room];
        $path   = $room === null ? $route->path() : (string) ($room === '' ? $route->unprefixedPath() : $route->prefixedPath());
        foreach ($route->placeholders($path) as $placeholder) {
            if ($placeholder !== 'room') {
                $params[$placeholder] = $sample($placeholder, $route);
            }
        }

        $target = $urls->generate($name, $params);
        $method = in_array('POST', $route->methods(), true) ? 'POST' : 'GET';

        if ($list) {
            printf("%-6s %-20s %s\n", $method, $name, $target);
        }

        // Back through the matcher, the way a browser's request arrives.
        $request = new Request($method, rtrim(substr($target, strlen(base_path())), '/'), [], [], [], [], []);
        try {
            $match = $matcher->match($request);
        } catch (Throwable $e) {
            printf("FAIL %-20s %s -- no route matches this address\n", $name, $target);
            $failures++;
            continue;
        }

        $checked++;

        if ($match->route->name() !== $name) {
            printf("FAIL %-20s %s -- matched as '%s'\n", $name, $target, $match->route->name());
            $failures++;
            continue;
        }
        foreach ($params as $key => $value) {
            $got = (string) ($match->parameters[$key] ?? '');
            if ($got !== (string) $value) {
                printf("FAIL %-20s %s -- {%s} came back as '%s', not '%s'\n", $name, $target, $key, $got, $value);
                $failures++;
            }
        }
    }
}

// Every route the access map names must exist: a typo there would silently
// leave an address public.
$access = require dirname(__DIR__) . '/config/access.php';
foreach (array_keys($access) as $name) {
    if (!$routes->has((string) $name)) {
        printf("FAIL access.php names '%s', which is no route\n", $name);
        $failures++;
    }
}

// And the other direction, which is the one that bites: every route that
// writes, and everything under /admin, has to be classified -- given a role
// area, or marked AccessListener::OPEN. The runtime refuses an unclassified
// one of those (AccessListener), so this does not decide whether an address
// is reachable; it says so here, at the moment the route is added, instead of
// leaving someone to find out from a 303 to the login.
foreach ($routes->all() as $route) {
    if (array_key_exists($route->name(), $access)) {
        continue;
    }
    $writes = in_array('POST', $route->methods(), true);
    $admin  = str_starts_with($route->path(), '/admin');
    if ($writes || $admin) {
        printf(
            "FAIL %-20s %s is %s but is not in access.php -- give it a role area, or AccessListener::OPEN\n",
            $route->name(),
            $route->path(),
            $writes ? 'a POST route' : 'under /admin',
        );
        $failures++;
    }
}

printf("\n%d route(s), %d address(es) checked, %d failure(s).\n", count($routes->all()), $checked, $failures);

exit($failures === 0 ? 0 : 1);
