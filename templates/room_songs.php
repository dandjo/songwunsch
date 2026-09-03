<?php

declare(strict_types=1);

use Songwunsch\Format;

/**
 * Pick a room's songs: two columns. Left the master list without the songs
 * already in the room, right the room's list. One search filters both, the
 * arrow buttons move a song across -- one click, one round trip, no
 * JavaScript needed.
 */

/** @var array<int,array<string,mixed>> $available  master songs not in the room (one page) */
/** @var int $availableTotal   all of them matching the search */
/** @var array<int,array<string,mixed>> $roomRows   the room's songs matching the search */
/** @var int $roomTotal */
/** @var int $roomSongCount    songs in the room, regardless of the search */
/** @var int $masterCount      songs in the master list */
/** @var string $q */
/** @var int $pageNo */
/** @var int $pages */
/** @var string $csrf */
/** @var array<string,mixed> $room  current room */

$e       = static fn (?string $v): string => Format::e($v);
$current = url(['p' => 'room_songs', 'q' => $q, 'page' => $pageNo > 1 ? $pageNo : null]);

/** Form that moves songs: single id, or with $all every match of the search. */
$move = static function (string $action, string $label, ?int $key, bool $all, string $class, string $confirm = '') use ($csrf, $current, $q, $e): string {
    $html = '<form method="post" action="' . $e(url()) . '"' . ($confirm !== '' ? ' data-confirm="' . $e($confirm) . '"' : '') . '>'
        . '<input type="hidden" name="a" value="' . $e($action) . '">'
        . '<input type="hidden" name="csrf" value="' . $e($csrf) . '">'
        . '<input type="hidden" name="back" value="' . $e($current) . '">';
    if ($all) {
        $html .= '<input type="hidden" name="all" value="1"><input type="hidden" name="q" value="' . $e($q) . '">';
    } elseif ($key !== null) {
        $html .= '<input type="hidden" name="key[]" value="' . $key . '">';
    }

    return $html . '<button type="submit" class="' . $class . '">' . $label . '</button></form>';
};

/** Arrow icon for the move buttons -- an SVG centres exactly, a text glyph sits below the middle. */
$arrowIcon = static fn (string $direction): string => '<svg viewBox="0 0 16 16" width="16" height="16" aria-hidden="true" focusable="false">'
    . ($direction === 'right'
        ? '<path d="M3 8h9M8.5 4.5 12 8l-3.5 3.5"'
        : '<path d="M13 8H4M7.5 4.5 4 8l3.5 3.5"')
    . ' fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';

/** A song card of either column. */
$card = static function (array $row, string $action, string $arrow, string $verb, string $class) use ($move, $e, $arrowIcon): string {
    $label = t('{title} by {artist}', ['title' => (string) $row['title'], 'artist' => (string) $row['artist']]);
    $button = $arrowIcon($arrow) . '<span class="sr-only">' . $e($verb) . ': ' . $e($label) . '</span>';

    return '<tr>'
        . '<td class="cell-title">' . $e((string) $row['title']) . '</td>'
        . '<td class="cell-artist">' . $e((string) $row['artist'])
        . ($row['length_sec'] !== null ? ' · ' . $e(Format::length($row['length_sec'])) : '')
        . (($row['genre'] ?? '') !== '' ? ' · ' . $e((string) $row['genre']) : '')
        . '</td>'
        . '<td class="cell-action">' . $move($action, $button, (int) $row['id'], false, $class) . '</td>'
        . '</tr>';
};
?>

<div class="panel__head">
    <div>
        <h1><?= $e(t('Manage {room}', ['room' => (string) $room['name']])) ?></h1>
        <p class="muted">
            <?= $e(t('{n} of {total} songs are in the room.', ['n' => Format::number($roomSongCount), 'total' => Format::number($masterCount)])) ?>
            <?= $e(t('Move songs with the arrows: to the right into the room, to the left out of it. The search filters both columns.')) ?>
        </p>
    </div>
    <?php /* Counterpart of "Manage" on the song list: back to the room's list. */ ?>
    <div class="panel__actions">
        <a class="link-button" href="<?= $e(url(['p' => 'songs'])) ?>"><?= icon('back') ?><?= $e(t('To the song list')) ?></a>
    </div>
</div>

<form class="search" method="get" action="<?= $e(url(['p' => 'room_songs'])) ?>" role="search">
    <label class="sr-only" for="q"><?= $e(t('Search songs')) ?></label>
    <input type="search" id="q" name="q" value="<?= $e($q) ?>" placeholder="<?= $e(t('Artist, title, genre …')) ?>" autocomplete="off">
    <button type="submit"><?= $e(t('Search')) ?></button>
    <?php if ($q !== ''): ?>
        <a class="search__reset" href="<?= $e(url(['p' => 'room_songs'])) ?>"><?= $e(t('reset')) ?></a>
    <?php endif; ?>
