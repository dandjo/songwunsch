<?php

declare(strict_types=1);

use Songwunsch\Format;
use Songwunsch\RoomRepository;
use Songwunsch\SongRepository;

/** @var SongRepository $repo */
/** @var array<int,array<string,mixed>> $rows */
/** @var int $total */
/** @var string $q */
/** @var string $sort */
/** @var string $dir */
/** @var int $pageNo */
/** @var int $pages */
/** @var string $csrf */
/** @var \Songwunsch\Settings $settings  delete confirmation switches */
/** @var bool $paused          wishing closed by the moderator */
/** @var string $formToken     signed timestamp for the wish form */
/** @var array<string,mixed> $room  current room */

$e        = static fn (?string $v): string => Format::e($v);
$inRoom   = (int) $room['id'] !== RoomRepository::DEFAULT_ID;
$current  = url(['p' => 'songs', 'q' => $q, 'sort' => $sort, 'dir' => $dir, 'page' => $pageNo > 1 ? $pageNo : null]);
$sortable = $repo->sortableFields();
$columns  = ['artist' => t('Artist'), 'title' => t('Title'), 'length' => t('Length'), 'genre' => t('Genre')];

/** Header cell with sort link and aria-sort for screen readers. */
$th = static function (string $key, string $label) use ($sortable, $sort, $dir, $q, $e): string {
    if (!array_key_exists($key, $sortable)) {
        return '<th scope="col" class="col-' . $e($key) . '">' . $e($label) . '</th>';
    }

    $active   = $sort === $key;
    $nextDir  = $active && $dir === 'asc' ? 'desc' : 'asc';
    $ariaSort = $active ? ($dir === 'asc' ? 'ascending' : 'descending') : 'none';
    $arrow    = $active ? ($dir === 'asc' ? '▲' : '▼') : '';
    $href     = url(['p' => 'songs', 'q' => $q, 'sort' => $key, 'dir' => $nextDir]);

    return '<th scope="col" class="col-' . $e($key) . '" aria-sort="' . $ariaSort . '">'
        . '<a class="sort' . ($active ? ' sort--active' : '') . '" href="' . $e($href) . '">'
        . $e($label)
        . '<span class="sort__arrow" aria-hidden="true">' . $arrow . '</span>'
        . '<span class="sr-only">' . $e($nextDir === 'asc' ? t(', sort ascending') : t(', sort descending')) . '</span>'
        . '</a></th>';
};
?>

<div class="panel__head">
    <div>
        <h1><?= $e($inRoom ? (string) $room['name'] : t('Repertoire')) ?></h1>
        <p class="muted">
            <?= $e(tn('{n} song', '{n} songs', $total, ['n' => Format::number($total)])) ?>
            <?= $e($q !== '' ? t('found for “{q}”.', ['q' => $q]) : ($inRoom ? t('in this room.') : t('in the repertoire.'))) ?>
            <?php if (!$paused): ?>
                <?= t('A click on {wish} drops the song into the list.', ['wish' => '<em>' . $e(t('Wish')) . '</em>']) ?>
            <?php endif; ?>
        </p>
    </div>

    <?php if (($inRoom && $security->can('rooms')) || (!$inRoom && $security->can('songs'))): ?>
        <?php /* The main action at the right end: Manage in a room, Add song on
                 the master list. The room's switches (start room, close/open)
                 live on the room list and the room's edit form, not here. */ ?>
        <div class="panel__actions">
            <?php if ($inRoom && $security->can('rooms')): ?>
                <a class="link-button" href="<?= $e(url(['p' => 'room_songs', 'back' => $current])) ?>">
                    <?= icon('note') ?>
                    <?= $e(t('Manage')) ?>
                </a>
            <?php elseif (!$inRoom && $security->can('songs')): ?>
                <a class="link-button" href="<?= $e(url(['p' => 'song', 'back' => $current])) ?>">
                    <?= icon('plus') ?>
                    <?= $e(t('Add song')) ?>
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php /* Own row below the head, right-aligned. */ ?>
<form class="search" method="get" action="<?= $e(url()) ?>" role="search">
    <input type="hidden" name="sort" value="<?= $e($sort) ?>">
    <input type="hidden" name="dir" value="<?= $e($dir) ?>">
    <label class="sr-only" for="q"><?= $e(t('Search songs')) ?></label>
    <input type="search" id="q" name="q" value="<?= $e($q) ?>" placeholder="<?= $e(t('Artist, title, genre …')) ?>" autocomplete="off">
    <button type="submit"><?= $e(t('Search')) ?></button>
    <?php if ($q !== ''): ?>
        <a class="search__reset" href="<?= $e(url(['p' => 'songs', 'sort' => $sort, 'dir' => $dir])) ?>"><?= $e(t('reset')) ?></a>
    <?php endif; ?>
</form>

<?php if ($rows === []): ?>
    <?php if ($inRoom && $q === '' && $total === 0): ?>
        <p class="empty">
            <?= $e(t('This room has no songs yet.')) ?>
            <?php if ($security->can('rooms')): ?>
                <a href="<?= $e(url(['p' => 'room_songs', 'back' => $current])) ?>"><?= $e(t('Manage the room: pick its songs from the master list.')) ?></a>
            <?php endif; ?>
        </p>
    <?php else: ?>
        <p class="empty"><?= $e(t('No song found. Try a different spelling?')) ?></p>
    <?php endif; ?>
