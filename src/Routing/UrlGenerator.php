<?php

declare(strict_types=1);

namespace Songwunsch\Routing;

use InvalidArgumentException;
use Songwunsch\RoomContext;

/**
 * The address of a route, by name -- the counterpart of RouteMatcher, out of
 * the same table. Nothing in the application writes a path by hand; this is
 * the only place that knows what a route's address looks like, which is why
 * a path can be changed in config/routes.php alone.
 *
 * Values that are placeholders of the path go into the path, everything else
 * into the query string. A room-scoped route lands in the room the visitor
 * is in unless 'room' says otherwise ('' for the main room).
 */
final class UrlGenerator
{
    public function __construct(
        private readonly RouteCollection $routes,
        private readonly RoomContext $rooms,
        private readonly string $basePath,
        private readonly bool $https = false,
    ) {
    }

    /**
     * @param array<string,mixed> $parameters placeholders plus query values;
     *                                        null and '' are dropped from the query
     */
    public function generate(string $name, array $parameters = []): string
    {
        $route = $this->routes->get($name);

        $path = $route->isRoomScoped()
            ? $this->roomPath($route, $parameters)
            : $route->path();

        // Fill the placeholders; whatever is left over is the query string.
        foreach ($route->placeholders($path) as $placeholder) {
            $value = $parameters[$placeholder] ?? $route->defaultValues()[$placeholder] ?? '';
            unset($parameters[$placeholder]);
            if (!is_scalar($value)) {
                throw new InvalidArgumentException('Route ' . $name . ': {' . $placeholder . '} needs a scalar value.');
            }
            $path = str_replace('{' . $placeholder . '}', rawurlencode((string) $value), $path);
        }

        $target = $this->basePath . $path;
        // The start page is the base path itself, and that is a '/', not the
        // empty string -- a form action must be an address.
        if ($target === '') {
            $target = '/';
        }

        $query = array_filter($parameters, static fn (mixed $v): bool => $v !== null && $v !== '');

        return $query === [] ? $target : $target . '?' . http_build_query($query);
    }

    /**
     * The room-scoped form of a path: /rooms/<slug> in front of it for a
     * real room, the main room's own address otherwise. 'room' overrides the
     * room of the request -- a slug for another room, '' for the main one --
     * and is consumed here either way.
     *
     * @param array<string,mixed> $parameters
     */
    private function roomPath(Route $route, array &$parameters): string
    {
        $slug = array_key_exists('room', $parameters)
            ? (string) $parameters['room']
            : $this->rooms->slug();
        unset($parameters['room']);

        if ($slug === '') {
            // No slug: the main room. A route that exists only inside a real
            // room and names no address for the main room falls back to its
            // bare path, which is what the old url() did; the pages that can
            // reach such a route always carry a room.
            return $route->unprefixedPath() ?? $route->path();
        }

        $parameters['room'] = $slug;

        return (string) $route->prefixedPath();
    }

    /**
     * An address of this installation with scheme and host, as a QR code or
     * a printout needs it: the request's host, https when the request came
     * in over https (also behind a proxy where one is trusted, see
     * Security::isHttps()).
     */
    public function absolute(string $target): string
    {
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');

        return ($this->https ? 'https' : 'http') . '://' . $host . $target;
    }

    /**
     * Check a return address: only this application's own addresses below
     * the base path are accepted, otherwise null. '//host' and '/\host'
     * would be read as protocol-relative by browsers and are rejected too,
     * so a 'back' parameter can never send a visitor off the site.
     */
    public function safeTarget(?string $candidate): ?string
    {
        $candidate = (string) $candidate;
        $base      = $this->basePath . '/';

        if ($candidate !== ''
            && str_starts_with($candidate, $base)
            && !str_starts_with($candidate, $base . '/')
            && !str_starts_with($candidate, $base . '\\')
            && !str_contains($candidate, "\n")
            && !str_contains($candidate, "\r")) {
            return $candidate;
        }

        return null;
    }
}
