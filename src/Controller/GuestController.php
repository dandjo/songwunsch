<?php

declare(strict_types=1);

namespace Songwunsch\Controller;

use Songwunsch\GuestName;
use Songwunsch\Http\Request;
use Songwunsch\Http\Response;
use Songwunsch\Template\View;

/**
 * The visitor's name for the wish list -- kept in a cookie, never in the
 * database, and never asked for twice.
 */
final class GuestController extends Controller
{
    public function __construct(
        Support $support,
        private readonly GuestName $name,
    ) {
        parent::__construct($support);
    }

    /**
     * The same form the first visit shows in a dialog, as a page for later
     * changes.
     */
    public function form(Request $request): View
    {
        return $this->view('name', t('Your name'), [
            'back' => $this->destination($this->url('songs')),
        ]);
    }

    public function save(Request $request): Response
    {
        $name = GuestName::clean($request->post('name'));
        $this->name->remember($name);
        $this->flash('ok', $name === ''
            ? t('Your wishes now carry no name.')
            : t('Hello {name}! Your wishes now carry your name.', ['name' => $name]));

        return $this->redirectTo($this->back());
    }

    /** "Not now" -- stop asking for this session. */
    public function skip(Request $request): Response
    {
        $this->name->skip();

        return $this->redirectTo($this->back());
    }
}
