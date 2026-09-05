<?php

declare(strict_types=1);

use Songwunsch\Format;

/**
 * Editors: a room's address as a QR code, to print or to download. The code
 * is inline SVG, so it prints crisply at any size; the same code is offered
 * as SVG and PNG file. In print only the code and the address remain (CSS).
 *
 * @var array<string,mixed> $room     the room, the main room included
 * @var string $address               the absolute address the code carries
 * @var string $svg                   the code as SVG markup (src/QrCode.php, safe to print as is)
 * @var bool   $hasPng                PNG download available (gd extension)
 */

$e      = static fn (?string $v): string => Format::e($v);
$slug   = (string) $room['slug'];
$isMain = (int) $room['id'] === 0;
$roomName = (string) $room['name'];
?>

<div class="panel__head">
    <div>
        <h1><?= $e(t('QR code: {room}', ['room' => $roomName])) ?></h1>
        <p class="muted">
            <?= $e(t('Guests scan the code with their phone\'s camera and land in the room – on a table card, a poster or a slide.')) ?>
            <?= $e(t('The code is made on this server; the address is passed to no other service.')) ?>
        </p>
    </div>
    <div class="panel__actions">
        <a class="link-button" href="<?= $e(url(['p' => 'room_qr', 'room' => $slug, 'format' => 'svg'])) ?>" download="songwunsch-<?= $e($isMain ? 'main' : $slug) ?>.svg"><?= icon('image') ?><?= $e(t('Download SVG')) ?></a>
        <?php if ($hasPng): ?>
            <a class="link-button" href="<?= $e(url(['p' => 'room_qr', 'room' => $slug, 'format' => 'png'])) ?>" download="songwunsch-<?= $e($isMain ? 'main' : $slug) ?>.png"><?= icon('image') ?><?= $e(t('Download PNG')) ?></a>
        <?php endif; ?>
        <button type="button" class="wish-button" data-print hidden><?= icon('page') ?><?= $e(t('Print')) ?></button>
        <a class="link-button" href="<?= $e(url(['p' => 'rooms'])) ?>"><?= icon('cross') ?><?= $e(t('Back')) ?></a>
    </div>
</div>

<figure class="qr">
    <?php /* The SVG comes from QrCode::svg(): fixed markup with numbers only,
             printed unescaped on purpose. */ ?>
    <div class="qr__code"><?= $svg ?></div>
    <figcaption class="qr__caption">
        <span class="qr__room"><?= $e($roomName) ?></span>
        <code class="qr__address"><?= $e($address) ?></code>
    </figcaption>
</figure>
