<?php

declare(strict_types=1);

use Songwunsch\Format;

/**
 * Admin only: the footer, arranged like a room's song picker. Two columns:
 * left the pages the footer does not link, right the footer itself in its
 * order. The arrow buttons move a page across -- one click, one round trip,
 * no JavaScript needed. The order on the right changes by drag & drop
 * (app.js, the wish list's code) or with the four move buttons of every row.
 * Pages themselves are written under Pages.
 *
 * @var array<int,array<string,mixed>> $linked     pages in the footer, in order
 * @var array<int,array<string,mixed>> $available  pages outside the footer, by title
 * @var string $csrf
 */

$e    = static fn (?string $v): string => Format::e($v);
$last = count($linked) - 1;

/** Form with one button that moves a page into or out of the footer. */
$across = static function (string $action, int $id, string $glyph, string $verb, string $title, string $class) use ($csrf, $e): string {
    return '<form method="post" action="' . $e(url()) . '">'
        . '<input type="hidden" name="a" value="' . $e($action) . '">'
        . '<input type="hidden" name="csrf" value="' . $e($csrf) . '">'
        . '<input type="hidden" name="id" value="' . $id . '">'
        . '<button type="submit" class="' . $class . '">' . icon($glyph)
        . '<span class="sr-only">' . $e($verb) . ': ' . $e($title) . '</span></button></form>';
};
?>

<div class="panel__head">
    <div>
        <h1><?= $e(t('Footer')) ?></h1>
        <p class="muted">
            <?= $e(t('{n} of {total} pages are linked in the footer.', ['n' => count($linked), 'total' => count($linked) + count($available)])) ?>
            <?= $e(t('Move pages with the arrows: to the right into the footer, to the left out of it. On the right, drag a row or use its arrows to change the order.')) ?>
        </p>
    </div>
    <div class="panel__actions">
        <a class="link-button" href="<?= $e(url(['p' => 'pages'])) ?>"><?= icon('page') ?><?= $e(t('Pages')) ?></a>
    </div>
</div>

<?php /* Announcements of the drag & drop (saved, failed), for screen readers. */ ?>
<p class="sr-only" role="status" id="reorder-status"></p>

<div class="picker">
    <section class="picker__col" aria-labelledby="picker-pages">
        <div class="picker__head">
            <h2 id="picker-pages"><?= $e(t('Pages')) ?></h2>
            <span class="muted"><?= $e(tn('{n} page available', '{n} pages available', count($available))) ?></span>
        </div>

        <?php if ($available === []): ?>
            <p class="empty"><?= $e($linked === [] ? t('No page yet – write one under Pages first.') : t('Every page is in the footer.')) ?></p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="grid grid--picker">
                    <caption class="sr-only"><?= $e(t('Pages not linked in the footer')) ?></caption>
                    <thead><tr><th scope="col"><?= $e(t('Title')) ?></th><th scope="col"><?= $e(t('Address')) ?></th><th scope="col"><span class="sr-only"><?= $e(t('Add')) ?></span></th></tr></thead>
                    <tbody>
                    <?php foreach ($available as $row): ?>
                        <?php $title = (string) $row['title']; ?>
                        <tr>
                            <td class="cell-title"><?= $e($title) ?></td>
                            <td class="cell-artist"><?= $e(url(['p' => 'page', 'slug' => (string) $row['slug']])) ?></td>
                            <td class="cell-action"><?= $across('footer_add', (int) $row['id'], 'arrow-right', t('Add'), $title, 'arrow-button') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="picker__col" aria-labelledby="picker-footer">
        <div class="picker__head">
            <h2 id="picker-footer"><?= $e(t('Footer')) ?></h2>
            <span class="muted"><?= $e(tn('{n} page linked', '{n} pages linked', count($linked))) ?></span>
        </div>

        <?php if ($linked === []): ?>
            <p class="empty"><?= $e(t('Nothing yet – the footer stays empty until a page is moved here.')) ?></p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="grid grid--picker grid--picker--room grid--picker--footer"
                       data-reorder data-reorder-action="footer_reorder" data-csrf="<?= $e($csrf) ?>"
                       data-msg-saved="<?= $e(t('Order saved.')) ?>"
                       data-msg-failed="<?= $e(t('The order could not be saved.')) ?>"
                       data-msg-offline="<?= $e(t('The order could not be saved – please reload the page.')) ?>">
                    <caption class="sr-only"><?= $e(t('Footer links in their order, changeable by drag & drop or arrow buttons')) ?></caption>
                    <thead><tr><th scope="col"><span class="sr-only"><?= $e(t('Remove')) ?></span></th><th scope="col"><?= $e(t('No.')) ?></th><th scope="col"><?= $e(t('Title')) ?></th><th scope="col"><?= $e(t('Address')) ?></th><th scope="col"><span class="sr-only"><?= $e(t('Order')) ?></span></th></tr></thead>
                    <tbody>
                    <?php foreach ($linked as $index => $row): ?>
                        <?php $title = (string) $row['title']; ?>
                        <tr data-id="<?= (int) $row['id'] ?>" draggable="true">
                            <td class="cell-action"><?= $across('footer_remove', (int) $row['id'], 'arrow-left', t('Remove'), $title, 'arrow-button arrow-button--remove') ?></td>
                            <td class="cell-rank">
                                <span class="rank"><span class="drag-grip" aria-hidden="true">⠿</span><?= (int) $index + 1 ?></span>
                            </td>
                            <td class="cell-title"><?= $e($title) ?></td>
                            <td class="cell-artist"><?= $e(url(['p' => 'page', 'slug' => (string) $row['slug']])) ?></td>
                            <?php /* The wish list's four moves: to the very top, one up, one
                                     down, to the very bottom (on phones a 2x2 block). app.js
                                     reads data-move to keep the disabled states right after
                                     a drag. */ ?>
                            <td class="cell-move">
                                <div class="row-actions">
                                    <span class="move">
                                        <?php
                                        $moves = [
                                            'top'    => [icon('to-top', 12), t('Move {label} to the top', ['label' => $title]), $index === 0],
                                            'up'     => [icon('up', 12), t('Move {label} up', ['label' => $title]), $index === 0],
                                            'down'   => [icon('down', 12), t('Move {label} down', ['label' => $title]), $index === $last],
                                            'bottom' => [icon('to-bottom', 12), t('Move {label} to the bottom', ['label' => $title]), $index === $last],
                                        ];
                                        foreach ($moves as $dir => [$glyph, $text, $disabled]):
                                        ?>
                                        <form method="post" action="<?= $e(url()) ?>" class="move__<?= $dir ?>">
                                            <input type="hidden" name="a" value="footer_move">
                                            <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                                            <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                            <input type="hidden" name="dir" value="<?= $dir ?>">
                                            <button type="submit" class="move-button" data-move="<?= $dir ?>" title="<?= $e($text) ?>"<?= $disabled ? ' disabled' : '' ?>>
                                                <?= $glyph ?>
                                                <span class="sr-only"><?= $e($text) ?></span>
                                            </button>
                                        </form>
                                        <?php endforeach; ?>
                                    </span>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>
