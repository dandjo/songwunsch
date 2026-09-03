<?php

declare(strict_types=1);

use Songwunsch\Format;
use Songwunsch\Settings;

/** @var Settings $settings */
/** @var int $selfId          the signed-in user */
/** @var list<string> $kinds  what this user may delete, see deletable_kinds() */
/** @var string $csrf */

$e = static fn (?string $v): string => Format::e($v);

// One row per kind of deletion: label and where the button lives. Only the
// kinds the user's roles may delete are shown.
$rows = array_intersect_key([
    'songs'  => [t('Confirm deleting songs'),  t('Delete in the repertoire')],
    'suggestions' => [t('Confirm deleting suggestions'), t('Delete under Suggestions – Clear list always asks')],
    'wishes' => [t('Confirm deleting wishes'), t('Delete in the wish list – Clear list always asks')],
    'rooms'  => [t('Confirm deleting rooms'),  t('Delete under Rooms – the room’s wishes go with it')],
], array_flip($kinds));
?>

<div class="panel__head">
    <div>
        <h1><?= $e(t('Settings')) ?></h1>
        <p class="muted"><?= $e(t('Personal – these settings apply to your own account only.')) ?></p>
    </div>
</div>

<?php if ($rows === []): ?>
    <p class="muted"><?= $e(t('Your account has no role that may delete anything, so there is nothing to set here.')) ?></p>
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
