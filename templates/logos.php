<?php

declare(strict_types=1);

use Songwunsch\Format;
use Songwunsch\Theme;
use Songwunsch\Uploads;

/**
 * Admin only: every logo ever uploaded, one of them (or none -- the word
 * mark) switched live for the header.
 *
 * There are two slots, because a logo drawn in pale lettering for the dark
 * ground vanishes on a white one: one logo for the dark design and, if the
 * operator has a second version, one for the light design. The light slot
 * may stay empty, and then both designs show the same.
 *
 * The page shows **one design at a time** ($design, `?design=light` for the
 * other). That is the whole reason it reads clearly: the design is named
 * once, at the top, in a sentence -- and each row below carries a single
 * "Switch live", the way it did while there was only one slot. Two buttons
 * per row named after the designs said nothing about what they would do.
 *
 * @var array<int,array{id:int,mime:string,width:?int,height:?int,size:int,created_at:string}> $logos  newest first, one page
 * @var int $total       all uploaded logos
 * @var int $pageNo
 * @var int $pages
 * @var int $activeId      id of the logo the dark design shows, 0 = word mark
 * @var int $lightId       the same for the light design, 0 = word mark -- only when it has an entry of its own
 * @var bool $lightFollows the light design has no entry: it shows whatever the dark one shows
 * @var string $design     the design being looked at, Theme::DARK or Theme::LIGHT
 * @var string $csrf
 */

$e = static fn (?string $v): string => Format::e($v);

$logoUrl = static fn (int $id): string => url('logo', ['id' => $id]);
$kb      = static fn (int $bytes): int => max(1, (int) round($bytes / 1024));

// Deliberately none of the names the layout uses: this template is
// required into the layout's scope (templates/layout.php), so a local of
// $here or $live here would overwrite the address and the poll tokens the
// header prints afterwards.
$onLight = $design === Theme::LIGHT;
// What this design shows: an id, or 0 for the word mark. A light design
// that follows the dark one has nothing of its own -- no row is marked
// then, and the sentence above the list says what it inherits.
$slotId  = $onLight ? $lightId : $activeId;
$inherits = $onLight && $lightFollows;
// Every address on this page keeps the design, so switching, deleting and
// paging all come back to the same context.
$pageUrl = static fn (int $page) => url('logos', [
    'page'   => $page > 1 ? $page : null,
    'design' => $design === Theme::LIGHT ? Theme::LIGHT : null,
]);
// This page of the list: where switching and deleting lead back to.
$current = $pageUrl($pageNo);
?>

<div class="panel__head">
    <div>
        <h1><?= $e(t('Logos')) ?></h1>
        <?php ob_start(); ?>
        <p><?= $e(t('A logo takes the place of the word mark “Songwunsch” and the claim at the top of every page; the room’s name keeps its spot. One logo is live – or none, then the word mark shows.')) ?></p>
        <p><?= $e(t('Pale lettering drawn for the dark ground disappears on a white one. If you have a second version, switch it live for the light design; without one both designs show the same logo.')) ?></p>
        <?php $help .= ob_get_clean(); ?>
    </div>
</div>

<div class="login login--wide">
    <form method="post" action="<?= $e(url('logo_upload')) ?>" enctype="multipart/form-data" class="login__form">
        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
        <?php /* The upload comes back to the design one uploaded from, and
                 "switch it live" means live in that design. */ ?>
        <input type="hidden" name="back" value="<?= $e($current) ?>">
        <input type="hidden" name="scheme" value="<?= $e($design) ?>">

        <fieldset class="field field--group">
            <legend><?= $e(t('Upload a logo')) ?></legend>
            <div class="field">
                <label for="logo"><?= $e(t('Logo file')) ?></label>
                <input type="file" id="logo" name="logo" accept="image/png,image/jpeg,image/webp,image/gif,image/svg+xml" required aria-describedby="hint-logo">
                <p class="field__hint" id="hint-logo">
                    <?= $e(t('The header shows the logo {h} pixels high. Any size works: on upload the image is scaled down to {target} pixels in height, which keeps it sharp on every screen; it should be at least that high itself. PNG or SVG with a transparent background look best; an SVG is kept as it is.', [
                        'h' => 48, 'target' => Uploads::TARGET_HEIGHT,
                    ])) ?>
                </p>
            </div>
            <label class="check">
                <input type="checkbox" name="activate" value="1" checked>
                <span><?= $e(t('Switch it live right away')) ?></span>
            </label>
        </fieldset>

        <div class="panel__actions">
            <button type="submit" class="wish-button"><?= icon('plus') ?><?= $e(t('Upload logo')) ?></button>
        </div>
    </form>
</div>

