<?php

declare(strict_types=1);

use Songwunsch\Format;
use Songwunsch\Colors;
use Songwunsch\Ui;

/**
 * Admin only: the interface -- the site's colours (one base colour per
 * area of use, picked with the browser's colour picker or typed as #rrggbb;
 * both follow each other, app.js; an empty field keeps the stylesheet's
 * built-in colour), how long a pop-up message stays, and how often the pages
 * ask for changes (one interval per case, 0 = no live update).
 *
 * @var array<string,string> $values  area => '#rrggbb' ('' = built-in colour) and field => number as text; what was typed after a failed save
 * @var array<string,string> $errors  area or field => message, after a failed save
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

// Label and explanation per number field, grouped as the form shows them.
$groups = [
    [t('Messages'), [
        'toast_sec' => [t('Seconds a message is shown'), t('The result of an action – a wish is in, a song was added, a row was deleted – pops up at the bottom edge and disappears after this many seconds; 0 keeps it until it is dismissed. Error messages always stay until dismissed.')],
    ]],
    [t('Live updates'), [
        'poll_wishes_sec'      => [t('Wish list: seconds between two checks'),   t('Every open wish list asks the server this often whether a wish arrived, a row moved or was deleted, and redraws itself. 0 switches the live update off; the page is then current after a reload.')],
        'poll_suggestions_sec' => [t('Suggestions: seconds between two checks'), t('The same for the list of suggestions.')],
        'poll_room_sec'        => [t('Room state: seconds between two checks'),  t('The song list asks this often whether the room was closed or opened, so the Wish buttons disappear and reappear for everyone without a reload. 0 switches that off.')],
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
        <div class="panel__title">
            <h1><?= $e(t('Interface')) ?></h1>
            <?= help_button('help-ui') ?>
        </div>
        <p class="muted help" id="help-ui">
            <?= $e(t('The colours of the interface, how long a message stays and how often the lists look for changes – for every visitor and every room alike.')) ?>
        </p>
    </div>
</div>

<div class="login login--wide">
    <form method="post" action="<?= $e(url()) ?>" class="login__form">
        <input type="hidden" name="a" value="ui_save">
        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">

        <fieldset class="field field--group">
            <legend><?= $e(t('Colours')) ?></legend>
            <p class="field__hint">
                <?= $e(t('The interface is dark with gold for actions, violet for tags and counters, red for danger and green for success. Every area has one base colour; the shades and tints it needs – hover, frames, notices – are derived from it.')) ?>
                <?= $e(t('Leave a field empty to keep the built-in colour. Keep the contrast to the background readable and check with the accessibility tools of the browser after a change.')) ?>
            </p>
            <div class="field-pair">
                <?php foreach (Colors::AREAS as $area): ?>
                    <?php [$label, $where] = $areas[$area]; ?>
                    <?php $default = Colors::DEFAULTS[$area]; ?>
                    <?php $value = (string) ($values[$area] ?? ''); ?>
                    <div class="field">
                        <label for="colors-<?= $e($area) ?>"><?= $e($label) ?></label>
                        <?php /* The picker and the "Default" button are rendered hidden and
                                 appear with JavaScript (app.js); without it the hex field
                                 stands alone and does the job. */ ?>
                        <div class="colour" data-colour>
                            <input type="color" value="<?= $e(Colors::parse($value) !== null ? $value : $default) ?>" hidden
                                   data-default="<?= $e($default) ?>"
                                   aria-label="<?= $e(t('Pick the colour: {area}', ['area' => $label])) ?>">
                            <input type="text" id="colors-<?= $e($area) ?>" name="<?= $e($area) ?>" value="<?= $e($value) ?>"
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

        <?php foreach ($groups as [$legend, $fields]): ?>
            <fieldset class="field field--group">
                <legend><?= $e($legend) ?></legend>
                <div class="field-pair">
                    <?php foreach ($fields as $name => [$label, $hint]): ?>
                        <?php [$default, $min, $max] = Ui::FIELDS[$name]; ?>
                        <div class="field">
                            <label for="ui-<?= $e($name) ?>"><?= $e($label) ?></label>
                            <input type="number" id="ui-<?= $e($name) ?>" name="<?= $e($name) ?>" value="<?= $e((string) ($values[$name] ?? '')) ?>"
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
            </fieldset>
        <?php endforeach; ?>

        <div class="panel__actions">
            <button type="submit" class="wish-button"><?= icon('check') ?><?= $e(t('Save')) ?></button>
        </div>
    </form>
</div>
