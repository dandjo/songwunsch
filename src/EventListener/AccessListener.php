<?php

declare(strict_types=1);

namespace Songwunsch\EventListener;

use Songwunsch\Http\Exception\AccessDeniedException;
use Songwunsch\Http\Request;
use Songwunsch\Http\Response;
use Songwunsch\Security;

/**
 * Who may open this address, from one table: config/access.php.
 *
 * Every route that is not in that table is public -- the repertoire, the
 * wish list, the suggestions, the rooms, a page, wishing and suggesting
 * themselves. Everything else names the role area it needs ('wishes',
 * 'songs', 'suggestions', 'rooms', 'users'), or ANY for "some login".
 *
 * This used to be 44 require_role() calls in the first line of 44 switch
 * branches, which meant that whether an address was protected could only be
 * answered by reading all of them. Now it can be read in one file, and a new
 * route cannot forget the line.
 */
final class AccessListener implements RequestListener
{
    /** Any signed-in user, whatever their roles: their own settings. */
    public const ANY = '*';

    /**
     * Deliberately open to everyone. Only meaningful for a route that would
     * otherwise be refused -- a POST, or an address under /admin -- and it
     * has to be written down, because for those two classes silence is a
     * refusal and not a permission. A public GET page needs no entry.
     */
    public const OPEN = 'public';

    /**
     * @param array<string,string> $map     route name => role area, ANY or OPEN
     * @param list<string>         $guarded names that have to appear in the map:
     *                                      every POST route and everything under
     *                                      /admin. A route of those classes that
     *                                      nobody classified is refused rather
     *                                      than served -- forgetting an entry
     *                                      then closes an address instead of
     *                                      opening it.
     */
    public function __construct(
        private readonly Security $security,
        private readonly array $map,
        private readonly array $guarded = [],
    ) {
    }

    public function handle(Request $request): ?Response
    {
        $name     = $request->routeName();
        $required = $this->map[$name] ?? null;
        if ($required === self::OPEN) {
            return null;
        }
        if ($required === null) {
            // Not classified: open, unless it is one of the classes that may
            // not be open by accident.
            if (in_array($name, $this->guarded, true)) {
                throw AccessDeniedException::notLoggedIn();
            }

            return null;
        }

        if (!$this->security->isLoggedIn()) {
            throw AccessDeniedException::notLoggedIn();
        }
        if ($required !== self::ANY && !$this->security->can($required)) {
            throw new AccessDeniedException($required);
        }

        return null;
    }
}
