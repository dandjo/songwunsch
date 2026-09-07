<?php

declare(strict_types=1);

namespace Songwunsch\EventListener;

use Songwunsch\FlashBag;
use Songwunsch\Http\Request;
use Songwunsch\Http\Response;
use Songwunsch\Routing\UrlGenerator;
use Songwunsch\Security;

/**
 * Every POST carries the session's token; without it nothing happens.
 *
 * Checked here rather than in the controllers, so that no action can be
 * added that forgets it. A session that has expired while a form stood open
 * is the ordinary case, and it gets a sentence and the page back, not a
 * refusal.
 */
final class CsrfListener implements RequestListener
{
    public function __construct(
        private readonly Security $security,
        private readonly UrlGenerator $urls,
        private readonly FlashBag $flash,
    ) {
    }

    public function handle(Request $request): ?Response
    {
        if (!$request->isPost() || $this->security->checkCsrf($request->post('csrf'))) {
            return null;
        }

        if ($request->wantsJson()) {
            return Response::json(['ok' => false, 'error' => t('Session expired. Please reload the page.')], 403);
        }

        $this->flash->notice('error', t('The session has expired. Please try again.'));

        // Back to the page the form stood on, so the visitor can try again
        // where they were.
        return Response::redirect(
            $this->urls->safeTarget($request->post('back')) ?? $this->urls->generate('songs'),
        );
    }
}
