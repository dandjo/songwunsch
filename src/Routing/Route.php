<?php

declare(strict_types=1);

namespace Songwunsch\Routing;

/**
 * One address of the application: its path, the controller behind it and the
 * name both directions use.
 *
 * A route is declared once, in config/routes.php, and answers both
 * questions -- which controller does this path belong to (RouteMatcher) and
 * what is the address of this controller (UrlGenerator). That is the whole
 * point of the class: the path pattern used to live in a preg_match chain
 * and its counterpart in an if/elseif chain in url(), two hand-kept copies
 * of the same table that nothing checked against each other.
 *
 * Placeholders are written {name}; a requirement gives the pattern such a
 * placeholder must match, '[^/]+' when none is given. A default fills a
 * placeholder the path did not carry.
 */
final class Route
{
    /** @var array<string,string> */
    private array $defaults = [];

    /** @var array<string,string> */
    private array $requirements = [];

    /**
     * Room scope. A room's pages exist twice: once for the main room, which
     * lives at the base path and has no machine name of its own, and once
     * below /rooms/<slug> for every other room. Both forms come from this
     * one declaration -- see $mainPath, RouteMatcher and UrlGenerator.
     *
     * 'both'   the path as declared is the main room's, /rooms/<slug> before
     *          it is every other room's (the song list, the wishes, the
     *          suggestions and the actions on them)
     * 'prefix' only inside a real room (a room's song selection), unless
     *          $mainPath names an address for the main room as well
     * 'none'   not a room's page
     */
    private string $scope = 'none';

    /**
     * The main room's own address, where it is not simply the bare path: the
     * QR code lives at /rooms/main/qr, because the main room's pages are the
     * base path itself and a QR code needs an address of its own.
     */
    private ?string $mainPath = null;

    /**
     * Does this address name its room outright? Then the address wins and
     * the remembered room neither steps in nor changes -- an editor looking
     * at a room's QR code is not entering the room. See RoomListener.
     */
    private bool $namesRoom = false;

    /**
     * The page key: what the templates and the navigation know this screen
     * by, and the key the live-update tokens are chosen with. Several routes
     * share one -- the song form is 'song' whether it is reached as /song/new
     * or as /suggestions/<id>/adopt -- which is why this is not simply the
     * route name. Empty means "the same as the name".
     */
    private string $page = '';

    /**
     * Does this address answer with bytes rather than a page -- an uploaded
     * logo, a QR image? Such a response carries no header and no content
     * that could be swapped, so it has no live-update tokens and answers no
     * poll (see LiveUpdateListener).
     */
    private bool $raw = false;

    /**
     * Skip Schema::ensure() before the controller. Logging out must work
     * when the database does not, which is the one route where it matters.
     */
    private bool $needsSchema = true;

    /**
     * @param list<string>               $methods    GET implies HEAD; a POST route answers POST alone
     * @param array{class-string,string} $controller class name and method
     */
    private function __construct(
        private readonly string $name,
        private readonly string $path,
        private readonly array $controller,
        private readonly array $methods,
    ) {
    }

    /** @param array{class-string,string} $controller */
    public static function get(string $name, string $path, array $controller): self
    {
        return new self($name, $path, $controller, ['GET', 'HEAD']);
    }

    /** @param array{class-string,string} $controller */
    public static function post(string $name, string $path, array $controller): self
    {
        return new self($name, $path, $controller, ['POST']);
    }

    /** @param array<string,string> $defaults */
    public function defaults(array $defaults): self
    {
        $this->defaults = $defaults + $this->defaults;

        return $this;
    }

    /** @param array<string,string> $requirements */
    public function requirements(array $requirements): self
    {
        $this->requirements = $requirements + $this->requirements;

        return $this;
    }

    /** The path as declared is the main room's, /rooms/<slug> before it every other room's. */
    public function roomScoped(): self
    {
        $this->scope = 'both';

        return $this;
    }

