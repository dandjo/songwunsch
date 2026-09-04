<?php

declare(strict_types=1);

use Songwunsch\Format;
use Songwunsch\UserRepository;

/** @var int $id                          0 = new user */
/** @var array<string,mixed>|null $user   existing record */
/** @var int $selfId                      id of the logged-in admin */
/** @var array<string,string> $values */
/** @var array<string,string> $errors */
/** @var string $csrf */

$e       = static fn (?string $v): string => Format::e($v);
$isNew   = $id === 0;
$isAdmin = $user !== null && (int) $user['is_admin'] === 1;
$isSelf  = $user !== null && (int) $user['id'] === $selfId;
$backUrl = url(['p' => 'users']);

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
?>

<div class="panel__head">
    <div>
        <h1><?= $e($isNew ? t('Add user') : t('Edit user')) ?></h1>
        <p class="muted">
            <?php if ($isNew): ?>
                <?= $e(t('Pass on username and password – the new user can log in right away.')) ?>
            <?php elseif ($isAdmin): ?>
                <?= $e(t('The admin may do everything; their role boxes and status are therefore fixed. The admin role can be handed over to another user below.')) ?>
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

        <fieldset class="field field--group">
            <legend><?= $e(t('Roles')) ?></legend>
            <label class="check">
                <input type="checkbox" name="role_editor" value="1"<?= $checked('role_editor') ?><?= $isAdmin ? ' disabled' : '' ?>>
                <span><strong><?= $e(t('Editor', [], 'role')) ?></strong> – <?= $e(t('maintain the repertoire (add, edit, delete)')) ?></span>
            </label>
            <label class="check">
                <input type="checkbox" name="role_moderator" value="1"<?= $checked('role_moderator') ?><?= $isAdmin ? ' disabled' : '' ?>>
                <span><strong><?= $e(t('Moderator', [], 'role')) ?></strong> – <?= $e(t('edit the wish list, open and close the room')) ?></span>
            </label>
            <?php if ($isAdmin): ?>
                <p class="field__hint"><?= $e(t('As admin this user has every permission anyway.')) ?></p>
                <?php /* Disabled boxes submit nothing -- hidden fields keep the values. */ ?>
                <input type="hidden" name="role_editor" value="<?= $e($values['role_editor'] ?? '0') ?>">
                <input type="hidden" name="role_moderator" value="<?= $e($values['role_moderator'] ?? '0') ?>">
            <?php endif; ?>
        </fieldset>

        <fieldset class="field field--group">
            <legend><?= $e(t('Status')) ?></legend>
            <label class="check">
                <input type="checkbox" name="active" value="1"<?= $checked('active') ?><?= $isAdmin ? ' disabled' : '' ?>>
                <span><strong><?= $e(t('Active')) ?></strong> – <?= $e(t('locked users cannot log in and are dropped from running sessions')) ?></span>
            </label>
            <?php if ($isAdmin): ?>
                <input type="hidden" name="active" value="1">
            <?php endif; ?>
        </fieldset>

        <div class="panel__actions">
            <button type="submit" class="wish-button"><?= icon($isNew ? 'plus' : 'check') ?><?= $e($isNew ? t('Create') : t('Save')) ?></button>
            <a class="link-button" href="<?= $e($backUrl) ?>"><?= icon('cross') ?><?= $e(t('Cancel')) ?></a>
        </div>
    </form>

    <?php if (!$isNew && !$isSelf && !$isAdmin && (int) $user['active'] === 1): ?>
        <div class="panel__head transfer">
            <div>
                <h2><?= $e(t('Hand over admin role')) ?></h2>
                <p class="muted">
                    <?= t('Afterwards {name} manages the users and may do everything. You keep your other roles but lose access to this page – you only get it back if the new admin hands the role back.', [
                        'name' => '<strong>' . $e((string) $user['username']) . '</strong>',
                    ]) ?>
                </p>
            </div>
            <div class="panel__actions">
                <form method="post" action="<?= $e(url()) ?>"
                      data-confirm="<?= $e(t('Really hand the admin role over to “{name}”?', ['name' => (string) $user['username']])) ?>">
                    <input type="hidden" name="a" value="admin_transfer">
                    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                    <input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
                    <button type="submit" class="danger-button"><?= icon('key') ?><?= $e(t('Hand over admin role')) ?></button>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>
