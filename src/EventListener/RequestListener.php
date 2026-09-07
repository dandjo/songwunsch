<?php

declare(strict_types=1);

namespace Songwunsch\EventListener;

use Songwunsch\Http\Request;
use Songwunsch\Http\Response;

/**
 * A step the kernel takes between matching the route and calling the
 * controller. It either prepares something the controller relies on -- the
 * room of the request -- or answers the request itself and stops the chain:
 * a live-update poll, a language switch, a rejected CSRF token.
 *
 * The order is fixed and declared in config/services.php; there is no
 * dispatcher, because five steps in a known order are a list.
 */
interface RequestListener
{
    /** A response ends the request; null passes it on. */
    public function handle(Request $request): ?Response;
}
