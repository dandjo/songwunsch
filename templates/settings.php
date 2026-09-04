<?php

declare(strict_types=1);

use Songwunsch\Format;
use Songwunsch\Settings;

/** @var Settings $settings */
/** @var int $selfId          the signed-in user */
/** @var list<string> $kinds  what this user may delete, see deletable_kinds() */
/** @var bool $isAdmin         the admin's roles are not assigned by anyone */
/** @var array<string,mixed> $account  the signed-in user's record */
/** @var array<string,string> $errors  from the password form, after a redirect */
/** @var string $csrf */

use Songwunsch\UserRepository;

$e = static fn (?string $v): string => Format::e($v);

$fieldError = static function (string $field) use ($errors, $e): string {
    if (!isset($errors[$field])) {
        return '';
    }

    return '<p class="field__error" id="err-' . $e($field) . '">' . $e($errors[$field]) . '</p>';
};

$invalid = static fn (string $field): string => isset($errors[$field])
    ? ' aria-invalid="true" aria-describedby="err-' . $field . '"'
    : '';

// One row per kind of deletion: label and where the button lives. Only the
// kinds the user's roles may delete are shown.
$rows = array_intersect_key([
    'songs'  => [t('Confirm deleting songs'),  t('Delete in the repertoire')],
    'suggestions' => [t('Confirm deleting suggestions'), t('Delete under Suggestions – Clear list always asks')],
    'wishes' => [t('Confirm deleting wishes'), t('Delete in the wish list – Clear list always asks')],
    'rooms'  => [t('Confirm deleting rooms'),  t('Delete under Rooms – the room’s wishes go with it')],
], array_flip($kinds));

// What each role may do, for the account box -- the same wording as the README.
$roleLabels = UserRepository::roleLabels($account);
$roleNotes  = [
    t('Admin', [], 'role')     => t('User management, plus everything editors and moderators may do.'),
    t('Editor', [], 'role')    => t('Repertoire, song suggestions and rooms.'),
    t('Moderator', [], 'role') => t('Wish list: sort, delete, clear; close and open rooms.'),
];
?>

<div class="panel__head">
    <div>
        <h1><?= $e(t('User settings')) ?></h1>
        <p class="muted"><?= $e(t('Personal – these settings apply to your own account only.')) ?></p>
    </div>
</div>

<div class="login login--wide">
    <div class="field field--group">
        <h2 class="field__legend"><?= $e(t('Your account')) ?></h2>
        <p>
            <strong><?= $e((string) $account['username']) ?></strong>
            <?php if ($roleLabels === []): ?>
                <span class="muted">– <?= $e(t('no role')) ?></span>
            <?php else: ?>
                <?php foreach ($roleLabels as $i => $label): ?>
                    <span class="tag<?= $isAdmin && $i === 0 ? ' tag--gold' : '' ?>"><?= $e($label) ?></span>
                <?php endforeach; ?>
            <?php endif; ?>
        </p>
        <?php if ($roleLabels === []): ?>
            <p class="field__hint"><?= $e(t('Without a role you can sign in and see the public repertoire; roles are assigned by the admin.')) ?></p>
        <?php else: ?>
            <ul class="field__hint">
                <?php foreach ($roleLabels as $label): ?>
                    <li><strong><?= $e($label) ?></strong> – <?= $e($roleNotes[$label] ?? '') ?></li>
                <?php endforeach; ?>
            </ul>
            <?php if (!$isAdmin): ?>
                <p class="field__hint"><?= $e(t('Roles are assigned by the admin.')) ?></p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<div class="login login--wide">
    <form method="post" action="<?= $e(url()) ?>" class="login__form">
        <input type="hidden" name="a" value="password_save">
        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">

        <fieldset class="field field--group">
            <legend><?= $e(t('Change password')) ?></legend>
            <p class="field__hint"><?= $e(t('Enter your current password once and the new one twice. The new password applies from the next sign-in; this session stays.')) ?></p>

            <div class="field">
                <label for="current_password"><?= $e(t('Current password')) ?></label>
                <div class="password" data-reveal>
                    <input type="password" id="current_password" name="current_password" autocomplete="current-password" required<?= $invalid('current_password') ?>>
                    <?= password_toggle() ?>
                </div>
                <?= $fieldError('current_password') ?>
            </div>

            <div class="field">
                <label for="password"><?= $e(t('New password')) ?></label>
                <div class="password" data-reveal>
                    <input type="password" id="password" name="password" autocomplete="new-password" required
                           minlength="<?= UserRepository::MIN_PASSWORD ?>"
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
                    <input type="password" id="password2" name="password2" autocomplete="new-password" required<?= $invalid('password2') ?>>
                    <?= password_toggle() ?>
                </div>
                <?= $fieldError('password2') ?>
            </div>
        </fieldset>

        <div class="panel__actions">
            <button type="submit" class="wish-button"><?= icon('key') ?><?= $e(t('Change password')) ?></button>
        </div>
    </form>
</div>


<?php if ($rows === []): ?>
    <p class="muted"><?= $e(t('Your account has no role that may delete anything, so there is nothing else to set here.')) ?></p>
<?php else: ?>

<div class="login login--wide">
    <form method="post" action="<?= $e(url()) ?>" class="login__form">
        <input type="hidden" name="a" value="settings_save">
        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">

        <fieldset class="field field--group">
            <legend><?= $e(t('Delete confirmations')) ?></legend>
            <p class="field__hint"><?= $e(t('Ask before deleting – switch off what gets in the way of routine work.')) ?></p>
            <?php foreach ($rows as $what => [$label, $where]): ?>
                <label class="check">
                    <input type="checkbox" name="confirm_<?= $e($what) ?>" value="1"<?= $settings->confirmsDelete($selfId, $what) ? ' checked' : '' ?>>
                    <span><strong><?= $e($label) ?></strong> – <?= $e($where) ?></span>
                </label>
            <?php endforeach; ?>
        </fieldset>

        <div class="panel__actions">
            <button type="submit" class="wish-button"><?= icon('check') ?><?= $e(t('Save')) ?></button>
        </div>
    </form>
</div>
<?php endif; ?>
