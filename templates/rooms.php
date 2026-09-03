<?php

declare(strict_types=1);

use Songwunsch\Format;

/** @var array<int,array<string,mixed>> $rows  one page of rooms with song_count and wish_count */
/** @var int $total           rooms matching search and filter */
/** @var string $q */
/** @var string $filter       active | archived | all (guests: always active) */
/** @var int $pageNo */
/** @var int $pages */
/** @var bool $canEdit        editor or admin: create, edit, delete */
/** @var bool $isAdmin        admin: pause or resume wishing in every room at once */
/** @var bool $pausedAll      the admin's pause of every room is in force */
/** @var int $masterSongs     songs in the master list (= the main room) */
/** @var int $masterWishes    open wishes of the main room */
/** @var array<string,mixed> $room  current room */
/** @var string $csrf */

$e         = static fn (?string $v): string => Format::e($v);
$currentId = (int) $room['id'];
$canCount  = $security->can('wishes');
$listUrl   = static fn (array $extra = []): string => url(array_merge(['p' => 'rooms', 'q' => $q, 'filter' => $canEdit ? $filter : null], $extra));
// The main room heads the list once: first page, no search, not the archive.
$showMain  = $pageNo === 1 && $q === '' && $filter !== 'archived';
?>

<div class="panel__head">
    <div>
        <h1><?= $e(t('Rooms')) ?></h1>
        <p class="muted">
            <?php if ($q !== ''): ?>
                <?= $e(tn('{n} room found for “{q}”.', '{n} rooms found for “{q}”.', $total, ['q' => $q])) ?>
            <?php elseif ($filter === 'archived'): ?>
                <?= $e(tn('{n} archived room.', '{n} archived rooms.', $total)) ?>
            <?php else: ?>
                <?= $e(tn('{n} room besides the main room.', '{n} rooms besides the main room.', $total)) ?>
            <?php endif; ?>
            <?= $e(t('Every room has its own song list, picked from the master list, and its own wish list.')) ?>
            <?php if ($canEdit): ?>
                <?= $e(t('Archived rooms stay reachable through their address but leave the room switcher and the guests’ list.')) ?>
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
                            <?= icon('pause') ?>
                        <?php endif; ?>
                        <?= $e($pausedAll ? t('Resume wishing in all rooms') : t('Pause wishing in all rooms')) ?>
                    </button>
                </form>
            <?php endif; ?>
            <a class="link-button" href="<?= $e(url(['p' => 'room'])) ?>"><?= icon('plus') ?><?= $e(t('Add room')) ?></a>
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
            <th scope="col"><?= $e(t('Songs')) ?></th>
            <?php if ($canCount): ?>
                <th scope="col"><?= $e(t('Wishes')) ?></th>
            <?php endif; ?>
            <th scope="col"><span class="sr-only"><?= $e(t('Actions')) ?></span></th>
        </tr>
        </thead>
        <tbody>
        <?php
        // The main room first: virtual, always there, not editable.
        $all = $showMain ? array_merge([[
            'id'         => 0,
            'slug'       => '',
            'name'       => t('Main room'),
            'active'     => 1,
            'song_count' => $masterSongs,
            'wish_count' => $masterWishes,
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
                    <?= $e((string) $row['name']) ?>
                    <?php if ($isMain): ?><span class="tag tag--gold"><?= $e(t('always there')) ?></span><?php endif; ?>
                    <?php if ((int) $row['active'] === 0): ?><span class="tag"><?= $e(t('archived')) ?></span><?php endif; ?>
                    <?php if ($isHere): ?><span class="muted"><?= $e(t('(current)')) ?></span><?php endif; ?>
                </td>
                <td class="cell-genre"><code class="address"><?= $e($address) ?></code></td>
                <?php /* Counts on their own line below the address, spelled out. */ ?>
                <td class="cell-length"><?= $e(tn('{n} song', '{n} songs', (int) $row['song_count'], ['n' => Format::number((int) $row['song_count'])])) ?></td>
                <?php if ($canCount): ?>
                    <td class="cell-wishes"><?= $e(tn('{n} wish', '{n} wishes', (int) $row['wish_count'], ['n' => Format::number((int) $row['wish_count'])])) ?></td>
                <?php endif; ?>
                <?php /* One action cell per row, stacked at the right edge: "Change to"
                         (primary) above "Manage", below them Edit and Delete side by
                         side 50/50 as icon buttons -- the label stays for screen readers and
                         as tooltip. Editors only, never for the main room. */ ?>
                <td class="cell-action">
                    <div class="row-actions">
                        <a class="wish-button" href="<?= $e($address) ?>"<?= $isHere ? ' aria-current="true"' : '' ?>>
                            <?= icon('enter') ?><?= $e(t('Change to')) ?><span class="sr-only">: <?= $e((string) $row['name']) ?></span>
                        </a>
                        <?php if ($canEdit && !$isMain): ?>
                            <a class="link-button" href="<?= $e(url(['p' => 'room_songs', 'room' => $slug])) ?>">
                                <?= icon('note') ?>
                                <span class="button__label"><?= $e(t('Manage')) ?></span>
                                <span class="sr-only">: <?= $e((string) $row['name']) ?></span>
                            </a>
                            <div class="row-actions__pair">
                                <a class="link-button icon-button" title="<?= $e(t('Edit')) ?>" href="<?= $e(url(['p' => 'room', 'id' => (int) $row['id']])) ?>">
                                    <?= icon('pencil') ?>
                                    <span class="button__label"><?= $e(t('Edit')) ?></span>
                                    <span class="sr-only">: <?= $e((string) $row['name']) ?></span>
                                </a>
                                <form method="post" action="<?= $e(url()) ?>"
                                      data-confirm="<?= $e(t('Permanently delete room “{name}” together with its wishes?', ['name' => (string) $row['name']])) ?>">
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
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($pages > 1): ?>
    <nav class="pager" aria-label="<?= $e(t('Pages')) ?>">
        <?php if ($pageNo > 1): ?>
            <a href="<?= $e($listUrl(['page' => $pageNo - 1 > 1 ? $pageNo - 1 : null])) ?>" rel="prev">&larr; <?= $e(t('back')) ?></a>
        <?php endif; ?>
        <span><?= $e(t('Page {page} of {pages}', ['page' => $pageNo, 'pages' => $pages])) ?></span>
        <?php if ($pageNo < $pages): ?>
            <a href="<?= $e($listUrl(['page' => $pageNo + 1])) ?>" rel="next"><?= $e(t('next')) ?> &rarr;</a>
        <?php endif; ?>
    </nav>
<?php endif; ?>
<?php endif; ?>

<script src="<?= $e(asset('assets/app.js')) ?>" defer></script>
