<?php

declare(strict_types=1);

/**
 * The form for the visitor's name -- shown in a dialog on the first visit
 * (layout.php) and as its own page under /name (name.php). The name lands
 * in a cookie and is shown on the wish list next to every wish from this
 * browser.
 *
 * Expects:
 * @var string|null $guestName  the current name, null when none is set
 * @var string      $csrf
 * @var string      $nameBack   address to return to after saving
 * @var bool        $nameAsk    first visit: offer "Not now" instead of "Back"
 */

use Songwunsch\Format;
use Songwunsch\GuestName;

$e  = static fn (?string $v): string => Format::e($v);
$id = $nameAsk ? 'name-ask' : 'name';
?>
<form method="post" action="<?= $e(url()) ?>" class="namebox__form">
    <input type="hidden" name="a" value="name_save">
    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
    <input type="hidden" name="back" value="<?= $e($nameBack) ?>">

    <div class="field">
        <label for="<?= $id ?>"><?= $e(t('Your name')) ?></label>
        <input type="text" id="<?= $id ?>" name="name" value="<?= $e((string) $guestName) ?>"
               maxlength="<?= GuestName::MAX_LENGTH ?>" autocomplete="nickname" autocapitalize="words"
               placeholder="<?= $e(t('First name or nickname')) ?>" aria-describedby="<?= $id ?>-hint"<?= $nameAsk ? ' autofocus' : '' ?>>
        <p class="field__hint" id="<?= $id ?>-hint">
            <?= $e(t('The name you enter appears publicly on the wish list and among the suggestions, next to every song you wish for or suggest – anyone who opens this site can see it. If you change your name, wishes and suggestions already made keep the old one; only new wishes and suggestions get the new name. It is kept as a cookie in this browser for a year and can be changed any time in the account menu at the top right.')) ?>
            <?php if (!$nameAsk): ?>
                <?= $e(t('Leave the field empty to wish without a name.')) ?>
            <?php endif; ?>
        </p>
    </div>

    <div class="namebox__actions">
        <button type="submit" class="wish-button"><?= icon('check') ?><?= $e(t('Save name')) ?></button>
        <?php if (!$nameAsk): ?>
            <a class="link-button" href="<?= $e($nameBack) ?>"><?= icon('arrow-left') ?><?= $e(t('Cancel')) ?></a>
        <?php endif; ?>
    </div>
</form>
<?php if ($nameAsk): ?>
    <?php /* "Not now" is its own form: a different action, and the name field
             must not travel with it. app.js submits it when the dialog is
             dismissed with Escape. */ ?>
    <form method="post" action="<?= $e(url()) ?>" class="namebox__skip" data-name-skip>
        <input type="hidden" name="a" value="name_skip">
        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
        <input type="hidden" name="back" value="<?= $e($nameBack) ?>">
        <button type="submit" class="link-button"><?= icon('cross') ?><?= $e(t('Not now')) ?></button>
    </form>
<?php endif; ?>
