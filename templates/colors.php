<?php

declare(strict_types=1);

use Songwunsch\Format;
use Songwunsch\Theme;

/**
 * Admin only: the site's colours -- one base colour per area of use, picked
 * with the browser's colour picker or typed as #rrggbb; both follow each
 * other (app.js). An empty field keeps the stylesheet's built-in colour.
 *
 * @var array<string,string> $values  area => '#rrggbb', '' = built-in colour; what was typed after a failed save
 * @var array<string,string> $errors  area => message, after a failed save
 * @var string $csrf
 */

$e = static fn (?string $v): string => Format::e($v);

// Label and where the colour shows up, per area -- the same wording as the README.
$areas = [
    'accent'     => [t('Accent'),     t('Buttons, links, the active tab and focus rings, “wunsch” in the word mark, the room name in the header, the gold tags and notices.')],
    'secondary'  => [t('Secondary'),  t('The genre and role tags, the counters on the tabs and the edge of the info notices.')],
    'danger'     => [t('Danger'),     t('Closed rooms, delete buttons, warnings and errors.')],
    'success'    => [t('Success'),    t('The edge of the confirmation notices and the confirm buttons in the dialogs of the page editor.')],
    'background' => [t('Background'), t('The page ground; shell, panels, fields and lines are lightened steps of it, and so is the text on gold buttons and counters.')],
    'text'       => [t('Text'),       t('The text; the muted text is a step towards the background.')],
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
        <h1><?= $e(t('Colours')) ?></h1>
        <p class="muted">
            <?= $e(t('The interface is dark with gold for actions, violet for tags and counters, red for danger and green for success. Every area has one base colour; the shades and tints it needs – hover, frames, notices – are derived from it.')) ?>
            <?= $e(t('Leave a field empty to keep the built-in colour. Keep the contrast to the background readable and check with the accessibility tools of the browser after a change.')) ?>
        </p>
    </div>
</div>

<div class="login login--wide">
    <form method="post" action="<?= $e(url()) ?>" class="login__form">
        <input type="hidden" name="a" value="theme_save">
        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">

        <fieldset class="field field--group">
            <legend><?= $e(t('Colours')) ?></legend>
            <div class="field-pair">
                <?php foreach (Theme::AREAS as $area): ?>
                    <?php [$label, $where] = $areas[$area]; ?>
                    <?php $default = Theme::DEFAULTS[$area]; ?>
                    <?php $value = (string) ($values[$area] ?? ''); ?>
                    <div class="field">
                        <label for="theme-<?= $e($area) ?>"><?= $e($label) ?></label>
                        <?php /* The picker and the "Default" button are rendered hidden and
                                 appear with JavaScript (app.js); without it the hex field
                                 stands alone and does the job. */ ?>
                        <div class="colour" data-colour>
                            <input type="color" value="<?= $e(Theme::parse($value) !== null ? $value : $default) ?>" hidden
                                   data-default="<?= $e($default) ?>"
                                   aria-label="<?= $e(t('Pick the colour: {area}', ['area' => $label])) ?>">
                            <input type="text" id="theme-<?= $e($area) ?>" name="<?= $e($area) ?>" value="<?= $e($value) ?>"
                                   placeholder="<?= $e($default) ?>" maxlength="7" autocomplete="off" spellcheck="false"
                                   pattern="#?([0-9a-fA-F]{3}|[0-9a-fA-F]{6})"
                                   aria-describedby="<?= $e($describedBy($area)) ?>"<?= isset($errors[$area]) ? ' aria-invalid="true"' : '' ?>>
                            <button type="button" class="colour__reset" hidden data-colour-reset>
                                <?= $e(t('Default')) ?><span class="sr-only">: <?= $e($label) ?></span>
                            </button>
                        </div>
                        <p class="field__hint" id="hint-<?= $e($area) ?>">
                            <?= $e($where) ?>
                            <?= $e(t('Built-in: {colour}', ['colour' => $default])) ?>
                        </p>
                        <?= $fieldError($area) ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </fieldset>

        <div class="panel__actions">
            <button type="submit" class="wish-button"><?= icon('check') ?><?= $e(t('Save')) ?></button>
        </div>
    </form>
</div>
