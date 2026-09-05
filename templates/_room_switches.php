<?php

declare(strict_types=1);

/**
 * A room's two switches beside the page's other actions: "As start room"
 * (editors; a tag instead once it is the start room) and "Close room" /
 * "Open room" (moderators). Used on the room's edit form -- the room list
 * has its own copy that names the room for screen readers, since it shows
 * many rooms at once. The room's own pages carry no switches.
 *
 * Expects:
 * @var int      $switchId      the room, 0 = the main room
 * @var bool     $switchActive  an archived room cannot become the start room
 * @var bool     $switchClosed  wishing closed in this room
 * @var int      $startRoomId   the current start room, 0 = the main room
 * @var string   $switchBack    where both actions return to
 * @var \Songwunsch\Security $security
 * @var string   $csrf
 */

use Songwunsch\Format;

$e = static fn (?string $v): string => Format::e($v);
?>
<?php if ($security->can('rooms') && $switchActive): ?>
    <?php if ($switchId === $startRoomId): ?>
        <?php /* Already where new visitors land; the main room is that by default and gets no tag. */ ?>
        <?php if ($switchId > 0): ?><span class="tag tag--gold"><?= $e(t('start room')) ?></span><?php endif; ?>
    <?php else: ?>
        <form method="post" action="<?= $e(url(['p' => 'rooms'])) ?>">
            <input type="hidden" name="a" value="room_start">
            <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
            <input type="hidden" name="id" value="<?= $switchId ?>">
            <input type="hidden" name="back" value="<?= $e($switchBack) ?>">
            <button type="submit" class="link-button"><?= icon('flag') ?><?= $e(t('As start room')) ?></button>
        </form>
    <?php endif; ?>
<?php endif; ?>
<?php if ($security->can('wishes')): ?>
    <form method="post" action="<?= $e(url()) ?>">
        <input type="hidden" name="a" value="pause">
        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
        <input type="hidden" name="id" value="<?= $switchId ?>">
        <input type="hidden" name="state" value="<?= $switchClosed ? '0' : '1' ?>">
        <input type="hidden" name="back" value="<?= $e($switchBack) ?>">
        <button type="submit" class="<?= $switchClosed ? 'wish-button' : 'link-button' ?>" aria-pressed="<?= $switchClosed ? 'true' : 'false' ?>">
            <?= icon($switchClosed ? 'play' : 'stop') ?><?= $e($switchClosed ? t('Open room') : t('Close room')) ?>
        </button>
    </form>
<?php endif; ?>
