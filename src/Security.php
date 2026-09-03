<?php

declare(strict_types=1);

namespace Songwunsch;

/**
 * Session, login against the `users` table, roles and CSRF protection.
 *
 * The session only holds the user id; the record is loaded fresh on every
 * request. When the admin changes roles or locks a user, that takes effect
 * immediately -- even for a session that is already running.
 *
 * Areas for can():
 *   'wishes'  edit the wish list     -- moderator or admin
 *   'songs'   maintain the song list -- editor or admin
 *   'rooms'   manage rooms            -- editor or admin
 *   'users'   manage users           -- admin only
 *
 * Guest view: a signed-in user can look at the site the way a visitor
 * without a login sees it. While the view is on, user() and everything
 * built on it (isLoggedIn, can, isAdmin, ...) answer as for a guest, so
 * pages, controls and POST actions behave exactly like for a stranger.
 * Only account() still knows who is actually signed in -- for the account
 * menu, where the view is switched back.
 */
final class Security
{
    private const SESSION_NAME = 'songwunsch';

    /** Hash used for unknown usernames so the check takes the same time. */
    /** Password the example configuration ships with -- the admin is warned while it is still in use. */
    public const DEFAULT_PASSWORD = 'Administrator';

    private const DUMMY_HASH = '$2y$12$yj8cmii9zUipXmvTfcFUR.kZlaxBEVjHAVciYdvUBmQCd0ZD6vRKm';

    /** @var array<string,mixed>|null|false false = not loaded yet */
    private array|null|false $user = false;

    /**
     * @param string $cookiePath scope of the session cookie, e.g. '/songliste/'
     *                           -- always with a trailing slash.
     */
    public function __construct(
        private readonly UserRepository $users,
        private readonly string $cookiePath = '/',
    ) {
    }

    public function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $https = self::isHttps();

        session_name(self::SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => $this->cookiePath,
            'secure'   => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    /**
     * Detect HTTPS -- also behind a reverse proxy (Traefik, nginx) that
     * terminates TLS. The value only controls the Secure flag of cookies.
     */
    public static function isHttps(): bool
    {
        $direct = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
        if ($direct !== '' && $direct !== 'off') {
            return true;
        }

        $forwarded = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));

        return str_starts_with($forwarded, 'https');
    }

    // ---- User ---------------------------------------------------------------

    /**
     * The logged-in, active user or null. Null as well while the guest view
     * is on -- the request is then handled as for a visitor without a login.
     */
    public function user(): ?array
    {
        return $this->guestView() ? null : $this->account();
    }

    /** The actually signed-in, active user, regardless of the guest view. */
    public function account(): ?array
    {
        if ($this->user !== false) {
            return $this->user;
        }

        $id = (int) ($_SESSION['user_id'] ?? 0);
        if ($id <= 0) {
            return $this->user = null;
        }

        $user = $this->users->find($id);
        if ($user === null || (int) $user['active'] !== 1) {
            // Deleted or locked: the session loses its login.
            unset($_SESSION['user_id']);

            return $this->user = null;
        }

        return $this->user = $user;
    }

    public function isLoggedIn(): bool
    {
        return $this->user() !== null;
    }

    /** Is the signed-in user currently looking at the site as a guest? */
    public function guestView(): bool
    {
        return !empty($_SESSION['guest_view']) && $this->account() !== null;
    }

    /** Switch the guest view on or off; a no-op without a signed-in account. */
    public function setGuestView(bool $on): void
    {
        if ($on && $this->account() !== null) {
            $_SESSION['guest_view'] = true;
        } else {
            unset($_SESSION['guest_view']);
        }
    }

    public function username(): string
    {
        return (string) ($this->user()['username'] ?? '');
    }

    public function isAdmin(): bool
    {
        return (int) ($this->user()['is_admin'] ?? 0) === 1;
    }

    /** May the logged-in user access this area? The admin may do everything. */
    public function can(string $area): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }
        if ((int) $user['is_admin'] === 1) {
            return true;
        }

        return match ($area) {
            'wishes' => (int) $user['role_moderator'] === 1,
            'songs'  => (int) $user['role_editor'] === 1,
            'rooms'  => (int) $user['role_editor'] === 1,
            default  => false,
        };
    }

    public function login(string $username, string $password): bool
    {
        $user = $this->users->findByName($username);

        // Verify a hash even for an unknown name, so the response time does
        // not reveal whether the name exists.
        $hash = $user !== null ? (string) $user['password_hash'] : self::DUMMY_HASH;
        $ok   = password_verify($password, $hash);

        if (!$ok || $user === null || (int) $user['active'] !== 1) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['user_id']    = (int) $user['id'];
        $_SESSION['auth_since'] = time();
        unset($_SESSION['guest_view']);
        $this->user             = $user;
        $this->notePassword($password);

        return true;
    }

    /**
     * Remember in the session whether the password just entered or chosen is
     * the default one -- bcrypt is too slow to verify on every page view.
     */
    public function notePassword(string $password): void
    {
        $_SESSION['default_password'] = $password === self::DEFAULT_PASSWORD;
    }

    /**
     * Is the signed-in admin still using the default password? Sessions
     * opened before this check existed are verified once and remembered.
     */
    public function usesDefaultPassword(): bool
    {
        $user = $this->user();
        if ($user === null || (int) $user['is_admin'] !== 1) {
            return false;
        }
        if (!isset($_SESSION['default_password'])) {
            $_SESSION['default_password'] = password_verify(self::DEFAULT_PASSWORD, (string) $user['password_hash']);
        }

        return (bool) $_SESSION['default_password'];
    }

    public function logout(): void
    {
        $_SESSION   = [];
        $this->user = null;
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $params['path'],
                'secure'   => $params['secure'],
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
        session_destroy();
    }

    // ---- CSRF and wish cooldown ---------------------------------------------

    public function csrfToken(): string
    {
        if (!isset($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf'];
    }

    public function checkCsrf(?string $token): bool
    {
        return is_string($token)
            && isset($_SESSION['csrf'])
            && hash_equals((string) $_SESSION['csrf'], $token);
    }

    /** Simple per-session wish cooldown, without storing personal data. */
    public function throttled(int $cooldownSeconds): bool
    {
        $last = (int) ($_SESSION['last_wish'] ?? 0);

        return $cooldownSeconds > 0 && (time() - $last) < $cooldownSeconds;
    }

    public function markWish(): void
    {
        $_SESSION['last_wish'] = time();
    }
}
