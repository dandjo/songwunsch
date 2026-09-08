<?php

declare(strict_types=1);

namespace Songwunsch\Controller;

use Songwunsch\GuestName;
use Songwunsch\Http\Request;
use Songwunsch\Http\Response;
use Songwunsch\Template\View;
use Songwunsch\Theme;

/**
 * What a visitor sets for themselves: the name their wishes carry and the
 * colour scheme they read the site in. Both are kept in a cookie, never in
 * the database -- most visitors here have no account -- and the name is
 * never asked for twice.
 */
final class GuestController extends Controller
{
    public function __construct(
        Support $support,
        private readonly GuestName $name,
        private readonly Theme $theme,
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

    /**
     * Light or dark. No message afterwards: the page comes back in the new
     * scheme, which says it better than any notice could.
     */
    public function theme(Request $request): Response
    {
        $this->theme->remember((string) $request->post('theme'));

        return $this->redirectTo($this->back());
    }
}
