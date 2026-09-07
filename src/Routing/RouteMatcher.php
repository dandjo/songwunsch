<?php

declare(strict_types=1);

namespace Songwunsch\Routing;

use Songwunsch\Http\Exception\NotFoundException;
use Songwunsch\Http\Request;

/**
 * Which route does this path belong to?
 *
 * The match runs in two passes, and that order is the whole of the room
 * logic in the matcher:
 *
 *  1. every route at its own address -- the static ones, the ones with an id
 *     or a machine name in the path, and the main room's form of a room
 *     page, which is the bare path (or /rooms/main/qr for the QR code);
 *  2. the room-scoped routes with /rooms/<slug> in front of them.
 *
 * So /rooms/new is the form for a new room and not a room called "new", and
 * /rooms/main/qr is the main room's QR code and not a room called "main" --
 * both names are reserved (RoomRepository::RESERVED_SLUGS), which is what
 * makes the order safe rather than merely lucky.
 *
 * The matcher does nothing but match: it asks no database, writes no cookie
 * and redirects nowhere. Which room a request is really in, whether that
 * room is archived and where a visitor without a room should land is the
 * RoomListener's business, downstream of here. That used to be tangled into
 * the pattern chain itself.
 */
final class RouteMatcher
{
    public function __construct(private readonly RouteCollection $routes)
    {
    }

    public function match(Request $request): RouteMatch
    {
        $path   = $request->path();
        $method = $request->method();

        foreach ([false, true] as $withRoomPrefix) {
            foreach ($this->routes->all() as $route) {
                $candidate = $withRoomPrefix ? $route->prefixedPath() : $route->unprefixedPath();
                if ($candidate === null) {
                    continue;
                }
                $match = $this->tryPath($route, $candidate, $path, $method);
                if ($match !== null) {
                    return $match;
                }
            }
        }

        throw new NotFoundException('No route for ' . ($path === '' ? '/' : $path));
    }

    private function tryPath(Route $route, string $candidate, string $path, string $method): ?RouteMatch
    {
        [$pattern, $names] = $route->compile($candidate);

        if (preg_match($pattern, $path, $found) !== 1) {
            return null;
        }
        // The path is this route's, but the method is not one it answers:
        // treated as unknown, like any other address the table does not
        // hold. The application posts to its own addresses only, so there is
        // nothing here to tell a stranger from a typo, and a 405 would only
        // confirm which addresses exist.
        if (!in_array($method, $route->methods(), true)) {
            return null;
        }

        $parameters = $route->defaultValues();
        foreach ($names as $i => $name) {
            $value = (string) ($found[$i + 1] ?? '');
            // A placeholder that matched nothing keeps its default; a value
            // that is there wins over it.
            if ($value !== '' || !array_key_exists($name, $parameters)) {
                $parameters[$name] = $value;
            }
        }

        return new RouteMatch($route, $parameters);
    }
}
