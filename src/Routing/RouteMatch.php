<?php

declare(strict_types=1);

namespace Songwunsch\Routing;

/**
 * The route a path belongs to, together with the values its placeholders
 * picked up. The kernel puts both onto the request as attributes.
 */
final class RouteMatch
{
    /** @param array<string,string> $parameters */
    public function __construct(
        public readonly Route $route,
        public readonly array $parameters,
    ) {
    }

    /**
     * The values a controller reads off the request: the placeholders plus
     * the route's own name and page key, which several routes share.
     *
     * @return array<string,string>
     */
    public function attributes(): array
    {
        return $this->parameters + [
            '_route' => $this->route->name(),
            '_page'  => $this->route->pageKey(),
        ];
    }
}
