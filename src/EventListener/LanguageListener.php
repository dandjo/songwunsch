<?php

declare(strict_types=1);

namespace Songwunsch\EventListener;

use Songwunsch\Http\Request;
use Songwunsch\Http\Response;
use Songwunsch\Routing\UrlGenerator;
use Songwunsch\Translator;

/**
 * ?lang=<code> switches the interface language.
 *
 * Which language a request speaks is worked out by the Translator itself,
 * on first use -- so a request that shows no text (a poll, an image) never
 * parses a catalogue. What is left for a listener is the switch: remember
 * the choice and get the parameter out of the address, so that a reload, a
 * bookmark or a shared link is not stuck on a language.
 */
final class LanguageListener implements RequestListener
{
    public function __construct(
        private readonly Translator $translator,
        private readonly UrlGenerator $urls,
        private readonly string $cookiePath,
        private readonly bool $secure = false,
    ) {
    }

    public function handle(Request $request): ?Response
    {
        if (!$request->hasQuery('lang')) {
            return null;
        }

        $this->translator->remember($this->translator->code(), $this->cookiePath, $this->secure);

        // The same address without the parameter -- with everything the path
        // carried (an id, a machine name, a room) still in place.
        $query = array_diff_key($request->queryAll(), ['lang' => true]);

        return Response::redirect($this->urls->generate(
            $request->routeName(),
            $request->routeParams() + $query,
        ));
    }
}
