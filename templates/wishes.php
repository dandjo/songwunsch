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
                <strong><?= $e(t('Wishing is paused')) ?></strong> <?= $e(t('– the audience cannot submit anything right now.')) ?>
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

    <?php if ($canEdit): ?>
        <div class="panel__actions">
            <form method="post" action="<?= $e(url()) ?>">
                <input type="hidden" name="a" value="pause">
                <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                <input type="hidden" name="state" value="<?= $paused ? '0' : '1' ?>">
                <button type="submit" class="link-button" aria-pressed="<?= $paused ? 'true' : 'false' ?>">
                    <?php /* Pause bars while wishing is open, a play triangle while it is paused. */ ?>
                    <?php if ($paused): ?>
                        <?= icon('play') ?>
                    <?php else: ?>
                        <?= icon('pause') ?>
                    <?php endif; ?>
                    <?= $e($paused ? t('Resume wishing') : t('Pause wishing')) ?>
                </button>
            </form>
            <?php if ($rows !== []): ?>
                <?php if (!$manual): ?>
                    <a class="link-button" href="<?= $e(url(['p' => 'wishes'])) ?>"><?= icon('list') ?><?= $e(t('Manual order')) ?></a>
                <?php endif; ?>
                <form method="post" action="<?= $e(url()) ?>" data-confirm="<?= $e(t('Really delete all wishes?')) ?>">
                    <input type="hidden" name="a" value="clear">
                    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                    <button type="submit" class="danger-button"><?= icon('trash') ?><?= $e(t('Clear list')) ?></button>
                </form>
            <?php endif; ?>
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
                <?= $e(t('Received wishes')) ?><?= $e($sortable ? t(' in manual order, changeable by drag & drop or arrow buttons') : ($manual ? '' : t(', sorted'))) ?>
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
                <?= $th('time', t('Received')) ?>
                <?= $th('artist', t('Artist')) ?>
                <?= $th('title', t('Title')) ?>
                <?= $th('length', t('Length')) ?>
                <?= $th('genre', t('Genre')) ?>
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
                        <?php if ($sortable): ?>
                            <span class="move">
                                <form method="post" action="<?= $e(url()) ?>">
                                    <input type="hidden" name="a" value="move">
                                    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                                    <input type="hidden" name="back" value="<?= $e($current) ?>">
                                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                    <input type="hidden" name="dir" value="up">
                                    <button type="submit" class="move-button"<?= $index === 0 ? ' disabled' : '' ?>>
                                        <span aria-hidden="true">▲</span>
                                        <span class="sr-only"><?= $e(t('Move {label} up', ['label' => $label])) ?></span>
                                    </button>
                                </form>
                                <form method="post" action="<?= $e(url()) ?>">
                                    <input type="hidden" name="a" value="move">
                                    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                                    <input type="hidden" name="back" value="<?= $e($current) ?>">
                                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                    <input type="hidden" name="dir" value="down">
                                    <button type="submit" class="move-button"<?= $index === $last ? ' disabled' : '' ?>>
                                        <span aria-hidden="true">▼</span>
                                        <span class="sr-only"><?= $e(t('Move {label} down', ['label' => $label])) ?></span>
                                    </button>
                                </form>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="cell-time">
                        <time datetime="<?= $e(str_replace(' ', 'T', (string) $row['created_at'])) ?>">
                            <?= $e(Format::moment((string) $row['created_at'])) ?>
                        </time>
                        <span class="muted ago"><?= $e(Format::ago((string) $row['created_at'])) ?></span>
                    </td>
                    <td class="cell-artist"><?= $e((string) $row['artist']) ?></td>
                    <td class="cell-title"><?= $e((string) $row['title']) ?></td>
                    <td class="cell-length"><?= $e(Format::length($row['length_sec'])) ?></td>
                    <td class="cell-genre">
                        <?php if (($row['genre'] ?? '') !== ''): ?>
                            <span class="tag"><?= $e((string) $row['genre']) ?></span>
                        <?php else: ?>
                            <span class="muted">–</span>
                        <?php endif; ?>
                    </td>
                    <?php if ($canEdit): ?>
                        <td class="cell-action">
                            <form method="post" action="<?= $e(url()) ?>"<?php if ($settings->confirmsDelete('wishes')): ?> data-confirm="<?= $e(t('Remove “{title}” from the list?', ['title' => (string) $row['title']])) ?>"<?php endif; ?>>
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
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php if ($canEdit): ?>
    <script src="<?= $e(asset('assets/app.js')) ?>" defer></script>
<?php endif; ?>
