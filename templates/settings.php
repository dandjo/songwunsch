<?php

declare(strict_types=1);

use Songwunsch\Format;
use Songwunsch\Settings;

/** @var Settings $settings */
/** @var string $csrf */

$e = static fn (?string $v): string => Format::e($v);

// One row per kind of deletion: label and where the button lives.
$kinds = [
    'songs'  => [t('Confirm deleting songs'),  t('Delete in the song list')],
    'wishes' => [t('Confirm deleting wishes'), t('Delete in the wish list – Clear list always asks')],
    'rooms'  => [t('Confirm deleting rooms'),  t('Delete under Rooms – the room’s wishes go with it')],
];
?>

<div class="panel__head">
    <div>
        <h1><?= $e(t('Settings')) ?></h1>
        <p class="muted"><?= $e(t('Applies to everyone who may delete, not only to your own account.')) ?></p>
    </div>
</div>

<div class="login login--wide">
    <form method="post" action="<?= $e(url()) ?>" class="login__form">
        <input type="hidden" name="a" value="settings_save">
        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">

        <fieldset class="field field--group">
            <legend><?= $e(t('Delete confirmations')) ?></legend>
            <p class="field__hint"><?= $e(t('Ask before deleting – switch off what gets in the way of routine work.')) ?></p>
            <?php foreach ($kinds as $what => [$label, $where]): ?>
                <label class="check">
                    <input type="checkbox" name="confirm_<?= $e($what) ?>" value="1"<?= $settings->confirmsDelete($what) ? ' checked' : '' ?>>
                    <span><strong><?= $e($label) ?></strong> – <?= $e($where) ?></span>
                </label>
            <?php endforeach; ?>
        </fieldset>

        <div class="panel__actions">
            <button type="submit" class="wish-button"><?= icon('check') ?><?= $e(t('Save')) ?></button>
        </div>
    </form>
</div>
