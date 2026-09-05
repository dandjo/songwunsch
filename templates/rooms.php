<?php

declare(strict_types=1);

use Songwunsch\Format;

/** @var array<int,array<string,mixed>> $rows  one page of rooms with song_count and wish_count */
/** @var int $total           rooms matching search and filter */
/** @var string $q */
/** @var string $filter       active | archived | all (guests: always active, and only listed rooms) */
/** @var int $pageNo */
/** @var int $pages */
/** @var bool $canEdit        editor or admin: create, edit, delete */
/** @var bool $isAdmin        admin: close or open every room at once */
/** @var bool $pausedAll      the admin's closing of every room is in force */
/** @var bool $canPause       moderator or admin: close and open single rooms */
/** @var array<int,bool> $pausedRooms  room id => closed?, for the rows shown */
/** @var int $startRoomId    room new visitors land in from the bare address, 0 = main room */
/** @var int $mainSongs     songs in the main list (= the main room) */
/** @var int $mainWishes    open wishes of the main room */
/** @var array<string,mixed> $room  current room */
/** @var string $csrf */
/** @var \Songwunsch\Settings $settings  delete confirmation switches */

$e         = static fn (?string $v): string => Format::e($v);
$currentId = (int) $room['id'];
$canCount  = $security->can('wishes');
$listUrl   = static fn (array $extra = []): string => url(array_merge(['p' => 'rooms', 'q' => $q, 'filter' => $canEdit ? $filter : null], $extra));
// This very page of the list -- the destination the forms and the QR page return to.
$here      = $listUrl(['page' => $pageNo > 1 ? $pageNo : null]);
// The main room heads the list once: first page, no search, not the archive.
$showMain  = $pageNo === 1 && $q === '' && $filter !== 'archived';
// Guests get no action column: the room's name is the link into the room.
$hasActions = $canPause || $canEdit;
?>

<div class="panel__head">
    <div>
        <div class="panel__title">
            <h1><?= $e(t('Rooms')) ?></h1>
            <?= help_button('help-rooms') ?>
        </div>
        <p class="muted help" id="help-rooms">
            <?php if ($q !== ''): ?>
                <?= $e(tn('{n} room found for “{q}”.', '{n} rooms found for “{q}”.', $total, ['q' => $q])) ?>
            <?php elseif ($filter === 'archived'): ?>
                <?= $e(tn('{n} archived room.', '{n} archived rooms.', $total)) ?>
            <?php else: ?>
                <?= $e(tn('{n} room besides “General”.', '{n} rooms besides “General”.', $total)) ?>
            <?php endif; ?>
            <?= $e(t('Every room has its own repertoire, picked from the main list, and its own wish list.')) ?>
            <?php if ($canEdit): ?>
                <?= $e(t('Archived rooms leave the room switcher and the list and are reachable to signed-in users only – a guest who opens the address lands on the start page.')) ?>
                <?= $e(t('Unlisted rooms are reached through their address only: guests see them neither here nor among the rooms offered in the room switcher – only a guest who has entered one finds it there under “Your rooms”.')) ?>
                <?= $e($startRoomId > 0
                    ? t('The start room receives visitors who open the bare address without having chosen a room yet; everyone else stays in the room they chose last.')
                    : t('Visitors who open the bare address without having chosen a room yet land in “General”; As start room sends them into another room instead.')) ?>
            <?php endif; ?>
        </p>
    </div>

    <?php if ($canEdit): ?>
        <div class="panel__actions">
            <?php if ($isAdmin): ?>
                <form method="post" action="<?= $e(url()) ?>">
                    <input type="hidden" name="a" value="pause_all">
                    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                    <input type="hidden" name="state" value="<?= $pausedAll ? '0' : '1' ?>">
                    <button type="submit" class="link-button" aria-pressed="<?= $pausedAll ? 'true' : 'false' ?>">
                        <?php if ($pausedAll): ?>
                            <?= icon('play') ?>
                        <?php else: ?>
                            <?= icon('stop') ?>
                        <?php endif; ?>
                        <?= $e($pausedAll ? t('Lift the closing of all rooms') : t('Close all rooms')) ?>
                    </button>
                </form>
            <?php endif; ?>
            <a class="link-button" href="<?= $e(url(['p' => 'room', 'back' => $here])) ?>"><?= icon('plus') ?><?= $e(t('Add room')) ?></a>
        </div>
    <?php endif; ?>
</div>

<form class="search" method="get" action="<?= $e(url(['p' => 'rooms'])) ?>" role="search">
    <?php if ($canEdit): ?><input type="hidden" name="filter" value="<?= $e($filter) ?>"><?php endif; ?>
    <label class="sr-only" for="q"><?= $e(t('Search rooms')) ?></label>
    <input type="search" id="q" name="q" value="<?= $e($q) ?>" placeholder="<?= $e(t('Room name or machine name …')) ?>" autocomplete="off">
    <button type="submit"><?= $e(t('Search')) ?></button>
    <?php if ($q !== ''): ?>
        <a class="search__reset" href="<?= $e($listUrl(['q' => null])) ?>"><?= $e(t('reset')) ?></a>
    <?php endif; ?>
