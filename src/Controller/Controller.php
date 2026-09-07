<?php

declare(strict_types=1);

namespace Songwunsch\Controller;

use Songwunsch\Http\Exception\NotFoundException;
use Songwunsch\Http\Request;
use Songwunsch\Http\Response;
use Songwunsch\Template\View;

/**
 * What every controller can do: build an address, answer with a redirect, a
 * view or JSON, and leave a message for the page that follows.
 *
 * A controller method takes the request and returns a Response or a View --
 * it never writes a header, never echoes and never calls exit. Which role
 * may reach it is not its business either: that is config/access.php.
 */
abstract class Controller
{
    public function __construct(protected readonly Support $support)
    {
    }

    protected function request(): Request
    {
        return $this->support->request;
    }

    /** @param array<string,mixed> $params */
    protected function url(string $route, array $params = []): string
    {
        return $this->support->urls->generate($route, $params);
    }

    /** @param array<string,mixed> $params */
    protected function redirect(string $route, array $params = []): Response
    {
        return Response::redirect($this->url($route, $params));
    }

    protected function redirectTo(string $target): Response
    {
        return Response::redirect($target);
    }

    /**
     * Where a POST goes when it is done. The form carries the address it was
     * opened from in a hidden 'back' field, so it survives the round trip of
     * post/redirect/get and a failed validation; anything that is not an
     * address of this installation is ignored (UrlGenerator::safeTarget).
     */
    protected function back(?string $fallback = null): string
    {
        return $this->support->urls->safeTarget($this->request()->post('back'))
            ?? $fallback
            ?? $this->url('songs');
    }

    /**
     * The destination of a form: where Cancel leads and where the save
     * redirects to -- the page the visitor came from (Drupal calls this the
     * "destination"). The link into the form passes it as ?back=, the form
     * keeps it in a hidden field.
     */
    protected function destination(string $fallback): string
    {
        return $this->support->urls->safeTarget($this->request()->post('back'))
            ?? $this->support->urls->safeTarget($this->request()->query('back'))
            ?? $fallback;
    }

    /** @param array<string,mixed> $vars */
    protected function view(string $template, string $title, array $vars = [], bool $editor = false): View
    {
        return new View($template, $title, $vars, $editor);
    }

    /** @param array<string,mixed> $payload */
    protected function json(array $payload, int $status = 200): Response
    {
        return Response::json($payload, $status);
    }

    /** The result of an action: pops up for a few seconds and goes away. */
    protected function flash(string $type, string $message): void
    {
        $this->support->flash->add($type, $message);
    }

    /** A message that belongs to the page one lands on and stays there. */
    protected function notice(string $type, string $message): void
    {
        $this->support->flash->notice($type, $message);
    }

    protected function notFound(): never
    {
        throw new NotFoundException('Nothing at this address');
    }
}
