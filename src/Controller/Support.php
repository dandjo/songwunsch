<?php

declare(strict_types=1);

namespace Songwunsch\Controller;

use Songwunsch\FlashBag;
use Songwunsch\FormMemory;
use Songwunsch\Http\Request;
use Songwunsch\Routing\UrlGenerator;
use Songwunsch\Security;

/**
 * The five things every controller needs: the request, the URL generator,
 * the two session-backed stores that carry a message and a half-filled form
 * across a redirect, and who is asking.
 *
 * Passed as one object so that a controller's own constructor lists its own
 * dependencies -- its repositories -- and nothing else. It is data, not a
 * container: everything in it is named and typed.
 */
final class Support
{
    public function __construct(
        public readonly Request $request,
        public readonly UrlGenerator $urls,
        public readonly FlashBag $flash,
        public readonly FormMemory $forms,
        public readonly Security $security,
    ) {
    }
}
