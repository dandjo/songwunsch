<?php

declare(strict_types=1);

namespace Songwunsch\Http\Exception;

use RuntimeException;

/**
 * No page lives at this address. Thrown by the router for a path that
 * matches nothing, and by a controller whose record turns out to be gone --
 * an unknown page machine name, a deleted logo. The kernel turns it into the
 * 404 page.
 */
final class NotFoundException extends RuntimeException
{
}
