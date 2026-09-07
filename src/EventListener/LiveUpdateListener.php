<?php

declare(strict_types=1);

namespace Songwunsch\EventListener;

use Songwunsch\Http\Request;
use Songwunsch\Http\Response;
use Songwunsch\LiveTokens;
use Songwunsch\Routing\RouteCollection;
use Songwunsch\Schema;
use Throwable;

/**
 * Answers ?poll=1 -- and answers it here, before anything below is built.
 *
 * That is the whole point of where this listener sits. A poll is not a page:
 * it builds no view, takes no message off the session, reads no setting its
 * answer does not need and, thanks to the lazy container behind it, never
 * constructs the repositories, the uploads or a parsed translation
 * catalogue. Polls are the majority of requests on a busy night; the
 * cheapest possible answer to them is what makes live updates affordable on
 * a host that allows a handful of PHP processes for the whole account.
 *
 * Cheaper still is the static file the browser asks for first
 * (assets/live.txt, see LiveSignal): an unchanged check is a 304 from the
 * web server with no body and no PHP at all. PHP is asked only once that
 * value has moved, plus once a minute as a safety net.
 */
final class LiveUpdateListener implements RequestListener
{
    public function __construct(
        private readonly RouteCollection $routes,
        private readonly Schema $schema,
        private readonly LiveTokens $tokens,
    ) {
    }

    public function handle(Request $request): ?Response
    {
        // A POST is an action; it answers with the redirect of
        // post/redirect/get, never with a poll.
        if ($request->isPost() || !$request->hasQuery('poll')) {
            return null;
        }
        // Resources without a header (a logo, a QR image) have no tokens.
        if ($this->routes->get($request->routeName())->isRaw()) {
            return null;
        }

        try {
            // The tables are there before the settings are read; after the
            // first call in a request this costs nothing.
            $this->schema->ensure();
            $payload = [
                'rev'  => $this->tokens->content($request->page()),
                'head' => $this->tokens->head(),
            ];
        } catch (Throwable $e) {
            // No database, no tokens: let the page below report the problem.
            // There is nothing an open page could poll for.
            return null;
        }

        // Nothing after this line needs the session, and its file is locked
        // for as long as it is open: letting go now lets a page this same
        // browser is loading in parallel carry on.
        session_write_close();

        return Response::json($payload)->withHeader('Cache-Control', 'no-store');
    }
}
