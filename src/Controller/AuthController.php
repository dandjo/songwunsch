<?php

declare(strict_types=1);

namespace Songwunsch\Controller;

use Songwunsch\Http\Request;
use Songwunsch\Http\Response;
use Songwunsch\Template\View;
use Songwunsch\UserRepository;

/**
 * Logging in and out, and the guest view.
 */
final class AuthController extends Controller
{
    /** @param array<string,mixed> $firstAdmin the 'auth' block of config.php */
    public function __construct(
        Support $support,
        private readonly UserRepository $users,
        private readonly array $firstAdmin,
    ) {
        parent::__construct($support);
    }

    public function form(Request $request): View|Response
    {
        if ($this->support->security->isLoggedIn()) {
            return $this->redirectTo($this->home());
        }

        return $this->view('login', t('Log in'));
    }

    public function login(Request $request): Response
    {
        // The first admin comes from config.php while there are no users yet.
        $this->users->ensureAdmin($this->firstAdmin);

        if ($this->support->security->login(trim($request->post('user')), $request->post('pass'))) {
            $this->flash('ok', t('Logged in as {name}.', ['name' => $this->support->security->username()]));

            return $this->redirectTo($this->home());
        }

        // Do not reveal which part was wrong.
        $this->notice('error', t('Username or password is incorrect.'));

        return $this->redirect('login');
    }

    public function logout(Request $request): Response
    {
        $this->support->security->logout();

        return $this->redirect('songs');
    }

    /**
     * Look at the site as a visitor without a login, or back. The account is
     * checked directly: in the guest view isLoggedIn() already answers no,
     * which is the whole point.
     */
    public function guestView(Request $request): Response
    {
        if ($this->support->security->account() === null) {
            $this->notice('info', t('Please log in first.'));

            return $this->redirect('login');
        }

        $on = $request->post('on') === '1';
        $this->support->security->setGuestView($on);
        $this->flash('info', $on
            ? t('You now see the site as a guest does. Your own view is back in the account menu.')
            : t('Back to your own view.'));

        // Stay on the page -- unless it is one a guest may not see; then the
        // song list, like for any stranger. The form names the page it was
        // shown on, since the switch has an address of its own.
        $public = in_array($request->post('page'), ['songs', 'wishes', 'suggestions', 'rooms', 'login', 'page', 'name'], true);

        return $this->redirectTo($on && !$public ? $this->url('songs') : $this->back());
    }

    /** Start page after logging in: the wish list for moderators, otherwise the song list. */
    private function home(): string
    {
        return $this->url($this->support->security->can('wishes') ? 'wishes' : 'songs');
    }
}
