<?php

declare(strict_types=1);

namespace Songwunsch\Controller;

use Songwunsch\Http\Request;
use Songwunsch\Http\Response;
use Songwunsch\Limits;
use Songwunsch\Pagination;
use Songwunsch\Settings;
use Songwunsch\Template\View;
use Songwunsch\UserRepository;

/**
 * Users -- the admins' list and form -- and every signed-in user's own
 * settings, which are not an admin page and therefore not below /admin.
 */
final class UserController extends Controller
{
    public function __construct(
        Support $support,
        private readonly UserRepository $users,
        private readonly Settings $settings,
        private readonly Limits $limits,
    ) {
        parent::__construct($support);
    }

    public function index(Request $request): View
    {
        $q       = trim($request->query('q'));
        $perPage = $this->limits->get('per_page');
        $result  = Pagination::of(
            fn (int $page): array => $this->users->page($q, $page, $perPage),
            (int) $request->query('page', '1'),
            $perPage,
        );

        return $this->view('users', t('Users'), [
            'q'      => $q,
            'rows'   => $result['rows'],
            'total'  => $result['total'],
            'pageNo' => $result['page'],
            'pages'  => $result['pages'],
            'selfId' => $this->selfId(),
        ]);
    }

    public function form(Request $request): View|Response
    {
        $id   = $request->routeInt('id'); // 0 = new user
        $user = null;

        if ($id > 0) {
            $user = $this->users->find($id);
            if ($user === null) {
                $this->notice('error', t('This user was not found.'));

                return $this->redirect('users');
            }
        }

        $kept = $this->support->forms->take();

        return $this->view('user', $id === 0 ? t('Add user') : t('Edit user'), [
            'id'     => $id,
            'back'   => $this->destination($this->url('users')),
            'user'   => $user,
            'selfId' => $this->selfId(),
            // The only active admin (oneself, necessarily) keeps the role;
            // the form says so instead of offering a box the save would
            // refuse.
            'onlyAdmin' => $user !== null
                && (int) $user['role_admin'] === 1
                && (int) $user['active'] === 1
                && $this->users->activeAdmins($id) === 0,
            'errors' => $kept['errors'] ?? [],
            'values' => $kept['values'] ?? [
                'username'       => (string) ($user['username'] ?? ''),
                'role_admin'     => (string) ($user['role_admin'] ?? '0'),
                'role_moderator' => (string) ($user['role_moderator'] ?? '0'),
                'role_editor'    => (string) ($user['role_editor'] ?? '0'),
                'active'         => (string) ($user['active'] ?? '1'),
            ],
        ]);
    }

    public function save(Request $request): Response
    {
        $id       = (int) $request->post('id'); // 0 = new user
        $selfId   = $this->selfId();
        $existing = $id > 0 ? $this->users->find($id) : null;

        if ($id > 0 && $existing === null) {
            $this->flash('error', t('This user was not found.'));

            return $this->redirect('users');
        }
        $back = $this->destination($this->url('users'));

        $input = [
            'username'       => $request->post('username'),
            'password'       => $request->post('password'),
            'password2'      => $request->post('password2'),
            'role_admin'     => $request->post('role_admin'),
            'role_moderator' => $request->post('role_moderator'),
            'role_editor'    => $request->post('role_editor'),
            'active'         => $request->post('active'),
        ];
        $checked = $this->users->validate($input, $existing);

        if ($id === $selfId) {
            // Nobody locks themselves out of a running session.
            $checked['values']['active'] = 1;
            // The last active admin keeps the role -- otherwise nobody could
            // manage users any more. Only oneself can be that last admin:
            // whoever gets here is an active admin already.
            if ($checked['values']['role_admin'] !== 1 && $this->users->activeAdmins($id) === 0) {
                $checked['errors']['role_admin'] = t('You are the only active admin – make another user admin first.');
            }
        }

        if ($checked['errors'] !== []) {
            // Passwords never go into the session.
            unset($input['password'], $input['password2']);
            $this->support->forms->remember($input, $checked['errors']);
            $this->notice('error', t('Please check the highlighted fields.'));

            return $this->redirectTo($id > 0
                ? $this->url('user_edit', ['id' => $id, 'back' => $back])
                : $this->url('user_new', ['back' => $back]));
        }

        if ($existing === null) {
            $this->users->create($checked['values']);
            $this->flash('ok', t('User “{name}” has been created.', ['name' => $checked['values']['username']]));

            return $this->redirectTo($back);
        }

        $this->users->update($id, $checked['values']);
        // Own password changed: the default-password warning follows suit.
        if ($id === $selfId && isset($checked['values']['password_hash'])) {
            $this->support->security->notePassword($input['password']);
        }
        // Own admin role given up: the user list is closed from now on, so
        // the way leads to the pages the remaining roles open.
        if ($id === $selfId && $checked['values']['role_admin'] !== 1) {
            $this->flash('ok', t('You are no longer admin. Your other roles stay; another admin can give the role back.'));

            return $this->redirect($checked['values']['role_moderator'] === 1 ? 'wishes' : 'songs');
        }
        $this->flash('ok', t('User “{name}” has been saved.', ['name' => $checked['values']['username']]));

        return $this->redirectTo($back);
    }

