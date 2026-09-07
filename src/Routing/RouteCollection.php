<?php

declare(strict_types=1);

namespace Songwunsch\Routing;

use InvalidArgumentException;

/**
 * Every route of the application, in the order config/routes.php declares
 * them. The order matters for matching: a literal path is declared before a
 * pattern that could swallow it (/rooms/new before /rooms/<slug>).
 */
final class RouteCollection
{
    /** @var array<string,Route> */
    private array $routes = [];

    /** @param list<Route> $routes */
    public function __construct(array $routes = [])
    {
        foreach ($routes as $route) {
            $this->add($route);
        }
    }

    public function add(Route $route): void
    {
        if (isset($this->routes[$route->name()])) {
            // A duplicate name would make the generator answer for one route
            // and the matcher for another -- exactly the split this class
            // exists to prevent.
            throw new InvalidArgumentException('Duplicate route name: ' . $route->name());
        }

        $this->routes[$route->name()] = $route;
    }

    public function get(string $name): Route
    {
        if (!isset($this->routes[$name])) {
            throw new InvalidArgumentException('Unknown route: ' . $name);
        }

        return $this->routes[$name];
    }

    public function has(string $name): bool
    {
        return isset($this->routes[$name]);
    }

    /** @return array<string,Route> */
    public function all(): array
    {
        return $this->routes;
    }
}
