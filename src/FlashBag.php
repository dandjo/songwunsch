<?php

declare(strict_types=1);

namespace Songwunsch;

/**
 * One message for the next page, carried across the redirect of
 * post/redirect/get in the session.
 *
 * Two kinds, and the difference is where they are shown:
 *
 *  - add()    the result of an action -- a wish is in, a row was deleted, the
 *             order was saved. It pops up for a few seconds and goes away
 *             (app.js; without JavaScript the layout renders it inline).
 *  - notice() a message that belongs to the page one lands on and stays
 *             until the next: "please log in first", "check the highlighted
 *             fields", "there is no room at this address".
 */
final class FlashBag
{
    private const KEY = 'flash';

    /** @param 'ok'|'info'|'error' $type */
    public function add(string $type, string $message): void
    {
        $_SESSION[self::KEY] = ['type' => $type, 'message' => $message, 'static' => false];
    }

    /** @param 'ok'|'info'|'error' $type */
    public function notice(string $type, string $message): void
    {
        $_SESSION[self::KEY] = ['type' => $type, 'message' => $message, 'static' => true];
    }

    /**
     * Read and remove -- a message is shown once.
     *
     * @return array{type:string,message:string,static:bool}|null
     */
    public function take(): ?array
    {
        $flash = $_SESSION[self::KEY] ?? null;
        unset($_SESSION[self::KEY]);

        if (!is_array($flash)) {
            return null;
        }

        return [
            'type'    => (string) ($flash['type'] ?? 'info'),
            'message' => (string) ($flash['message'] ?? ''),
            'static'  => (bool) ($flash['static'] ?? false),
        ];
    }
}
