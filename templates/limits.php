<?php

declare(strict_types=1);

use Songwunsch\Format;
use Songwunsch\Limits;

/**
 * Admin only: the limits on wishing and suggesting and the page size of the
 * lists, for every room alike. Numbers with 0 = off; one switch for duplicates.
 *
 * @var array<string,string> $values  field => value as text; what was typed after a failed save
 * @var array<string,string> $errors  field => message, after a failed save
 * @var string $csrf
 */

$e = static fn (?string $v): string => Format::e($v);

// Label and explanation per number field, grouped as the form shows them.
$groups = [
    [t('Wishing'), [
        'max_open'          => [t('Open wishes per room'),           t('The room’s wish list is full at this many open wishes; guests are asked to try again later.')],
        'per_minute_total'  => [t('Wishes per minute, everyone'),     t('Across all visitors of the site. Bounds the damage whatever the source.')],
        'per_minute_sender' => [t('Wishes per minute per sender'),    t('Senders are told apart by a daily pseudonym of their address, never by a stored IP.')],
        'per_hour_sender'   => [t('Wishes per hour per sender'),      t('The slower brake for the same sender.')],
        'wish_cooldown_sec' => [t('Seconds between two wishes'),      t('In the same browser session – a brake against double clicks.')],
        'wish_min_form_sec' => [t('Seconds after the page load'),     t('A wish or suggestion sent sooner than this after the page was loaded is rejected – scripts are that fast, people are not.')],
    ]],
    [t('Suggesting'), [
        'suggestion_max_open'     => [t('Open suggestions'),                t('For the whole site; the suggestions aim at the master list. The box is full at this many open suggestions.')],
        'suggestion_cooldown_sec' => [t('Seconds between two suggestions'), t('In the same browser session.')],
    ]],
    [t('Lists'), [
        'per_page' => [t('Rows per page'), t('Repertoire, rooms, the room’s song picker and the suggestions show this many rows before the pager takes over.')],
    ]],
];

$fieldError = static function (string $field) use ($errors, $e): string {
    if (!isset($errors[$field])) {
        return '';
    }

    return '<p class="field__error" id="err-' . $e($field) . '">' . $e($errors[$field]) . '</p>';
};

$describedBy = static fn (string $field): string => 'hint-' . $field . (isset($errors[$field]) ? ' err-' . $field : '');
?>

<div class="panel__head">
    <div>
        <h1><?= $e(t('Limits')) ?></h1>
        <p class="muted">
            <?= $e(t('Wishing and suggesting are public and need no sign-in – these limits keep scripts and over-eager guests from flooding the lists. They apply to every room alike; 0 switches a limit off.')) ?>
            <?= $e(t('The hidden field in the forms that only scripts fill stays in place regardless.')) ?>
        </p>
    </div>
</div>

<div class="login login--wide">
    <form method="post" action="<?= $e(url()) ?>" class="login__form">
        <input type="hidden" name="a" value="limits_save">
        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">

        <?php foreach ($groups as [$legend, $fields]): ?>
            <fieldset class="field field--group">
                <legend><?= $e($legend) ?></legend>
                <div class="field-pair">
                    <?php foreach ($fields as $name => [$label, $hint]): ?>
                        <?php [$default, $min, $max] = Limits::FIELDS[$name]; ?>
                        <div class="field">
                            <label for="limit-<?= $e($name) ?>"><?= $e($label) ?></label>
                            <input type="number" id="limit-<?= $e($name) ?>" name="<?= $e($name) ?>" value="<?= $e((string) ($values[$name] ?? '')) ?>"
                                   min="<?= $min ?>" max="<?= $max ?>" step="1" inputmode="numeric" required
                                   aria-describedby="<?= $e($describedBy($name)) ?>"<?= isset($errors[$name]) ? ' aria-invalid="true"' : '' ?>>
                            <p class="field__hint" id="hint-<?= $e($name) ?>">
                                <?= $e($hint) ?>
                                <?= $e(t('Default: {n}.', ['n' => $default])) ?>
                            </p>
                            <?= $fieldError($name) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if ($legend === $groups[0][0]): ?>
                    <label class="check">
                        <input type="checkbox" name="allow_duplicates" value="1"<?= ($values['allow_duplicates'] ?? '0') === '1' ? ' checked' : '' ?>>
                        <span><strong><?= $e(t('Allow duplicates')) ?></strong> – <?= $e(t('a song may be wished again while it is still open on the list; otherwise the guest is told it is already there')) ?></span>
                    </label>
                <?php endif; ?>
            </fieldset>
        <?php endforeach; ?>

        <div class="panel__actions">
            <button type="submit" class="wish-button"><?= icon('check') ?><?= $e(t('Save')) ?></button>
        </div>
    </form>
</div>
