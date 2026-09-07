<?php

declare(strict_types=1);

namespace Songwunsch\Http;

/**
 * The incoming request, read once from the superglobals and then read-only.
 *
 * Everything the application knows about the outside world comes through
 * here: a controller that never touches $_GET or $_POST can be reasoned
 * about from its signature, and the route's own values (an id in the path,
 * a room's machine name) arrive the same way as a query parameter instead of
 * through a handful of globals the way they used to.
 *
 * The path is the one below the base path -- the application may live in a
 * sub-folder, and the prefix is taken from SCRIPT_NAME rather than from the
 * configuration, so it also works behind a proxy that strips the prefix.
 */
final class Request
{
    /**
     * Values the kernel puts here while it works out what to do: the matched
     * route's name and its placeholders, the resolved room. A controller
     * reads them through attribute(); nothing outside the kernel writes them.
     *
     * @var array<string,mixed>
     */
    private array $attributes = [];

    /**
     * @param array<string,mixed> $query   $_GET
     * @param array<string,mixed> $post    $_POST
     * @param array<string,mixed> $cookies $_COOKIE
     * @param array<string,mixed> $files   $_FILES
     * @param array<string,mixed> $server  $_SERVER
     */
    public function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly array $query,
        private readonly array $post,
        private readonly array $cookies,
        private readonly array $files,
        private readonly array $server,
    ) {
    }

    public static function fromGlobals(): self
    {
        $uri  = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = rawurldecode((string) parse_url($uri, PHP_URL_PATH));

        // The prefix the application is mounted under, from the script's own
        // address: '' at the domain root, '/songliste' in a sub-folder.
        $scriptDir = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'))), '/');
        if ($scriptDir !== '' && str_starts_with($path, $scriptDir)) {
            $path = substr($path, strlen($scriptDir));
        }

        return new self(
            strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
            // A trailing slash names the same page: '/wishes/' is '/wishes'.
            // The root stays '' -- the route table's key for the start page.
            rtrim($path, '/'),
            $_GET,
            $_POST,
            $_COOKIE,
            $_FILES,
            $_SERVER,
        );
    }

    public function method(): string
    {
        return $this->method;
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    /** The path below the base path, without a trailing slash; '' is the start page. */
    public function path(): string
    {
        return $this->path;
    }

    public function query(string $key, string $default = ''): string
    {
        $value = $this->query[$key] ?? null;

        return is_scalar($value) ? (string) $value : $default;
    }

    public function hasQuery(string $key): bool
    {
        return array_key_exists($key, $this->query);
    }

    /** @return array<string,mixed> */
    public function queryAll(): array
    {
        return $this->query;
    }

    public function post(string $key, string $default = ''): string
    {
        $value = $this->post[$key] ?? null;

        return is_scalar($value) ? (string) $value : $default;
    }

    public function hasPost(string $key): bool
    {
        return array_key_exists($key, $this->post);
    }

    /**
     * A posted list -- checkboxes, the ids of a selection. Non-scalar
     * members are dropped, so a nested array in the request body cannot
     * reach a repository.
     *
     * @return array<int,string>
     */
    public function postList(string $key): array
    {
        $value = $this->post[$key] ?? null;
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_map(
            static fn (mixed $v): string => is_scalar($v) ? (string) $v : '',
            array_filter($value, 'is_scalar'),
        ));
    }

    /**
     * A posted map of language code to text -- a page's versions, the footer
     * lines. Same reasoning as postList(): scalars only.
     *
     * @return array<string,string>
     */
    public function postMap(string $key): array
    {
        $value = $this->post[$key] ?? null;
        if (!is_array($value)) {
            return [];
        }

        $map = [];
        foreach ($value as $code => $text) {
            $map[(string) $code] = is_scalar($text) ? (string) $text : '';
        }

        return $map;
    }

    /** @return array<string,mixed> */
    public function postAll(): array
    {
        return $this->post;
    }

    /** @return array<string,mixed> */
    public function file(string $key): array
    {
        $value = $this->files[$key] ?? null;

        return is_array($value) ? $value : [];
    }

    public function cookie(string $key, ?string $default = null): ?string
    {
        $value = $this->cookies[$key] ?? null;

        return is_scalar($value) ? (string) $value : $default;
    }

    public function server(string $key, string $default = ''): string
    {
        $value = $this->server[$key] ?? null;

        return is_scalar($value) ? (string) $value : $default;
    }

    /**
     * Does the caller expect JSON? Drag & drop posts through fetch and wants
     * a verdict, not a redirect it would have to follow and throw away.
     */
    public function wantsJson(): bool
    {
        return $this->server('HTTP_X_REQUESTED_WITH') === 'fetch'
            || str_contains($this->server('HTTP_ACCEPT'), 'application/json');
    }

    /** @param array<string,mixed> $attributes */
    public function withAttributes(array $attributes): void
    {
        $this->attributes = $attributes + $this->attributes;
    }

    public function attribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    /** A route placeholder as a whole number: an id in the path, 0 when absent. */
    public function routeInt(string $key): int
    {
        $value = $this->attributes[$key] ?? null;

        return is_scalar($value) ? (int) $value : 0;
    }

    /** A route placeholder as text: a machine name, an image format. */
    public function routeString(string $key): string
    {
        $value = $this->attributes[$key] ?? null;

        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * The placeholders the path carried, without the kernel's own entries.
     * Feeding them back to the URL generator with the route name rebuilds
     * the current address -- what the header's forms and the language links
     * need, and the address a page polls for live updates.
     *
     * @return array<string,string>
     */
    public function routeParams(): array
    {
        $params = [];
        foreach ($this->attributes as $key => $value) {
            if (!str_starts_with((string) $key, '_') && is_scalar($value)) {
                $params[(string) $key] = (string) $value;
            }
        }

        return $params;
    }

    /** The name of the matched route; '' before the kernel has matched one. */
    public function routeName(): string
    {
        return $this->routeString('_route');
    }

    /**
     * The page key of the matched route -- what the templates and the
     * navigation know a screen by. Several routes share one: the song form
     * is 'song' whether it is reached as /song/new or /suggestions/7/adopt.
     */
    public function page(): string
    {
        return $this->routeString('_page');
    }
}
