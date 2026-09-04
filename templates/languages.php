<?php

declare(strict_types=1);

use Songwunsch\Format;

/**
 * Admin only: the languages of the interface and their fallback order. The
 * languages themselves come from lang/*.po -- a new file is a new language,
 * nothing to set here. What the admins arrange is the order in which a
 * reader gets a page (or the footer line) that is missing in their own
 * language: the first language of this order the page has. The order
 * changes by drag & drop (app.js, the wish list's code) or with the move
 * buttons of every row.
 *
 * @var array<string,string> $languages  code => native name
 * @var array<int,string> $order         the fallback order, codes
 * @var string $csrf
 */

$e    = static fn (?string $v): string => Format::e($v);
$last = count($order) - 1;
$name = static fn (string $code): string => $languages[$code] ?? strtoupper($code);
?>

<div class="panel__head">
    <div>
        <h1><?= $e(t('Languages')) ?></h1>
        <p class="muted">
            <?= $e(tn('{n} language is available.', '{n} languages are available.', count($order))) ?>
            <?= $e(t('Readers get pages and the footer line in the language they chose in the language menu. Where a text lacks it, they get the first language of this order the text has.')) ?>
            <?= $e(t('A language is a file in the lang folder – see the README to add one.')) ?>
        </p>
    </div>
</div>

<?php if (count($order) < 2): ?>
    <p class="empty"><?= $e(t('With one language there is nothing to order.')) ?></p>
<?php else: ?>
<section class="list-section" aria-labelledby="langorder-title">
    <div class="panel__head">
        <div>
            <h2 id="langorder-title"><?= $e(t('Fallback order of the languages')) ?></h2>
            <p class="muted"><?= $e(t('Drag a row or use its arrows.')) ?></p>
        </div>
    </div>

    <?php /* Announcements of the drag & drop (saved, failed), for screen readers. */ ?>
    <p class="sr-only" role="status" id="reorder-status"></p>

    <div class="table-wrap">
        <table class="grid grid--picker grid--langs"
               data-reorder data-reorder-action="languages_reorder" data-csrf="<?= $e($csrf) ?>"
               data-msg-saved="<?= $e(t('Order saved.')) ?>"
               data-msg-failed="<?= $e(t('The order could not be saved.')) ?>"
               data-msg-offline="<?= $e(t('The order could not be saved – please reload the page.')) ?>">
            <caption class="sr-only"><?= $e(t('Languages in their fallback order, changeable by drag & drop or arrow buttons')) ?></caption>
            <thead><tr><th scope="col"><?= $e(t('No.')) ?></th><th scope="col"><?= $e(t('Language')) ?></th><th scope="col"><span class="sr-only"><?= $e(t('Order')) ?></span></th></tr></thead>
            <tbody>
            <?php foreach ($order as $index => $code): ?>
                <?php $label = $name($code); ?>
                <tr data-id="<?= $e($code) ?>" draggable="true">
                    <td class="cell-rank">
                        <span class="rank"><span class="drag-grip" aria-hidden="true">⠿</span><?= (int) $index + 1 ?></span>
                    </td>
                    <td class="cell-title"><span lang="<?= $e($code) ?>"><?= $e($label) ?></span> <span class="muted"><?= $e(strtoupper($code)) ?></span></td>
                    <?php /* The wish list's four moves: to the very top, one up, one
                             down, to the very bottom. app.js reads data-move to keep
                             the disabled states right after a drag. */ ?>
                    <td class="cell-move">
                        <div class="row-actions">
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
                                    <input type="hidden" name="a" value="languages_move">
                                    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                                    <input type="hidden" name="code" value="<?= $e($code) ?>">
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
</section>
<?php endif; ?>
