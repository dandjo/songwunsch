<?php

declare(strict_types=1);

namespace Songwunsch;

use Throwable;

/**
 * What a visitor is told about a failure.
 *
 * Table and column names, a driver's message, a file path: useful to whoever
 * runs the installation, an invitation to everyone else. The detail is shown
 * to signed-in users, and to everyone while show_errors is on in config.php
 * -- which is off by default, because a new installation answers requests
 * before anyone has read that file. A stranger gets one sentence.
 *
 * The detail reaches the error log in every case, shown or not: a failure
 * that was only put on the screen of whoever happened to hit it is a failure
 * nobody can look into afterwards.
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
        error_log(sprintf(
            '[songwunsch] %s: %s in %s:%d',
            $e::class,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
        ));

        if ($this->showErrors || $this->security->isLoggedIn()) {
            return $e->getMessage();
        }

        return t('The repertoire is not available right now. Please try again later.');
    }
}