    public function delete(Request $request): Response
    {
        $selfId = $this->selfId();
        $target = $this->users->find($request->routeInt('id'));

        if ($target === null) {
            $this->flash('error', t('This user was not found.'));
        } elseif ((int) $target['id'] === $selfId) {
            // Also keeps the last active admin: whoever deletes is one.
            $this->flash('error', t('You cannot delete yourself.'));
        } elseif ($this->users->delete((int) $target['id'])) {
            $this->settings->forgetUser((int) $target['id']);
            $this->flash('ok', t('User “{name}” has been deleted.', ['name' => (string) $target['username']]));
        } else {
            $this->flash('error', t('Deleting was not possible.'));
        }

        return $this->redirectTo($this->back($this->url('users')));
    }

    /** The bare /settings leads to the signed-in user's own page. */
    public function settingsRedirect(Request $request): Response
    {
        return $this->redirect('settings', ['id' => $this->selfId()]);
    }

    /** One's own settings. Any other id leads to one's own page as well. */
    public function settings(Request $request): View|Response
    {
        $selfId = $this->selfId();
        if ($request->routeInt('id') !== $selfId) {
            return $this->redirect('settings', ['id' => $selfId]);
        }

        return $this->view('settings', t('User settings'), [
            'selfId'  => $selfId,
            'kinds'   => $this->support->security->deletableKinds(),
            'account' => $this->support->security->user(),
            'errors'  => $this->support->forms->take()['errors'] ?? [],
        ]);
    }

    /**
     * Which deletions ask this user for confirmation -- only the kinds their
     * roles may delete. Unchecked boxes are not posted, so every kind is
     * written explicitly.
     */
    public function saveSettings(Request $request): Response
    {
        $selfId = $this->selfId();
        foreach ($this->support->security->deletableKinds() as $what) {
            $this->settings->setConfirmDelete($selfId, $what, $request->post('confirm_' . $what) === '1');
        }
        $this->flash('ok', t('Settings saved.'));

        return $this->redirect('settings', ['id' => $selfId]);
    }

    /**
     * A signed-in user -- admins included -- changes their own password: the
     * current one proves it is really them (a forgotten unlocked screen must
     * not be enough).
     */
    public function savePassword(Request $request): Response
    {
        $self = $this->support->security->user();
        $id   = (int) $self['id'];

        $current = $request->post('current_password');
        $new     = $request->post('password');
        $errors  = [];

        if (!password_verify($current, (string) $self['password_hash'])) {
            $errors['current_password'] = t('The current password is not right.');
        }
        $check = $new === ''
            ? ['errors' => ['password' => t('{field} is required.', ['field' => t('New password')])], 'hash' => null]
            : $this->users->checkPassword($new, $request->post('password2'));
        $errors += $check['errors'];

        if ($errors !== []) {
            // Passwords never go into the session -- only which field failed.
            $this->support->forms->remember([], $errors);
            $this->notice('error', t('Please check the highlighted fields.'));

            return $this->redirect('settings', ['id' => $id]);
        }

        $this->users->setPassword($id, (string) $check['hash']);
        $this->support->security->notePassword($new);
        $this->flash('ok', t('Your password has been changed.'));

        return $this->redirect('settings', ['id' => $id]);
    }

    private function selfId(): int
    {
        return (int) ($this->support->security->user()['id'] ?? 0);
    }
}