<div class="login login--wide">
    <div class="field field--group">
        <h2 class="field__legend"><?= $e(t('Uploaded logos')) ?></h2>

        <?php /* Which design the list below is about. Two plain links, not
                 tab panels: the whole list belongs to one design at a time,
                 so switching is a navigation and works without JavaScript
                 (app.js swaps it in, the address being the same path). */ ?>
        <nav class="tabs" aria-label="<?= $e(t('Which design?')) ?>">
            <ul>
                <?php foreach ([Theme::DARK => t('Dark design'), Theme::LIGHT => t('Light design')] as $value => $label): ?>
                    <li>
                        <a class="tabs__item" href="<?= $e(url('logos', ['design' => $value === Theme::LIGHT ? Theme::LIGHT : null])) ?>"<?= $value === $design ? ' aria-current="page"' : '' ?>>
                            <?= $e($label) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>

        <?php /* What this design shows right now, said in words -- the rows
                 below then need no more than "Switch live". The light design
                 gets its sentence from its slot: empty means it shows the
                 dark one's logo, and naming that logo saves a second tag in
                 the list. */ ?>
        <div class="logo-state">
            <?php if (!$onLight): ?>
                <p class="field__hint"><?= $e(t('The dark design – what a visitor sees unless they switch. Choose a logo below, or the word mark, and then no logo shows at all.')) ?></p>
            <?php elseif ($inherits): ?>
                <?php /* Nothing of its own: it shows what the dark design shows,
                         named here so no row has to carry a second marker. */ ?>
                <p class="field__hint"><?= $e($activeId > 0
                    ? t('The light design shows the same as the dark one – {logo} – and follows it when that changes. Choose below to give it something of its own.', [
                        'logo' => t('Logo {id}', ['id' => $activeId]),
                    ])
                    : t('The light design shows the same as the dark one – the word mark – and follows it when that changes. Choose below to give it something of its own.')) ?></p>
            <?php else: ?>
                <p class="field__hint"><?= $e($slotId > 0
                    ? t('The light design shows a logo of its own; a change to the dark design leaves it alone.')
                    : t('The light design shows the word mark, whatever the dark design shows.')) ?></p>
                <?php /* Back to following the dark design: its own address,
                         because it removes the entry rather than setting it. */ ?>
                <form method="post" action="<?= $e(url('logo_light_inherit')) ?>">
                    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                    <input type="hidden" name="back" value="<?= $e($current) ?>">
                    <button type="submit" class="link-button"><?= icon('cross') ?><?= $e(t('Same as dark')) ?></button>
                </form>
            <?php endif; ?>
        </div>

        <ul class="logo-list" role="list">
            <?php /* The word mark heads the list as the choice "no logo" -- on the
                     first page, and in either design: a logo drawn for the dark
                     ground may be better left off the light one than shown pale
                     on white. */ ?>
            <?php if ($pageNo === 1): ?>
            <li class="logo-card<?= !$inherits && $slotId === 0 ? ' logo-card--active' : '' ?>">
                <div class="logo-card__preview"><span class="dome__brand">Song<span>wunsch</span></span></div>
                <div class="logo-card__meta">
                    <strong><?= $e(t('Word mark')) ?></strong>
                    <span class="muted"><?= $e(t('The default without a logo, with the claim below.')) ?></span>
                </div>
                <div class="logo-card__actions">
                    <?php if (!$inherits && $slotId === 0): ?>
                        <span class="tag tag--gold"><?= $e(t('live')) ?></span>
                    <?php else: ?>
                        <form method="post" action="<?= $e(url('logo_activate', ['id' => 0])) ?>">
                            <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                            <input type="hidden" name="back" value="<?= $e($current) ?>">
                            <input type="hidden" name="scheme" value="<?= $e($design) ?>">
                            <button type="submit" class="link-button"><?= icon('check') ?><?= $e(t('Switch live')) ?></button>
                        </form>
                    <?php endif; ?>
                </div>
            </li>
            <?php endif; ?>

            <?php foreach ($logos as $upload): ?>
                <?php
                $id   = (int) $upload['id'];
                // Live in the design on show. While the light slot is empty
                // the dark logo stands there too, but the sentence above
                // says so -- the list marks the slot, not the effect.
                $isLive = !$inherits && $id === $slotId;
                ?>
                <li class="logo-card<?= $isLive ? ' logo-card--active' : '' ?>">
                    <div class="logo-card__preview">
                        <img src="<?= $e($logoUrl($upload['id'])) ?>" alt="<?= $e(t('Logo {id}', ['id' => $upload['id']])) ?>">
                    </div>
                    <div class="logo-card__meta">
                        <strong><?= $e(t('Logo {id}', ['id' => $upload['id']])) ?></strong>
                        <span class="muted">
                            <?= $e($upload['width'] !== null
                                ? t('{w} × {h} pixels, {kb} KB', ['w' => $upload['width'], 'h' => $upload['height'], 'kb' => $kb($upload['size'])])
                                : t('SVG, {kb} KB', ['kb' => $kb($upload['size'])])) ?>
                            · <?= $e(t('uploaded {when}', ['when' => Format::moment($upload['created_at'])])) ?>
                        </span>
                    </div>
                    <div class="logo-card__actions">
                        <?php /* One action, because the design is named above the
                                 list and not on every button. */ ?>
                        <?php if ($isLive): ?>
                            <span class="tag tag--gold"><?= $e(t('live')) ?></span>
                        <?php else: ?>
                            <form method="post" action="<?= $e(url('logo_activate', ['id' => $id])) ?>">
                                <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                                <input type="hidden" name="back" value="<?= $e($current) ?>">
                                <input type="hidden" name="scheme" value="<?= $e($design) ?>">
                                <button type="submit" class="link-button"><?= icon('check') ?><?= $e(t('Switch live')) ?></button>
                            </form>
                        <?php endif; ?>
                        <form method="post" action="<?= $e(url('logo_delete', ['id' => $id])) ?>" data-confirm="<?= $e($id === $activeId || $id === $lightId ? t('Delete the live logo? The header falls back to what it showed before.') : t('Delete this logo?')) ?>">
                            <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                            <input type="hidden" name="back" value="<?= $e($current) ?>">
                            <?php /* Icon only, like the delete buttons in every list -- the label stays for screen readers and as tooltip. */ ?>
                            <button type="submit" class="delete-button icon-button" title="<?= $e(t('Delete')) ?>">
                                <?= icon('trash') ?>
                                <span class="button__label"><?= $e(t('Delete')) ?></span>
                            </button>
                        </form>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if ($total === 0): ?>
            <p class="field__hint"><?= $e(t('No logo uploaded yet.')) ?></p>
        <?php endif; ?>
        <?php require __DIR__ . '/_pager.php'; ?>
    </div>
</div>
