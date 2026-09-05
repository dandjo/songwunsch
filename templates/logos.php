<?php

declare(strict_types=1);

use Songwunsch\Format;
use Songwunsch\Uploads;

/**
 * Admin only: every logo ever uploaded, one of them (or none -- the word
 * mark) switched live for the header.
 *
 * @var array<int,array{id:int,mime:string,width:?int,height:?int,size:int,created_at:string}> $logos  newest first
 * @var int $activeId    id of the logo the header shows, 0 = word mark
 * @var string $csrf
 */

$e = static fn (?string $v): string => Format::e($v);

$logoUrl = static fn (int $id): string => url(['p' => 'logo', 'room' => '', 'id' => $id]);
$kb      = static fn (int $bytes): int => max(1, (int) round($bytes / 1024));
?>

<div class="panel__head">
    <div>
        <div class="panel__title">
            <h1><?= $e(t('Logos')) ?></h1>
            <?= help_button('help-logos') ?>
        </div>
        <p class="muted help" id="help-logos"><?= $e(t('A logo takes the place of the word mark “Songwunsch” and the claim at the top of every page; the room’s name keeps its spot. Exactly one logo is live at a time – or none, then the word mark shows.')) ?></p>
    </div>
</div>

<div class="login login--wide">
    <form method="post" action="<?= $e(url()) ?>" enctype="multipart/form-data" class="login__form">
        <input type="hidden" name="a" value="logo_upload">
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
            <?php /* The word mark heads the list as the choice "no logo". */ ?>
            <li class="logo-card<?= $activeId === 0 ? ' logo-card--active' : '' ?>">
                <div class="logo-card__preview"><span class="dome__brand">Song<span>wunsch</span></span></div>
                <div class="logo-card__meta">
                    <strong><?= $e(t('Word mark')) ?></strong>
                    <span class="muted"><?= $e(t('The default without a logo, with the claim below.')) ?></span>
                </div>
                <div class="logo-card__actions">
                    <?php if ($activeId === 0): ?>
                        <span class="tag tag--gold"><?= $e(t('live')) ?></span>
                    <?php else: ?>
                        <form method="post" action="<?= $e(url()) ?>">
                            <input type="hidden" name="a" value="logo_activate">
                            <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                            <input type="hidden" name="id" value="0">
                            <button type="submit" class="link-button"><?= icon('check') ?><?= $e(t('Switch live')) ?></button>
                        </form>
                    <?php endif; ?>
                </div>
            </li>

            <?php foreach ($logos as $logo): ?>
                <?php $isActive = $logo['id'] === $activeId; ?>
                <li class="logo-card<?= $isActive ? ' logo-card--active' : '' ?>">
                    <div class="logo-card__preview">
                        <img src="<?= $e($logoUrl($logo['id'])) ?>" alt="<?= $e(t('Logo {id}', ['id' => $logo['id']])) ?>">
                    </div>
                    <div class="logo-card__meta">
                        <strong><?= $e(t('Logo {id}', ['id' => $logo['id']])) ?></strong>
                        <span class="muted">
                            <?= $e($logo['width'] !== null
                                ? t('{w} × {h} pixels, {kb} KB', ['w' => $logo['width'], 'h' => $logo['height'], 'kb' => $kb($logo['size'])])
                                : t('SVG, {kb} KB', ['kb' => $kb($logo['size'])])) ?>
                            · <?= $e(t('uploaded {when}', ['when' => Format::moment($logo['created_at'])])) ?>
                        </span>
                    </div>
                    <div class="logo-card__actions">
                        <?php if ($isActive): ?>
                            <span class="tag tag--gold"><?= $e(t('live')) ?></span>
                        <?php else: ?>
                            <form method="post" action="<?= $e(url()) ?>">
                                <input type="hidden" name="a" value="logo_activate">
                                <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                                <input type="hidden" name="id" value="<?= $logo['id'] ?>">
                                <button type="submit" class="link-button"><?= icon('check') ?><?= $e(t('Switch live')) ?></button>
                            </form>
                        <?php endif; ?>
                        <form method="post" action="<?= $e(url()) ?>" data-confirm="<?= $e($isActive ? t('Delete the live logo? The header shows the word mark again.') : t('Delete this logo?')) ?>">
                            <input type="hidden" name="a" value="logo_delete">
                            <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                            <input type="hidden" name="id" value="<?= $logo['id'] ?>">
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
        <?php if ($logos === []): ?>
            <p class="field__hint"><?= $e(t('No logo uploaded yet.')) ?></p>
        <?php endif; ?>
    </div>
</div>