    /**
     * Only inside a room. $mainPath gives the main room an address of its
     * own where the bare path is not available (the QR code).
     */
    public function roomOnly(?string $mainPath = null): self
    {
        $this->scope    = 'prefix';
        $this->mainPath = $mainPath;

        return $this;
    }

    /** @see $namesRoom */
    public function namesRoom(): self
    {
        $this->namesRoom = true;

        return $this;
    }

    /** @see $needsSchema */
    public function withoutSchema(): self
    {
        $this->needsSchema = false;

        return $this;
    }

    /** @see $raw */
    public function raw(): self
    {
        $this->raw = true;

        return $this;
    }

    /** Share a page key with another route -- see $page. */
    public function page(string $page): self
    {
        $this->page = $page;

        return $this;
    }

    public function name(): string
    {
        return $this->name;
    }

    /** The declared path, without a trailing slash: the start page is ''. */
    public function path(): string
    {
        return rtrim($this->path, '/');
    }

    /**
     * The address outside /rooms/<slug>: the declared path for a route that
     * has both forms, $mainPath for one that names the main room explicitly,
     * null for a route that exists only inside a real room.
     */
    public function unprefixedPath(): ?string
    {
        if ($this->mainPath !== null) {
            return rtrim($this->mainPath, '/');
        }

        return $this->scope === 'prefix' ? null : $this->path();
    }

    /** The address below /rooms/<slug>, null for a route that is not a room's. */
    public function prefixedPath(): ?string
    {
        return $this->scope === 'none' ? null : '/rooms/{room}' . $this->path();
    }

    /** @return array{class-string,string} */
    public function controller(): array
    {
        return $this->controller;
    }

    /** @return list<string> */
    public function methods(): array
    {
        return $this->methods;
    }

    /** @return array<string,string> */
    public function defaultValues(): array
    {
        return $this->defaults;
    }

    public function isRoomScoped(): bool
    {
        return $this->scope !== 'none';
    }

    public function addressNamesRoom(): bool
    {
        return $this->namesRoom;
    }

    public function isRaw(): bool
    {
        return $this->raw;
    }

    public function needsSchema(): bool
    {
        return $this->needsSchema;
    }

    /** @see $page */
    public function pageKey(): string
    {
        return $this->page !== '' ? $this->page : $this->name;
    }

    /**
     * A path as a regular expression together with its placeholder names, in
     * the order they appear. Compiled on demand and kept: a request tries a
     * handful of routes before it finds its own.
     *
     * @return array{0:string,1:list<string>}
     */
    public function compile(string $path): array
    {
        /** @var array<string,array{0:string,1:list<string>}> $cache */
        static $cache = [];

        // Keyed by route as well as path: two routes may declare the same
        // path shape with different requirements.
        $key = $this->name . ' ' . $path;
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $names  = [];
        $regex  = '';
        $offset = 0;

        // Walk the placeholders in order; everything between them is literal
        // and is quoted, so a dot in a path stays a dot instead of becoming
        // "any character".
        preg_match_all('/\{([A-Za-z_][A-Za-z0-9_]*)\}/', $path, $found, PREG_OFFSET_CAPTURE);
        foreach ($found[1] as $i => $match) {
            $name   = (string) $match[0];
            $at     = (int) $found[0][$i][1];
            $regex .= preg_quote(substr($path, $offset, $at - $offset), '#');
            $offset = $at + strlen((string) $found[0][$i][0]);

            $names[] = $name;
            $regex  .= '(' . ($this->requirements[$name] ?? '[^/]+') . ')';
        }
        $regex .= preg_quote(substr($path, $offset), '#');

        return $cache[$key] = ['#^' . $regex . '$#u', $names];
    }

    /**
     * The placeholder names of a path, for the generator and for the
     * round-trip check in tools/check-routes.php.
     *
     * @return list<string>
     */
    public function placeholders(string $path): array
    {
        return $this->compile($path)[1];
    }
}
