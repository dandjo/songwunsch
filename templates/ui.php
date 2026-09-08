<?php

declare(strict_types=1);

use Songwunsch\Format;
use Songwunsch\Colors;
use Songwunsch\Theme;
use Songwunsch\Ui;

/**
 * Admin only: the interface -- the site's colours, which scheme a visitor
 * gets by default, how long a pop-up message stays, and how often the pages
 * ask for changes (one interval per case, 0 = no live update).
 *
 * The colours come as two sets of six, one per scheme: a base colour per
 * area of use, picked with the browser's colour picker or typed as #rrggbb
 * (both follow each other, app.js), and an empty field keeps the
 * stylesheet's built-in colour for that scheme. Which set a visitor sees is
 * their own choice, made with the switch in the header; the default below
 * only decides what someone gets who has not used it -- see src/Theme.php.
 *
 * @var array<string,string> $values  area => '#rrggbb' ('' = built-in colour) and field => number as text; what was typed after a failed save
 * @var array<string,string> $errors  area or field => message, after a failed save
 * @var string $csrf
 */

$e = static fn (?string $v): string => Format::e($v);

// Label and where the colour shows up, per area -- the same wording as docs/interface.md.
$areas = [
    'accent'     => [t('Accent'),     t('Buttons, links, the active tab and focus rings, “wunsch” in the word mark, the room name in the header, the gold tags and notices.')],
    'secondary'  => [t('Secondary'),  t('The genre and role tags, the counters on the tabs and the edge of the info notices.')],
    'danger'     => [t('Danger'),     t('Closed rooms, delete buttons, warnings and errors.')],
    'success'    => [t('Success'),    t('The edge of the confirmation notices and the confirm buttons in the dialogs of the page editor.')],
    'background' => [t('Background'), t('The page ground; shell, panels, fields and lines are steps away from it, and so is the text on gold buttons and counters.')],
    'text'       => [t('Text'),       t('The text; the muted text is a step towards the background.')],
];

// Label and explanation per number field, grouped as the form shows them.
$groups = [
    [t('Messages'), [
        'toast_sec' => [t('Seconds a message is shown'), t('The result of an action – a wish is in, a song was added, a row was deleted – pops up at the bottom edge and disappears after this many seconds; 0 keeps it until it is dismissed.')],
    ]],
    [t('Live updates'), [
        'poll_wishes_sec'      => [t('Wish list: seconds between two checks'),   t('Every open wish list asks the server this often whether a wish arrived, a row moved or was deleted, and redraws itself. 0 switches the live update off; the page is then current after a reload.')],
        'poll_suggestions_sec' => [t('Suggestions: seconds between two checks'), t('The same for the list of suggestions.')],
        'poll_room_sec'        => [t('Room state, rooms and songs: seconds between two checks'), t('Every page asks this often whether the room was closed or opened and whether rooms or songs were added, changed or removed, so the notice in the header, the room switcher, the repertoire and the list of rooms follow for everyone without a reload. 0 switches that off.')],
    ]],
];

$fieldError = static function (string $field) use ($errors, $e): string {
    if (!isset($errors[$field])) {
        return '';
    }

    return '<p class="field__error" id="err-' . $e($field) . '">' . $e($errors[$field]) . '</p>';
};

$describedBy = static fn (string $field): string => 'hint-' . $field . (isset($errors[$field]) ? ' err-' . $field : '');

// Which colour set has a field to look at, and therefore which tab is
// marked and which panel the tabs open on. Dark unless only light failed.
$schemeErrors = [];
foreach ([true => Theme::DARK, false => Theme::LIGHT] as $dark => $scheme) {
    $schemeErrors[$scheme] = false;
    foreach (Colors::AREAS as $area) {
        if (isset($errors[Colors::field($area, (bool) $dark)])) {
            $schemeErrors[$scheme] = true;
        }
    }
}
$colorTab = !$schemeErrors[Theme::DARK] && $schemeErrors[Theme::LIGHT] ? Theme::LIGHT : Theme::DARK;
?>

<div class="panel__head">
    <div>
        <h1><?= $e(t('Interface')) ?></h1>
        <?php ob_start(); ?>
        <p>
            <?= $e(t('The colours of the interface, how long a message stays and how often the lists look for changes – for every visitor and every room alike.')) ?>
        </p>
        <?php $help .= ob_get_clean(); ?>
    </div>
</div>

