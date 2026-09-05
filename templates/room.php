<?php

declare(strict_types=1);

use Songwunsch\Format;
use Songwunsch\RoomRepository;

/** @var int $id                    0 = new room */
/** @var bool $main                  rename the main room: only its name, no address, no status */
/** @var array<string,string> $values */
/** @var array<string,string> $errors */
/** @var int $startRoomId   for the switches beside the title, see _room_switches.php */
/** @var bool $roomClosed   wishing closed in this room */
/** @var bool $roomActive   the stored state, not the form's input */
/** @var string $roomSlug   the stored machine name, for the QR code link ('' = main room) */
/** @var string $csrf */

$e       = static fn (?string $v): string => Format::e($v);
$isNew   = $id === 0;
$backUrl = url(['p' => 'rooms']);

$fieldError = static function (string $field) use ($errors, $e): string {
    if (!isset($errors[$field])) {
        return '';
    }

    return '<p class="field__error" id="err-' . $e($field) . '">' . $e($errors[$field]) . '</p>';
};

$invalid = static fn (string $field): string => isset($errors[$field])
    ? ' aria-invalid="true" aria-describedby="err-' . $field . ' hint-' . $field . '"'
    : ' aria-describedby="hint-' . $field . '"';
?>

<div class="panel__head">
    <div>
        <h1><?= $e($main ? t('Rename “General”') : ($isNew ? t('Add room') : t('Edit room'))) ?></h1>
        <p class="muted">
            <?php if ($main): ?>
                <?= $e(t('“General” is always there and lives at the root address; only its name can change. Leave the field empty for the default name.')) ?>
            <?php elseif ($isNew): ?>
                <?= $e(t('A room gets its own address, its own repertoire picked from the master list and its own wish list.')) ?>
            <?php else: ?>
                <?= $e(t('Changing the machine name changes the address – links already handed out stop working.')) ?>
            <?php endif; ?>
        </p>
    </div>
    <?php if (!$isNew || $main): ?>
        <?php /* The room's switches, as on its own pages: start room, close/open. */ ?>
        <div class="panel__actions">
            <?php
            $switchId     = (int) $id;
            $switchActive = $roomActive;
            $switchClosed = $roomClosed;
            $switchBack   = $main ? url(['p' => 'room', 'main' => 1]) : url(['p' => 'room', 'id' => $id]);
            require __DIR__ . '/_room_switches.php';
            ?>
            <a class="link-button" href="<?= $e(url(['p' => 'room_qr', 'room' => $roomSlug])) ?>"><?= icon('qr') ?><?= $e(t('QR code')) ?></a>
        </div>
    <?php endif; ?>
</div>

<div class="login login--wide">
    <form method="post" action="<?= $e(url()) ?>" class="login__form">
        <input type="hidden" name="a" value="<?= $main ? 'main_room_save' : 'room_save' ?>">
        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
        <input type="hidden" name="id" value="<?= (int) $id ?>">

        <div class="field">
            <label for="name"><?= $e(t('Name')) ?></label>
            <input type="text" id="name" name="name" value="<?= $e($values['name'] ?? '') ?>"
                   <?= $main ? 'placeholder="' . $e(t('General')) . '"' : 'required' ?> autofocus maxlength="<?= RoomRepository::MAX_NAME ?>"<?= $invalid('name') ?>>
            <p class="field__hint" id="hint-name"><?= $e($main
                ? t('Shown in the header, the room switcher and the list of rooms wherever “General” is meant.')
                : t('Shown in the header and the list of rooms, e.g. “Summer party 2026”.')) ?></p>
            <?= $fieldError('name') ?>
        </div>

        <?php if (!$main): ?>
        <div class="field">
            <label for="slug"><?= $e(t('Machine name')) ?></label>
            <input type="text" id="slug" name="slug" value="<?= $e($values['slug'] ?? '') ?>"
                   required autocomplete="off" autocapitalize="none" spellcheck="false"
                   minlength="<?= RoomRepository::MIN_SLUG ?>" maxlength="<?= RoomRepository::MAX_SLUG ?>"
                   pattern="[a-z0-9]+(-[a-z0-9]+)*"<?= $invalid('slug') ?>>
            <?php /* The address follows what is typed (app.js); until something is
                     typed an example stands in. */ ?>
            <p class="field__hint" id="hint-slug">
                <?= $e(t('Part of the address: lower-case letters a–z, digits and hyphens.')) ?>
                <code data-slug-preview="slug" data-slug-base="<?= $e(substr(url(['p' => 'songs', 'room' => 'x']), 0, -1)) ?>" data-slug-example="sommerfest-2026"><?= $e(url(['p' => 'songs', 'room' => ($values['slug'] ?? '') !== '' ? $values['slug'] : 'sommerfest-2026'])) ?></code>
            </p>
            <?= $fieldError('slug') ?>
        </div>

        <fieldset class="field field--group">
            <legend><?= $e(t('Status')) ?></legend>
            <label class="check">
                <input type="checkbox" name="active" value="1"<?= ($values['active'] ?? '1') === '1' ? ' checked' : '' ?>>
                <span><strong><?= $e(t('Active')) ?></strong> – <?= $e(t('archived rooms leave the room switcher and the list; only signed-in users can still open them, guests land on the start page')) ?></span>
            </label>
            <label class="check">
                <input type="checkbox" name="listed" value="1"<?= ($values['listed'] ?? '0') === '1' ? ' checked' : '' ?>>
                <span><strong><?= $e(t('Listed')) ?></strong> – <?= $e(t('guests see the room in the room switcher and the list of rooms; an unlisted room is reached through its address or QR code only – keep private events unlisted, above all when the name says whose event it is')) ?></span>
            </label>
        </fieldset>
        <?php endif; ?>

        <div class="panel__actions">
            <button type="submit" class="wish-button"><?= icon($isNew && !$main ? 'plus' : 'check') ?><?= $e($isNew && !$main ? t('Create') : t('Save')) ?></button>
            <a class="link-button" href="<?= $e($backUrl) ?>"><?= icon('cross') ?><?= $e(t('Cancel')) ?></a>
        </div>
    </form>
</div>
