<?php

declare(strict_types=1);

use Songwunsch\Format;

/** @var array<int,array<string,mixed>> $rows */
/** @var string $sort */
/** @var string $dir */
/** @var string $csrf */
/** @var \Songwunsch\Settings $settings  delete confirmation switches */
/** @var bool $paused   wishing closed by the moderator */
/** @var bool $canEdit  moderator or admin: controls, sorting, drag & drop */
/** @var array<string,mixed> $room  current room */
/** @var \Songwunsch\Security $security */

$e       = static fn (?string $v): string => Format::e($v);
$inRoom  = (int) $room['id'] !== \Songwunsch\RoomRepository::DEFAULT_ID;
$current = url(['p' => 'wishes', 'sort' => $sort, 'dir' => $dir]);

// Reordering only makes sense in manual order -- with any other sorting the
// display would not follow the stored rank. Guests always see manual order.
$manual = $sort === 'manual' && $dir === 'asc';
$last   = count($rows) - 1;

$th = static function (string $key, string $label) use ($sort, $dir, $e, $canEdit): string {
    if (!$canEdit) {
        return '<th scope="col">' . $e($label) . '</th>';
    }

    $active   = $sort === $key;
    $nextDir  = $active && $dir === 'asc' ? 'desc' : 'asc';
    $ariaSort = $active ? ($dir === 'asc' ? 'ascending' : 'descending') : 'none';
    $arrow    = $active ? ($dir === 'asc' ? '▲' : '▼') : '';
    $href     = url(['p' => 'wishes', 'sort' => $key, 'dir' => $nextDir]);

    return '<th scope="col" aria-sort="' . $ariaSort . '">'
        . '<a class="sort' . ($active ? ' sort--active' : '') . '" href="' . $e($href) . '">'
        . $e($label)
        . '<span class="sort__arrow" aria-hidden="true">' . $arrow . '</span>'
        . '<span class="sr-only">' . $e($nextDir === 'asc' ? t(', sort ascending') : t(', sort descending')) . '</span>'
        . '</a></th>';
};
?>

<div class="panel__head">
    <div>
        <h1><?= $e(t('Wish list')) ?><?= $inRoom ? ' <span class="muted">· ' . $e((string) $room['name']) . '</span>' : '' ?></h1>
        <p class="muted">
            <?= $e(tn('{n} wish in the queue.', '{n} wishes in the queue.', count($rows))) ?>
            <?php if ($paused): ?>
                <strong><?= $e(t('The room is closed')) ?></strong> <?= $e(t('– the audience cannot wish or suggest anything right now.')) ?>
                <?php if ($canEdit): ?>
                    <?= t('Open it in the notice above or under {rooms}.', [
                        'rooms' => '<a href="' . $e(url(['p' => 'rooms'])) . '">' . $e(t('Rooms')) . '</a>',
                    ]) ?>
                <?php endif; ?>
            <?php endif; ?>
            <?php if (!$canEdit): ?>
                <?= $e(t('The wishes in the order they will be played.')) ?>
            <?php elseif ($manual): ?>
                <?= $e(t('Change the order with the arrows – on a desktop also by drag & drop. Sorting only changes the view.')) ?>
            <?php else: ?>
                <?= t('To reorder, switch to the {manual}.', [
                    'manual' => '<a href="' . $e(url(['p' => 'wishes'])) . '">' . $e(t('manual order')) . '</a>',
                ]) ?>
            <?php endif; ?>
        </p>
    </div>

    <?php /* Closing and opening the room happens in the room list and in the
             header notice, not here. */ ?>
    <?php if ($canEdit && $rows !== []): ?>
        <div class="panel__actions">
            <?php if (!$manual): ?>
                <a class="link-button" href="<?= $e(url(['p' => 'wishes'])) ?>"><?= icon('list') ?><?= $e(t('Manual order')) ?></a>
            <?php endif; ?>
            <form method="post" action="<?= $e(url()) ?>" data-confirm="<?= $e(t('Really delete all wishes?')) ?>">
                <input type="hidden" name="a" value="clear">
                <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                <button type="submit" class="danger-button"><?= icon('trash') ?><?= $e(t('Clear list')) ?></button>
            </form>
        </div>
    <?php endif; ?>
