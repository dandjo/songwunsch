<?php

declare(strict_types=1);

namespace Songwunsch\Http\Exception;

use RuntimeException;

/**
 * The visitor may not do this. Thrown by the access listener from the access
 * map (config/access.php), which is the only place that decides it.
 *
 * $area names the role area that was missing ('wishes', 'songs',
 * 'suggestions', 'rooms', 'users'); an empty area means no login at all,
 * which the kernel answers with the login page instead of a refusal.
 */
final class AccessDeniedException extends RuntimeException
{
    public function __construct(public readonly string $area = '')
    {
        parent::__construct('Access denied');
    }

    /** Not signed in -- the way on is the login page, not a refusal. */
    public static function notLoggedIn(): self
    {
        return new self('');
    }

    public function needsLogin(): bool
    {
        return $this->area === '';
    }
}