<div class="login login--wide">
    <form method="post" action="<?= $e(url('ui_save')) ?>" class="login__form">
        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">

        <?php /* One panel per scheme, six fields each, behind a row of tabs --
                 the same component the page editor uses for its languages and
                 the footer for its lines (app.js), because it is the same
                 shape: one form, one panel per version of the same fields.
                 Not the Logos page's kind of tabs: those navigate, and here a
                 navigation would throw away what is half typed in.

                 Without JavaScript both panels stand on the page, each headed
                 by its scheme -- which is what this looked like before the
                 tabs, so nothing is lost. The tabs start on the scheme whose
                 fields need a look after a failed save. */ ?>
        <div class="field field--group">
        <h2 class="field__legend"><?= $e(t('Colours')) ?></h2>
        <div class="tabbed tabbed--flush" data-tabs data-tabs-active="<?= $e($colorTab) ?>">
            <nav class="tabs" aria-label="<?= $e(t('Colours')) ?>">
                <ul role="list">
                    <?php foreach ([Theme::DARK => t('Dark'), Theme::LIGHT => t('Light')] as $scheme => $label): ?>
                        <?php $flawed = $schemeErrors[$scheme]; ?>
                        <li>
                            <a href="#colorset-<?= $e($scheme) ?>" class="tabs__item<?= $flawed ? ' has-error' : '' ?>" data-tab="<?= $e($scheme) ?>">
                                <?= $e($label) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </nav>
            <?php foreach ([[true, Theme::DARK, t('Colours – dark')], [false, Theme::LIGHT, t('Colours – light')]] as [$isDark, $scheme, $legend]): ?>
            <fieldset class="tabpanel" id="colorset-<?= $e($scheme) ?>" data-panel="<?= $e($scheme) ?>">
                <legend class="tabpanel__legend"><?= $e($legend) ?></legend>
                <p class="field__hint">
                    <?= $isDark
                        ? $e(t('The dark interface: gold for actions, violet for tags and counters, red for danger, green for success. Every area has one base colour; the shades and tints it needs – hover, frames, notices – are derived from it.'))
                        : $e(t('The light interface, for visitors who choose it. The same six areas, picked against the pale ground – a colour that shines on black is rarely readable on white, so these are their own values and not a translation of the ones above.')) ?>
                    <?= $e(t('Leave a field empty to keep the built-in colour. Keep the contrast to the background readable and check with the accessibility tools of the browser after a change – in the scheme the colour belongs to.')) ?>
                </p>
                <div class="field-pair">
                    <?php foreach (Colors::AREAS as $area): ?>
                        <?php [$label, $where] = $areas[$area]; ?>
                        <?php $field   = Colors::field($area, $isDark); ?>
                        <?php $default = Colors::defaults($isDark)[$area]; ?>
                        <?php $value   = (string) ($values[$field] ?? ''); ?>
                        <div class="field">
                            <label for="colors-<?= $e($field) ?>"><?= $e($label) ?></label>
                            <?php /* The picker and the "Default" button are rendered hidden and
                                     appear with JavaScript (app.js); without it the hex field
                                     stands alone and does the job. */ ?>
                            <div class="colour" data-colour>
                                <input type="color" value="<?= $e(Colors::parse($value) !== null ? $value : $default) ?>" hidden
                                       data-default="<?= $e($default) ?>"
                                       aria-label="<?= $e(t('Pick the colour: {area}', ['area' => $label])) ?>">
                                <input type="text" id="colors-<?= $e($field) ?>" name="<?= $e($field) ?>" value="<?= $e($value) ?>"
                                       placeholder="<?= $e($default) ?>" maxlength="7" autocomplete="off" spellcheck="false"
                                       pattern="#?([0-9a-fA-F]{3}|[0-9a-fA-F]{6})"
                                       aria-describedby="<?= $e($describedBy($field)) ?>"<?= isset($errors[$field]) ? ' aria-invalid="true"' : '' ?>>
                                <button type="button" class="colour__reset" hidden data-colour-reset>
                                    <?= $e(t('Default')) ?><span class="sr-only">: <?= $e($label) ?></span>
                                </button>
                            </div>
                            <p class="field__hint" id="hint-<?= $e($field) ?>">
                                <?= $e($where) ?>
                                <?= $e(t('Built-in: {colour}', ['colour' => $default])) ?>
                            </p>
                            <?= $fieldError($field) ?>
                        </div>
                    <?php endforeach; ?>
            </div>
        </fieldset>
        <?php endforeach; ?>
        </div>
        </div>

        <?php /* Which scheme someone gets who never used the switch. "Follow
                 my device" hands the decision to the browser's own setting
                 (prefers-color-scheme); the other two fix it. */ ?>
        <fieldset class="field field--group">
            <legend><?= $e(t('Default design')) ?></legend>
            <div class="field">
                <label for="ui-theme"><?= $e(t('Visitors who have not chosen see')) ?></label>
                <select id="ui-theme" name="theme" aria-describedby="hint-theme">
                    <?php foreach ([
                        Theme::DARK   => t('Dark'),
                        Theme::LIGHT  => t('Light'),
                        Theme::SYSTEM => t('Follow my device'),
                    ] as $value => $label): ?>
                        <option value="<?= $e($value) ?>"<?= ($values['theme'] ?? Theme::FALLBACK) === $value ? ' selected' : '' ?>><?= $e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="field__hint" id="hint-theme">
                    <?= $e(t('Everyone may switch for themselves in the header at any time; this is only the starting point. “Follow my device” takes the light or dark setting of the visitor\'s own system.')) ?>
                    <?= $e(t('Default: {n}.', ['n' => t('Dark')])) ?>
                </p>
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
