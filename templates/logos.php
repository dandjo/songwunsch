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
 * @var array<int,array{id:int,mime:string,width:?int,height:?int,size:int,created_at:string}> $logos  newest first, one page
 * @var int $total       all uploaded logos
 * @var int $pageNo
 * @var int $pages
 * @var int $activeId    id of the logo the dark design shows, 0 = word mark
 * @var int $lightId     id of the logo the light design shows, 0 = the same as the dark one
 * @var string $csrf
 */

$e = static fn (?string $v): string => Format::e($v);

$logoUrl = static fn (int $id): string => url('logo', ['id' => $id]);
// Where a logo is live. While the light slot is empty the dark design's
// logo stands in both, so it carries one tag and not two.
$liveInDark  = static fn (int $id): bool => $id === $activeId;
$liveInLight = static fn (int $id): bool => $lightId > 0 ? $id === $lightId : $id === $activeId;
// The light slot only means something while a logo is live at all; with the
// word mark up front there is nothing for a second version to differ from.
$twoSlots = $activeId > 0;
$kb      = static fn (int $bytes): int => max(1, (int) round($bytes / 1024));
$pageUrl = static fn (int $page): string => url('logos', ['page' => $page > 1 ? $page : null]);
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

        <ul class="logo-list" role="list">
            <?php /* The word mark heads the list as the choice "no logo" -- on the first page. */ ?>
            <?php if ($pageNo === 1): ?>
            <li class="logo-card<?= $activeId === 0 ? ' logo-card--active' : '' ?>">
                <div class="logo-card__preview"><span class="dome__brand">Song<span>wunsch</span></span></div>
                <div class="logo-card__meta">
                    <strong><?= $e(t('Word mark')) ?></strong>
                    <span class="muted"><?= $e(t('The default without a logo, with the claim below.')) ?></span>
                </div>
                <div class="logo-card__actions">
                    <?php /* The word mark is one choice, not one per design: without
                             a logo there is nothing a second version could differ
                             from, and switching to it clears the light slot. */ ?>
                    <?php if ($activeId === 0): ?>
                        <span class="tag tag--gold"><?= $e(t('live')) ?></span>
                    <?php else: ?>
                        <form method="post" action="<?= $e(url('logo_activate', ['id' => 0])) ?>">
                            <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                            <input type="hidden" name="back" value="<?= $e($current) ?>">
                            <input type="hidden" name="scheme" value="<?= $e(Theme::DARK) ?>">
                            <button type="submit" class="link-button"><?= icon('check') ?><?= $e(t('Switch live')) ?></button>
                        </form>
                    <?php endif; ?>
                </div>
            </li>
            <?php endif; ?>

            <?php foreach ($logos as $upload): ?>
                <?php
                $id      = (int) $upload['id'];
                $inDark  = $liveInDark($id);
                $inLight = $liveInLight($id);
                $slotUrl = url('logo_activate', ['id' => $id]);
                ?>
                <li class="logo-card<?= $inDark || $inLight ? ' logo-card--active' : '' ?>">
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
                        <?php /* One tag where this logo is live -- "live" alone when
                                 both designs show it, otherwise the design named --
                                 and a button where it is not. */ ?>
                        <?php if ($inDark && $inLight): ?>
                            <span class="tag tag--gold"><?= $e(t('live')) ?></span>
                        <?php else: ?>
                            <?php if ($inDark): ?>
                                <span class="tag tag--gold"><?= $e(t('live · dark')) ?></span>
                            <?php else: ?>
                                <form method="post" action="<?= $e($slotUrl) ?>">
                                    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                                    <input type="hidden" name="back" value="<?= $e($current) ?>">
                                    <input type="hidden" name="scheme" value="<?= $e(Theme::DARK) ?>">
                                    <button type="submit" class="link-button" title="<?= $e(t('Switch live for the dark design')) ?>"><?= icon('check') ?><?= $e(t('Dark')) ?></button>
                                </form>
                            <?php endif; ?>
                            <?php if ($inLight): ?>
                                <span class="tag tag--gold"><?= $e(t('live · light')) ?></span>
                            <?php elseif ($twoSlots): ?>
                                <form method="post" action="<?= $e($slotUrl) ?>">
                                    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                                    <input type="hidden" name="back" value="<?= $e($current) ?>">
                                    <input type="hidden" name="scheme" value="<?= $e(Theme::LIGHT) ?>">
                                    <button type="submit" class="link-button" title="<?= $e(t('Switch live for the light design')) ?>"><?= icon('check') ?><?= $e(t('Light')) ?></button>
                                </form>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php /* Back to one logo for both designs: the light slot's
                                 neutral choice, which is no entry at all. */ ?>
                        <?php if ($lightId === $id): ?>
                            <form method="post" action="<?= $e(url('logo_activate', ['id' => 0])) ?>">
                                <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                                <input type="hidden" name="back" value="<?= $e($current) ?>">
                                <input type="hidden" name="scheme" value="<?= $e(Theme::LIGHT) ?>">
                                <button type="submit" class="link-button" title="<?= $e(t('Show the dark design’s logo in the light design as well')) ?>"><?= icon('cross') ?><?= $e(t('Same as dark')) ?></button>
                            </form>
                        <?php endif; ?>
                        <form method="post" action="<?= $e(url('logo_delete', ['id' => (int) $upload['id']])) ?>" data-confirm="<?= $e($inDark || $inLight ? t('Delete the live logo? The header falls back to what it showed before.') : t('Delete this logo?')) ?>">
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
