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

    /** @param array<string,string> $map route name => role area, or ANY */
    public function __construct(
        private readonly Security $security,
        private readonly array $map,
    ) {
    }

    public function handle(Request $request): ?Response
    {
        $required = $this->map[$request->routeName()] ?? null;
        if ($required === null) {
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