<?php else: ?>
    <?php
    $sortbarItems = array_intersect_key($columns, $sortable);
    $sortbarPage  = 'songs';
    $sortbarExtra = ['q' => $q];
    require __DIR__ . '/_sortbar.php';
    ?>
    <?php $hasActions = $security->can('songs') || !$paused; ?>
    <div class="table-wrap">
        <table class="grid grid--songs<?= $security->can('songs') ? ' grid--editable' : '' ?>"<?= $paused ? '' : ' data-rowclick' ?>>
            <caption class="sr-only">
                <?= $e(t('Songs, sortable by artist, title, length and genre')) ?><?= $security->can('songs') ? $e(t('; with columns to edit and delete')) : '' ?>
            </caption>
            <thead>
            <tr>
                <?= $th('artist', $columns['artist']) ?>
                <?= $th('title', $columns['title']) ?>
                <?= $th('length', $columns['length']) ?>
                <?= $th('genre', $columns['genre']) ?>
                <?php if ($hasActions): ?>
                    <th scope="col"><span class="sr-only"><?= $e(t('Actions')) ?></span></th>
                <?php endif; ?>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <?php $rowKey = (string) (int) $row['id']; ?>
                <?php $rowLabel = t('{title} by {artist}', ['title' => (string) $row['title'], 'artist' => (string) $row['artist']]); ?>
                <tr>
                    <td class="cell-artist"><?= $e((string) $row['artist']) ?></td>
                    <td class="cell-title"><?= $e((string) $row['title']) ?></td>
                    <td class="cell-length"><?= $e(Format::length($row['length_sec'])) ?></td>
                    <td class="cell-genre">
                        <?php if ($row['genre'] !== null && $row['genre'] !== ''): ?>
                            <span class="tag"><?= $e((string) $row['genre']) ?></span>
                        <?php endif; ?>
                    </td>
                    <?php if ($hasActions): ?>
                        <?php /* One action cell per row, stacked at the right edge: Wish
                                 (primary) on top, below it Edit and Delete side by side 50/50 as
                                 icon buttons -- the label stays for screen readers and as tooltip.
                                 Guests only see Wish; when wishing is paused, editors only see
                                 the pair. */ ?>
                        <td class="cell-action">
                            <div class="row-actions">
                                <?php if (!$paused): ?>
                                    <form method="post" action="<?= $e(url()) ?>">
                                        <input type="hidden" name="a" value="wish">
                                        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                                        <input type="hidden" name="back" value="<?= $e($current) ?>">
                                        <input type="hidden" name="key" value="<?= $e($rowKey) ?>">
                                        <input type="hidden" name="t" value="<?= $e($formToken) ?>">
                                        <?php /* Bot trap: invisible, out of the tab order, stays empty for humans. */ ?>
                                        <div class="hp" aria-hidden="true">
                                            <input type="text" name="hp_url" tabindex="-1" autocomplete="off" value="">
                                        </div>
                                        <button type="submit" class="wish-button">
                                            <?= icon('star') ?><?= $e(t('Wish')) ?><span class="sr-only">: <?= $e($rowLabel) ?></span>
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($security->can('songs')): ?>
                                    <div class="row-actions__pair">
                                        <a class="link-button icon-button" title="<?= $e(t('Edit')) ?>"
                                           href="<?= $e(url(['p' => 'song', 'id' => $rowKey, 'back' => $current])) ?>">
                                            <?= icon('pencil') ?>
                                            <span class="button__label"><?= $e(t('Edit')) ?></span>
                                            <span class="sr-only">: <?= $e($rowLabel) ?></span>
                                        </a>
                                        <?php if ($inRoom): ?>
                                            <?php /* In a room the song only leaves the room; the master list keeps it. */ ?>
                                            <form method="post" action="<?= $e(url()) ?>">
                                                <input type="hidden" name="a" value="room_songs_remove">
                                                <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                                                <input type="hidden" name="back" value="<?= $e($current) ?>">
                                                <input type="hidden" name="key[]" value="<?= $e($rowKey) ?>">
                                                <button type="submit" class="delete-button icon-button" title="<?= $e(t('Remove')) ?>">
                                                    <?= icon('trash') ?>
                                                    <span class="button__label"><?= $e(t('Remove')) ?></span>
                                                    <span class="sr-only">: <?= $e($rowLabel) ?></span>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form method="post" action="<?= $e(url()) ?>"<?php if ($settings->confirmsDelete((int) ($security->user()['id'] ?? 0), 'songs')): ?>
                                                  data-confirm="<?= $e(t('Permanently delete “{title}” from the repertoire?', ['title' => (string) $row['title']])) ?>"<?php endif; ?>>
                                                <input type="hidden" name="a" value="song_delete">
                                                <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                                                <input type="hidden" name="back" value="<?= $e($current) ?>">
                                                <input type="hidden" name="key" value="<?= $e($rowKey) ?>">
                                                <button type="submit" class="delete-button icon-button" title="<?= $e(t('Delete')) ?>">
                                                    <?= icon('trash') ?>
                                                    <span class="button__label"><?= $e(t('Delete')) ?></span>
                                                    <span class="sr-only">: <?= $e($rowLabel) ?></span>
                                                </button>
                                            </form>
                                        <?php endif; ?>
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
    $pageUrl = static fn (int $page): string => url(['p' => 'songs', 'q' => $q, 'sort' => $sort, 'dir' => $dir, 'page' => $page > 1 ? $page : null]);
    require __DIR__ . '/_pager.php';
    ?>
<?php endif; ?>