</div>

<?php if ($rows === []): ?>
    <p class="empty"><?= $e(t('No wishes yet. The start page is waiting for an audience.')) ?></p>
<?php else: ?>
    <?php if ($canEdit): ?>
        <p class="sr-only" role="status" id="reorder-status"></p>

        <?php
        $sortbarItems = [
            'manual' => t('Order'),
            'time'   => t('Received'),
            'artist' => t('Artist'),
            'title'  => t('Title'),
            'length' => t('Length'),
            'genre'  => t('Genre'),
            'wisher' => t('From'),
        ];
        $sortbarPage  = 'wishes';
        $sortbarExtra = [];
        require __DIR__ . '/_sortbar.php';
        ?>
    <?php endif; ?>

    <?php $sortable = $canEdit && $manual; ?>
    <div class="table-wrap">
        <table class="grid grid--wishes<?= $sortable ? ' grid--sortable' : '' ?>"
               <?= $sortable ? 'data-reorder data-csrf="' . $e($csrf) . '"' : '' ?>
               data-msg-saved="<?= $e(t('Order saved.')) ?>"
               data-msg-failed="<?= $e(t('The order could not be saved.')) ?>"
               data-msg-offline="<?= $e(t('The order could not be saved – please reload the page.')) ?>">
            <caption class="sr-only">
                <?= $e(t('Received wishes, with the name of whoever wished')) ?><?= $e($sortable ? t(' in manual order, changeable by drag & drop or arrow buttons') : ($manual ? '' : t(', sorted'))) ?>
            </caption>
            <thead>
            <tr>
                <th scope="col" class="cell-rank" aria-sort="<?= $manual ? 'ascending' : 'none' ?>">
                    <?php if ($canEdit): ?>
                        <a class="sort<?= $manual ? ' sort--active' : '' ?>" href="<?= $e(url(['p' => 'wishes'])) ?>">
                            <span aria-hidden="true">#</span>
                            <span class="sr-only"><?= $e(t('Position, restore manual order')) ?></span>
                        </a>
                    <?php else: ?>
                        <span aria-hidden="true">#</span><span class="sr-only"><?= $e(t('Position')) ?></span>
                    <?php endif; ?>
                </th>
                <?= $th('artist', t('Artist')) ?>
                <?= $th('title', t('Title')) ?>
                <?= $th('length', t('Length')) ?>
                <?= $th('genre', t('Genre')) ?>
                <?php /* Time received and who wished share one cell: stacked
                         right-aligned on wide screens, side by side on phones.
                         The sort bar above offers both sortings. */ ?>
                <th scope="col"><?= $e(t('Received')) ?>, <?= $e(t('From')) ?></th>
                <th scope="col" class="cell-count"><span class="sr-only"><?= $e(t('Times wished')) ?></span></th>
                <?php if ($canEdit): ?>
                    <th scope="col"><span class="sr-only"><?= $e(t('Actions')) ?></span></th>
                <?php endif; ?>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $index => $row): ?>
                <?php $label = t('{title} by {artist}', ['title' => (string) $row['title'], 'artist' => (string) $row['artist']]); ?>
                <tr data-id="<?= (int) $row['id'] ?>"<?= $sortable ? ' draggable="true"' : '' ?>>
                    <td class="cell-rank">
                        <span class="rank"><span class="drag-grip" aria-hidden="true">⠿</span><?= (int) $index + 1 ?></span>
                    </td>
                    <td class="cell-artist"><?= $e((string) $row['artist']) ?></td>
                    <td class="cell-title"><?= $e((string) $row['title']) ?></td>
                    <td class="cell-length"><?= $e(Format::length($row['length_sec'])) ?></td>
                    <td class="cell-genre">
                        <?php if (($row['genre'] ?? '') !== ''): ?>
                            <span class="tag"><?= $e((string) $row['genre']) ?></span>
                        <?php endif; ?>
                    </td>
                    <?php /* Time received and, below or beside it, who wished --
                             the name the guest gave, if any. */ ?>
                    <td class="cell-meta">
                        <span class="cell-time">
                            <?= icon('clock', 14) ?><time datetime="<?= $e(str_replace(' ', 'T', (string) $row['created_at'])) ?>">
                                <?= $e(Format::moment((string) $row['created_at'])) ?>
                            </time>
                            <span class="muted ago"><?= $e(Format::ago((string) $row['created_at'])) ?></span>
                        </span>
                        <?php if (($row['wisher'] ?? '') !== ''): ?>
                            <span class="cell-wisher">
                                <?= icon('user', 14) ?><span class="sr-only"><?= $e(t('From')) ?> </span><span class="cell-wisher__name"><?= $e((string) $row['wisher']) ?></span>
                            </span>
                        <?php else: ?>
                            <span class="sr-only"><?= $e(t('No name given')) ?></span>
                        <?php endif; ?>
                    </td>
                    <?php /* How often the song was wished while this entry has been
                             open -- for everyone, the audience included. A first wish
                             shows nothing; the badge appears from the second on. The
                             cell is always there so the card's columns stay put. */ ?>
                    <?php $wished = (int) ($row['wished'] ?? 1); ?>
                    <td class="cell-count">
                        <?php if ($wished > 1): ?>
                            <?php $wishedText = tn('wished {n} time', 'wished {n} times', $wished); ?>
                            <span class="wish-count" title="<?= $e($wishedText) ?>">
                                <span aria-hidden="true"><?= $wished ?>×</span>
                                <span class="sr-only"><?= $e($wishedText) ?></span>
                            </span>
                        <?php endif; ?>
                    </td>
                    <?php if ($canEdit): ?>
                        <?php /* One stack at the right edge, spanning the whole card:
                                 the move buttons (manual order only) above Delete. */ ?>
                        <td class="cell-action">
                            <div class="row-actions">
                            <?php if ($sortable): ?>
                                <?php /* Four moves in one row: to the very top, one up, one
                                         down, to the very bottom. On phones a 2x2 block: the
                                         "to the very ..." buttons move under their arrow
                                         (style.css, by the form's class). app.js reads
                                         data-move to keep the disabled states right after a
                                         drag. */ ?>
                                <span class="move">
                                    <?php
                                    $moves = [
                                        'top'    => [icon('to-top', 12), t('Move {label} to the top', ['label' => $label]), $index === 0],
                                        'up'     => [icon('up', 12), t('Move {label} up', ['label' => $label]), $index === 0],
                                        'down'   => [icon('down', 12), t('Move {label} down', ['label' => $label]), $index === $last],
                                        'bottom' => [icon('to-bottom', 12), t('Move {label} to the bottom', ['label' => $label]), $index === $last],
                                    ];
                                    foreach ($moves as $dir => [$glyph, $text, $disabled]):
                                    ?>
                                    <form method="post" action="<?= $e(url()) ?>" class="move__<?= $dir ?>">
                                        <input type="hidden" name="a" value="move">
                                        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                                        <input type="hidden" name="back" value="<?= $e($current) ?>">
                                        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                        <input type="hidden" name="dir" value="<?= $dir ?>">
                                        <button type="submit" class="move-button" data-move="<?= $dir ?>" title="<?= $e($text) ?>"<?= $disabled ? ' disabled' : '' ?>>
                                            <?= $glyph ?>
                                            <span class="sr-only"><?= $e($text) ?></span>
                                        </button>
                                    </form>
                                    <?php endforeach; ?>
                                </span>
                            <?php endif; ?>
                            <form method="post" action="<?= $e(url()) ?>"<?php if ($settings->confirmsDelete((int) ($security->user()['id'] ?? 0), 'wishes')): ?> data-confirm="<?= $e(t('Remove “{title}” from the list?', ['title' => (string) $row['title']])) ?>"<?php endif; ?>>
                                <input type="hidden" name="a" value="delete">
                                <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                                <input type="hidden" name="back" value="<?= $e($current) ?>">
                                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                <button type="submit" class="delete-button icon-button" title="<?= $e(t('Delete')) ?>">
                                    <?= icon('trash') ?>
                                    <span class="button__label"><?= $e(t('Delete')) ?></span>
                                    <span class="sr-only">: <?= $e($label) ?></span>
                                </button>
                            </form>
                            </div>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
