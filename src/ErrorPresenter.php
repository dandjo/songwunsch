<?php

declare(strict_types=1);

namespace Songwunsch;

use Throwable;

/**
 * What a visitor is told about a failure.
 *
 * Table and column names, a driver's message, a file path: useful to whoever
 * runs the installation, an invitation to everyone else. The detail is shown
 * to signed-in users, and while show_errors is on in config.php; a stranger
 * gets one sentence, and the detail goes to the error log instead.
 */
final class ErrorPresenter
{
    public function __construct(
        private readonly Security $security,
        private readonly bool $showErrors,
    ) {
    }

    public function detail(Throwable $e): string
    {
        if ($this->showErrors || $this->security->isLoggedIn()) {
            return $e->getMessage();
        }

        error_log('[songwunsch] ' . $e::class . ': ' . $e->getMessage());

        return t('The repertoire is not available right now. Please try again later.');
    }
}
