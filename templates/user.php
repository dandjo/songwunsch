<?php

declare(strict_types=1);

use Songwunsch\Format;
use Songwunsch\UserRepository;

/** @var int $id                          0 = new user */
/** @var array<string,mixed>|null $user   existing record */
/** @var int $selfId                      id of the signed-in admin */
/** @var bool $onlyAdmin                  this user is the only active admin (then it is oneself): the role is fixed */
/** @var array<string,string> $values */
/** @var array<string,string> $errors */
/** @var string $csrf */
/** @var string $back  where Cancel and a save return to: the page the visitor came from, see destination() */

$e       = static fn (?string $v): string => Format::e($v);
$isNew   = $id === 0;
$isSelf  = $user !== null && (int) $user['id'] === $selfId;

$fieldError = static function (string $field) use ($errors, $e): string {
    if (!isset($errors[$field])) {
        return '';
    }

    return '<p class="field__error" id="err-' . $e($field) . '">' . $e($errors[$field]) . '</p>';
};

$invalid = static fn (string $field): string => isset($errors[$field])
    ? ' aria-invalid="true" aria-describedby="err-' . $field . '"'
    : '';

$checked = static fn (string $field): string => ($values[$field] ?? '') === '1' ? ' checked' : '';

// Admin includes editor and moderator: with the admin box ticked the other
// two are ticked and locked (app.js follows the box live; the server derives
// the roles anyway, so the locked boxes need no hidden fields).
$isAdmin = ($values['role_admin'] ?? '') === '1';

// Boxes nobody may change here: one's own status, and the admin role while
// one is the only active admin. Disabled boxes submit nothing -- a hidden
// field keeps the value.
?>

<div class="panel__head">
    <div>
        <h1><?= $e($isNew ? t('Add user') : t('Edit user')) ?></h1>
        <p class="muted">
            <?php if ($isNew): ?>
                <?= $e(t('Pass on username and password – the new user can log in right away.')) ?>
            <?php else: ?>
                <?= $e(t('Changes to roles and status take effect immediately, even for a running session.')) ?>
            <?php endif; ?>
        </p>
    </div>
</div>

<div class="login login--wide">
    <form method="post" action="<?= $e(url()) ?>" class="login__form">
        <input type="hidden" name="a" value="user_save">
        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
        <input type="hidden" name="id" value="<?= (int) $id ?>">
        <input type="hidden" name="back" value="<?= $e($back) ?>">

        <div class="field">
            <label for="username"><?= $e(t('Username')) ?></label>
            <input type="text" id="username" name="username" value="<?= $e($values['username'] ?? '') ?>"
                   autocomplete="off" required autofocus
                   minlength="<?= UserRepository::MIN_NAME ?>" maxlength="<?= UserRepository::MAX_NAME ?>"<?= $invalid('username') ?>>
            <?= $fieldError('username') ?>
        </div>

        <div class="field">
            <label for="password"><?= $e($isNew ? t('Password') : t('New password')) ?>
                <?php if (!$isNew): ?><span class="muted"><?= $e(t('(leave empty to keep it)')) ?></span><?php endif; ?>
            </label>
            <div class="password" data-reveal>
                <input type="password" id="password" name="password" autocomplete="new-password"
                       <?= $isNew ? 'required' : '' ?> minlength="<?= UserRepository::MIN_PASSWORD ?>"
                       aria-describedby="hint-password<?= isset($errors['password']) ? ' err-password' : '' ?>"
                       <?= isset($errors['password']) ? 'aria-invalid="true"' : '' ?>>
                <?= password_toggle() ?>
            </div>
            <p class="field__hint" id="hint-password"><?= $e(t('At least {n} characters.', ['n' => UserRepository::MIN_PASSWORD])) ?></p>
            <?= $fieldError('password') ?>
        </div>

        <div class="field">
            <label for="password2"><?= $e(t('Repeat password')) ?></label>
            <div class="password" data-reveal>
                <input type="password" id="password2" name="password2" autocomplete="new-password"
                       <?= $isNew ? 'required' : '' ?><?= $invalid('password2') ?>>
                <?= password_toggle() ?>
            </div>
            <?= $fieldError('password2') ?>
        </div>

        <fieldset class="field field--group"<?= isset($errors['role_admin']) ? ' aria-describedby="err-role_admin"' : '' ?>>
            <legend><?= $e(t('Roles')) ?></legend>
            <label class="check">
                <input type="checkbox" name="role_admin" value="1" data-implies="role_editor role_moderator"<?= $checked('role_admin') ?><?= $onlyAdmin ? ' disabled' : '' ?>>
                <span><strong><?= $e(t('Admin', [], 'role')) ?></strong> – <?= $e(t('manage users and roles, logos, colours, limits, pages, footer and languages; includes Editor and Moderator')) ?></span>
            </label>
            <?php if ($onlyAdmin): ?>
                <input type="hidden" name="role_admin" value="1">
                <p class="field__hint"><?= $e(t('The only active admin keeps the role – make another user admin first.')) ?></p>
            <?php endif; ?>
            <label class="check">
                <input type="checkbox" name="role_editor" value="1"<?= $isAdmin ? ' checked disabled' : $checked('role_editor') ?>>
                <span><strong><?= $e(t('Editor', [], 'role')) ?></strong> – <?= $e(t('maintain the repertoire, work the song suggestions, create rooms and manage their songs')) ?></span>
            </label>
            <label class="check">
                <input type="checkbox" name="role_moderator" value="1"<?= $isAdmin ? ' checked disabled' : $checked('role_moderator') ?>>
                <span><strong><?= $e(t('Moderator', [], 'role')) ?></strong> – <?= $e(t('edit the wish list, close and open rooms')) ?></span>
            </label>
            <?= $fieldError('role_admin') ?>
        </fieldset>

        <fieldset class="field field--group"<?= isset($errors['active']) ? ' aria-describedby="err-active"' : '' ?>>
            <legend><?= $e(t('Status')) ?></legend>
            <label class="check">
                <input type="checkbox" name="active" value="1"<?= $checked('active') ?><?= $isSelf ? ' disabled' : '' ?>>
                <span><strong><?= $e(t('Active')) ?></strong> – <?= $e(t('locked users cannot log in and are dropped from running sessions')) ?></span>
            </label>
            <?php if ($isSelf): ?>
                <input type="hidden" name="active" value="1">
                <p class="field__hint"><?= $e(t('You cannot lock yourself.')) ?></p>
            <?php endif; ?>
            <?= $fieldError('active') ?>
        </fieldset>

        <div class="panel__actions">
            <button type="submit" class="wish-button"><?= icon($isNew ? 'plus' : 'check') ?><?= $e($isNew ? t('Create') : t('Save')) ?></button>
            <a class="link-button" href="<?= $e($back) ?>"><?= icon('cross') ?><?= $e(t('Cancel')) ?></a>
        </div>
    </form>
</div>