</form>

<?php if ($canEdit): ?>
    <nav class="sortbar" aria-label="<?= $e(t('Filter')) ?>">
        <span class="sortbar__label"><?= $e(t('Show:')) ?></span>
        <?php foreach (['all' => t('All'), 'active' => t('Active'), 'archived' => t('Archived')] as $key => $label): ?>
            <a class="sortbar__item<?= $filter === $key ? ' is-active' : '' ?>"
               href="<?= $e(url(['p' => 'rooms', 'q' => $q, 'filter' => $key])) ?>"<?= $filter === $key ? ' aria-current="true"' : '' ?>><?= $e($label) ?></a>
        <?php endforeach; ?>
    </nav>
<?php endif; ?>

<?php if ($rows === [] && !$showMain): ?>
    <p class="empty"><?= $e($q !== '' ? t('No room found. Try a different spelling?') : t('No archived rooms.')) ?></p>
<?php else: ?>
<div class="table-wrap">
    <table class="grid grid--rooms<?= $canEdit ? ' grid--editable' : '' ?>">
        <caption class="sr-only"><?= $e(t('Rooms with their number of songs')) ?></caption>
        <thead>
        <tr>
            <th scope="col"><?= $e(t('Room')) ?></th>
            <th scope="col"><?= $e(t('Address')) ?></th>
            <th scope="col"><?= $e(t('Repertoire')) ?></th>
            <?php if ($canCount): ?>
                <th scope="col"><?= $e(t('Wishes')) ?></th>
            <?php endif; ?>
            <?php if ($hasActions): ?>
                <th scope="col"><span class="sr-only"><?= $e(t('Actions')) ?></span></th>
            <?php endif; ?>
        </tr>
        </thead>
        <tbody>
        <?php
        // The main room first: virtual, always there, not editable.
        $all = $showMain ? array_merge([[
            'id'         => 0,
            'slug'       => '',
            'name'       => (string) \Songwunsch\RoomRepository::defaultRoom()['name'],
            'active'     => 1,
            'listed'     => 1,
            'song_count' => $mainSongs,
            'wish_count' => $mainWishes,
        ]], $rows) : $rows;
        ?>
        <?php foreach ($all as $row): ?>
            <?php
            $isMain  = (int) $row['id'] === 0;
            $isHere  = (int) $row['id'] === $currentId;
            $slug    = (string) $row['slug'];
            $address = url(['p' => 'songs', 'room' => $slug]);
            ?>
            <tr>
                <td class="cell-title">
                    <?php /* The name leads into the room -- the way to change rooms from
                             here. The main room is a POST that clears the remembered
                             room (RoomMemory); every other room is reached by address. */ ?>
                    <?php if ($isMain): ?>
                        <form method="post" action="<?= $e(url(['p' => 'rooms'])) ?>" class="room-link-form">
                            <input type="hidden" name="a" value="room_switch">
                            <input type="hidden" name="slug" value="">
                            <input type="hidden" name="to" value="songs">
                            <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                            <button type="submit" class="room-link"<?= $isHere ? ' aria-current="true"' : '' ?>><?= $e((string) $row['name']) ?></button>
                        </form>
                    <?php else: ?>
                        <a class="room-link" href="<?= $e($address) ?>"<?= $isHere ? ' aria-current="true"' : '' ?>><?= $e((string) $row['name']) ?></a>
                    <?php endif; ?>
                    <?php if ($isMain): ?><span class="tag tag--gold"><?= $e(t('always there')) ?></span><?php endif; ?>
                    <?php if ((int) $row['active'] === 0): ?><span class="tag"><?= $e(t('archived')) ?></span><?php endif; ?>
                    <?php if ($canEdit && !$isMain && (int) ($row['listed'] ?? 0) === 0): ?><span class="tag"><?= $e(t('unlisted')) ?></span><?php endif; ?>
                    <?php if ($canPause && ($pausedRooms[(int) $row['id']] ?? false)): ?><span class="tag"><?= $e(t('closed')) ?></span><?php endif; ?>
                    <?php if ((int) $row['id'] === $startRoomId && (int) $row['active'] === 1): ?><span class="tag tag--gold"><?= $e(t('start room')) ?></span><?php endif; ?>
                    <?php if ($isHere): ?><span class="muted"><?= $e(t('(current)')) ?></span><?php endif; ?>
                </td>
                <td class="cell-genre"><code class="address"><?= $e($address) ?></code></td>
                <?php /* Counts on their own line below the address, spelled out. */ ?>
                <td class="cell-length"><?= $e(tn('{n} song', '{n} songs', (int) $row['song_count'], ['n' => Format::number((int) $row['song_count'])])) ?></td>
                <?php if ($canCount): ?>
                    <td class="cell-wishes"><?= $e(tn('{n} wish', '{n} wishes', (int) $row['wish_count'], ['n' => Format::number((int) $row['wish_count'])])) ?></td>
                <?php endif; ?>
                <?php if ($hasActions): ?>
                <?php /* One action cell per row, stacked at the right edge, in the
                         order of the edit form: "As start room" (editors) first,
                         then Close/Open room (moderators), QR code and "Manage",
                         below them Edit and Delete side by side 50/50 as icon
                         buttons -- the label stays for screen readers and as
                         tooltip. Editing never for the main room. */ ?>
                <td class="cell-action">
                    <div class="row-actions">
                        <?php if ($canEdit && (int) $row['id'] !== $startRoomId && (int) $row['active'] === 1): ?>
                            <?php /* Where new visitors land: the bare address leads into
                                     the start room. Setting the main room clears it. */ ?>
                            <form method="post" action="<?= $e(url(['p' => 'rooms'])) ?>">
                                <input type="hidden" name="a" value="room_start">
                                <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                <input type="hidden" name="back" value="<?= $e($listUrl(['page' => $pageNo > 1 ? $pageNo : null])) ?>">
                                <button type="submit" class="link-button">
                                    <?= icon('flag') ?>
                                    <span class="button__label"><?= $e(t('As start room')) ?></span>
                                    <span class="sr-only">: <?= $e((string) $row['name']) ?></span>
                                </button>
                            </form>
                        <?php endif; ?>
                        <?php if ($canPause): ?>
                            <?php /* Close or open the room -- the moderator's switch,
                                     for the main room as well. A stop sign while open,
                                     a play triangle while closed. */ ?>
                            <?php $closed = $pausedRooms[(int) $row['id']] ?? false; ?>
                            <form method="post" action="<?= $e(url()) ?>">
                                <input type="hidden" name="a" value="pause">
                                <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                <input type="hidden" name="state" value="<?= $closed ? '0' : '1' ?>">
                                <input type="hidden" name="back" value="<?= $e($listUrl(['page' => $pageNo > 1 ? $pageNo : null])) ?>">
                                <button type="submit" class="<?= $closed ? 'wish-button' : 'link-button' ?>" aria-pressed="<?= $closed ? 'true' : 'false' ?>">
                                    <?= icon($closed ? 'play' : 'stop') ?>
                                    <span class="button__label"><?= $e($closed ? t('Open room') : t('Close room')) ?></span>
                                    <span class="sr-only">: <?= $e((string) $row['name']) ?></span>
                                </button>
                            </form>
                        <?php endif; ?>
                        <?php if ($canEdit): ?>
                            <?php /* The room's address as a QR code -- the main room's as well. */ ?>
                            <a class="link-button" href="<?= $e(url(['p' => 'room_qr', 'room' => $slug, 'back' => $here])) ?>">
                                <?= icon('qr') ?>
                                <span class="button__label"><?= $e(t('QR code')) ?></span>
                                <span class="sr-only">: <?= $e((string) $row['name']) ?></span>
                            </a>
                        <?php endif; ?>
                        <?php if ($canEdit && $isMain): ?>
                            <?php /* The main room cannot be managed or deleted, but renamed. */ ?>
                            <a class="link-button" href="<?= $e(url(['p' => 'room', 'main' => 1, 'back' => $here])) ?>">
                                <?= icon('pencil') ?>
                                <span class="button__label"><?= $e(t('Rename')) ?></span>
                                <span class="sr-only">: <?= $e((string) $row['name']) ?></span>
                            </a>
                        <?php endif; ?>
                        <?php if ($canEdit && !$isMain): ?>
                            <a class="link-button" href="<?= $e(url(['p' => 'room_songs', 'room' => $slug, 'back' => $here])) ?>">
                                <?= icon('note') ?>
                                <span class="button__label"><?= $e(t('Manage')) ?></span>
                                <span class="sr-only">: <?= $e((string) $row['name']) ?></span>
                            </a>
                            <div class="row-actions__pair">
                                <a class="link-button icon-button" title="<?= $e(t('Edit')) ?>" href="<?= $e(url(['p' => 'room', 'id' => (int) $row['id'], 'back' => $here])) ?>">
                                    <?= icon('pencil') ?>
                                    <span class="button__label"><?= $e(t('Edit')) ?></span>
                                    <span class="sr-only">: <?= $e((string) $row['name']) ?></span>
                                </a>
                                <form method="post" action="<?= $e(url()) ?>"<?php if ($settings->confirmsDelete((int) ($security->user()['id'] ?? 0), 'rooms')): ?>
                                      data-confirm="<?= $e(t('Permanently delete room “{name}” together with its wishes?', ['name' => (string) $row['name']])) ?>"<?php endif; ?>>
                                    <input type="hidden" name="a" value="room_delete">
                                    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                    <button type="submit" class="delete-button icon-button" title="<?= $e(t('Delete')) ?>">
                                        <?= icon('trash') ?>
                                        <span class="button__label"><?= $e(t('Delete')) ?></span>
                                        <span class="sr-only">: <?= $e((string) $row['name']) ?></span>
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php
$pageUrl = static fn (int $page): string => $listUrl(['page' => $page > 1 ? $page : null]);
require __DIR__ . '/_pager.php';
?>
<?php endif; ?>
