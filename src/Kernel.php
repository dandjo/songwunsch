<?php

declare(strict_types=1);

namespace Songwunsch;

use Songwunsch\DependencyInjection\Container;
use Songwunsch\EventListener\RequestListener;
use Songwunsch\Http\Exception\AccessDeniedException;
use Songwunsch\Http\Exception\NotFoundException;
use Songwunsch\Http\Request;
use Songwunsch\Http\Response;
use Songwunsch\Routing\RouteMatcher;
use Songwunsch\Template\Renderer;
use Songwunsch\Template\View;
use Throwable;

/**
 * Request in, response out.
 *
 * The whole of the request's life is the four steps of handle(): start the
 * session, find the route, walk the listeners, call the controller. Nothing
 * else in the application decides what happens when; nothing else writes a
 * header or ends a request.
 *
 * The listeners are a plain list in a fixed order (config/services.php), and
 * the order is the design:
 *
 *   RoomListener        which room this request is in -- everything is
 *                       room-scoped, so this comes first
 *   LiveUpdateListener  answers ?poll=1 and stops here. The majority of
 *                       requests end on this line, having built almost
 *                       nothing.
 *   LanguageListener    ?lang=<code>: remember it, drop it from the address
 *   CsrfListener        every POST carries the session's token
 *   AccessListener      who may open this address (config/access.php)
 */
final class Kernel
{
    /** @param list<string> $listeners service ids, in the order they run */
    public function __construct(
        private readonly Container $container,
        private readonly array $listeners,
    ) {
    }

    public function handle(Request $request): Response
    {
        // The session cookie is scoped to the base path, so several
        // applications on the same domain do not share a session.
        $this->container->get(Security::class)->startSession();

        try {
            return $this->dispatch($request);
        } catch (NotFoundException $e) {
            return $this->notFound();
        } catch (AccessDeniedException $e) {
            return $this->denied($request, $e);
        } catch (Throwable $e) {
            return $this->failed($request, $e);
        }
    }

    private function dispatch(Request $request): Response
    {
        $match = $this->container->get(RouteMatcher::class)->match($request);
        // From here on the route's own values -- an id, a machine name, the
        // room -- are on the request, and its name and page key with them.
        $request->withAttributes($match->attributes());

        foreach ($this->listeners as $id) {
            /** @var RequestListener $listener */
            $listener = $this->container->get($id);
            $response = $listener->handle($request);
            if ($response !== null) {
                return $response;
            }
        }

        // All tables exist before the first data access of a request,
        // whatever environment the application runs in (Schema::ensure(),
        // memoised). Logging out is the one route that must work when the
        // database does not.
        if ($match->route->needsSchema()) {
            $this->container->get(Schema::class)->ensure();
        }

        [$class, $method] = $match->route->controller();
        $result = $this->container->get($class)->{$method}($request);

        return $result instanceof View
            ? $this->container->get(Renderer::class)->render($result)
            : $result;
    }

    /**
     * An address the route table does not hold. Answered without layout,
     * database or session data: at this point nothing is known about the
     * request beyond its being nowhere.
     */
    private function notFound(): Response
    {
        $e    = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
        $link = '<a href="' . $e($this->container->get(Routing\UrlGenerator::class)->generate('songs')) . '">'
            . $e(t('To the repertoire')) . '</a>';

        return $this->container->get(Renderer::class)->bare(
            404,
            t('Page not found'),
            $e(t('There is nothing at this address.')) . ' ' . $link,
            asset('assets/style.css'),
        );
    }

    /**
     * Not signed in, or signed in without the role this address needs. The
     * first is not a refusal but a detour: the login page, and the message
     * says why.
     */
    private function denied(Request $request, AccessDeniedException $e): Response
    {
        if ($e->needsLogin()) {
            if ($request->wantsJson()) {
                return Response::json(['ok' => false, 'error' => t('Please log in first.')], 401);
            }
            $this->container->get(FlashBag::class)->notice('info', t('Please log in first.'));

            return Response::redirect($this->container->get(Routing\UrlGenerator::class)->generate('login'));
        }

        if ($request->wantsJson()) {
            return Response::json(['ok' => false, 'error' => t('You do not have permission for that.')], 403);
        }
        $this->container->get(FlashBag::class)->notice('error', t('You do not have permission for that.'));

        return Response::redirect($this->container->get(Routing\UrlGenerator::class)->generate('songs'));
    }

    /**
     * Something went wrong. An action says so and hands the page back, so
     * that whatever was typed is still on the screen; a page says so in
     * place of its content.
     */
    private function failed(Request $request, Throwable $e): Response
    {
        try {
            $detail = $this->container->get(ErrorPresenter::class)->detail($e);

            if ($request->isPost()) {
                $this->container->get(FlashBag::class)->notice('error', t('An error occurred: {detail}', ['detail' => $detail]));

                return Response::redirect(
                    $this->container->get(Routing\UrlGenerator::class)->safeTarget($request->post('back'))
                        ?? $this->container->get(Routing\UrlGenerator::class)->generate('songs'),
                );
            }

            return $this->container->get(Renderer::class)->render(
                new View('error', t('Error'), ['message' => $detail]),
                500,
            );
        } catch (Throwable $fatal) {
            // The error page itself cannot be built -- no configuration, no
            // stylesheet address. One sentence, no dependencies.
            return Response::html(
                '<!doctype html><meta charset="utf-8"><title>Error</title><p>An error occurred.</p>',
                500,
            );
        }
    }
}