</form>

<div class="picker">
    <section class="picker__col" aria-labelledby="picker-master">
        <div class="picker__head">
            <h2 id="picker-master"><?= $e(t('Master list')) ?></h2>
            <span class="muted"><?= $e(tn('{n} song available', '{n} songs available', $availableTotal, ['n' => Format::number($availableTotal)])) ?></span>
        </div>

        <?php if ($available === []): ?>
            <p class="empty"><?= $e($q !== '' ? t('No song found. Try a different spelling?') : t('Every song of the master list is in the room.')) ?></p>
        <?php else: ?>
            <?php if ($availableTotal > 1): ?>
                <div class="picker__bulk">
                    <?= $move(
                        'room_songs_add',
                        $e($q !== ''
                            ? tn('Add the {n} song found', 'Add all {n} songs found', $availableTotal, ['n' => Format::number($availableTotal)])
                            : t('Add all {n}', ['n' => Format::number($availableTotal)])) . $arrowIcon('right'),
                        null,
                        true,
                        'link-button',
                    ) ?>
                </div>
            <?php endif; ?>

            <div class="table-wrap">
                <table class="grid grid--picker">
                    <caption class="sr-only"><?= $e(t('Master list: songs not yet in the room')) ?></caption>
                    <thead><tr><th scope="col"><?= $e(t('Title')) ?></th><th scope="col"><?= $e(t('Artist')) ?></th><th scope="col"><span class="sr-only"><?= $e(t('Add')) ?></span></th></tr></thead>
                    <tbody>
                    <?php foreach ($available as $row): ?>
                        <?= $card($row, 'room_songs_add', 'right', t('Add'), 'arrow-button') ?>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($pages > 1): ?>
                <nav class="pager" aria-label="<?= $e(t('Pages')) ?>">
                    <?php if ($pageNo > 1): ?>
                        <a href="<?= $e(url(['p' => 'room_songs', 'q' => $q, 'page' => $pageNo - 1])) ?>" rel="prev">&larr; <?= $e(t('back')) ?></a>
                    <?php endif; ?>
                    <span><?= $e(t('Page {page} of {pages}', ['page' => $pageNo, 'pages' => $pages])) ?></span>
                    <?php if ($pageNo < $pages): ?>
                        <a href="<?= $e(url(['p' => 'room_songs', 'q' => $q, 'page' => $pageNo + 1])) ?>" rel="next"><?= $e(t('next')) ?> &rarr;</a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <section class="picker__col" aria-labelledby="picker-room">
        <div class="picker__head">
            <h2 id="picker-room"><?= $e((string) $room['name']) ?></h2>
            <span class="muted"><?= $e(tn('{n} song in the room', '{n} songs in the room', $roomTotal, ['n' => Format::number($roomTotal)])) ?></span>
        </div>

        <?php if ($roomRows === []): ?>
            <p class="empty"><?= $e($q !== '' ? t('No song found. Try a different spelling?') : t('This room has no songs yet.')) ?></p>
        <?php else: ?>
            <?php if ($roomTotal > 1): ?>
                <div class="picker__bulk">
                    <?= $move(
                        'room_songs_remove',
                        $arrowIcon('left') . $e($q !== ''
                            ? tn('Remove the {n} song found', 'Remove all {n} songs found', $roomTotal, ['n' => Format::number($roomTotal)])
                            : t('Remove all {n}', ['n' => Format::number($roomTotal)])),
                        null,
                        true,
                        'link-button',
                        $q !== '' ? '' : t('Remove every song from this room? Wishes already received are kept.'),
                    ) ?>
                </div>
            <?php endif; ?>

            <div class="table-wrap">
                <table class="grid grid--picker grid--picker--room">
                    <caption class="sr-only"><?= $e(t('Songs in the room')) ?></caption>
                    <thead><tr><th scope="col"><span class="sr-only"><?= $e(t('Remove')) ?></span></th><th scope="col"><?= $e(t('Title')) ?></th><th scope="col"><?= $e(t('Artist')) ?></th></tr></thead>
                    <tbody>
                    <?php foreach ($roomRows as $row): ?>
                        <?= $card($row, 'room_songs_remove', 'left', t('Remove'), 'arrow-button arrow-button--remove') ?>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>

<script src="<?= $e(asset('assets/app.js')) ?>" defer></script>
