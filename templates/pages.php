<?php

declare(strict_types=1);

use Songwunsch\Format;

/**
 * Admin only: every page -- imprint, FAQ, privacy notice -- with its
 * address and the languages it is written in. Add, edit, delete; the footer
 * is arranged on its own page (Administration -> Footer). Below the search,
 * the fallback order of the languages: a reader gets a page in their own
 * language, otherwise in the first language of this order the page has.
 * The order changes by drag & drop (app.js, the wish list's code) or with
 * the move buttons of every row.
 *
 * @var array<int,array<string,mixed>> $rows  the pages by title, or those matching the search;
 *                                            each with title, lang (of that title) and languages (codes)
 * @var string $q                              the search, '' for all
 * @var string $csrf
 * @var array<string,string> $languages        code => native name
 * @var array<int,string> $order               the fallback order, codes
 */

$e    = static fn (?string $v): string => Format::e($v);
$last = count($order) - 1;
$name = static fn (string $code): string => $languages[$code] ?? strtoupper($code);
?>

<div class="panel__head">
    <div>
        <h1><?= $e(t('Pages')) ?></h1>
        <p class="muted">
            <?= $e($q !== '' ? tn('{n} page found.', '{n} pages found.', count($rows)) : tn('{n} page.', '{n} pages.', count($rows))) ?>
            <?= t('Every page is open to everyone under its address and may link to any other – for an imprint, FAQs or a privacy notice. Which pages the footer links, and in which order, is set under {footer}.', [
                'footer' => '<a href="' . $e(url(['p' => 'footer'])) . '">' . $e(t('Footer')) . '</a>',
            ]) ?>
        </p>
    </div>

    <div class="panel__actions">
        <a class="link-button" href="<?= $e(url(['p' => 'page_edit'])) ?>"><?= icon('plus') ?><?= $e(t('Add page')) ?></a>
    </div>
</div>

<form class="search" method="get" action="<?= $e(url(['p' => 'pages'])) ?>" role="search">
    <label class="sr-only" for="q"><?= $e(t('Search pages')) ?></label>
    <input type="search" id="q" name="q" value="<?= $e($q) ?>" placeholder="<?= $e(t('Title or machine name …')) ?>" autocomplete="off">
    <button type="submit"><?= $e(t('Search')) ?></button>
    <?php if ($q !== ''): ?>
        <a class="search__reset" href="<?= $e(url(['p' => 'pages'])) ?>"><?= $e(t('reset')) ?></a>
    <?php endif; ?>
</form>

<?php if ($rows === []): ?>
    <p class="empty"><?= $e($q !== '' ? t('No page found. Try a different spelling?') : t('No page yet.')) ?></p>
<?php else: ?>
<div class="table-wrap">
    <table class="grid grid--pages">
        <caption class="sr-only"><?= $e(t('Pages with their addresses and languages')) ?></caption>
        <thead>
        <tr>
            <th scope="col"><?= $e(t('Title')) ?></th>
            <th scope="col"><?= $e(t('Address')) ?></th>
            <th scope="col"><?= $e(t('Languages')) ?></th>
            <th scope="col"><?= $e(t('Last change')) ?></th>
            <th scope="col"><span class="sr-only"><?= $e(t('Actions')) ?></span></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
            <?php
            $title   = (string) $row['title'];
            $pageUrl = url(['p' => 'page', 'slug' => (string) $row['slug']]);
            $editUrl = url(['p' => 'page_edit', 'id' => (int) $row['id']]);
            ?>
            <tr>
                <td class="cell-title"<?= (string) $row['lang'] !== $translator->code() ? ' lang="' . $e((string) $row['lang']) . '"' : '' ?>><?= $e($title) ?></td>
                <td class="cell-genre"><a class="address" href="<?= $e($pageUrl) ?>"><?= $e($pageUrl) ?></a></td>
                <?php /* One chip per language of the menu, in the fallback order:
                         filled where the page has that language, dashed where
                         not. Each leads to that language's tab of the form. */ ?>
                <td class="cell-langs">
                    <?php foreach ($order as $code): ?>
                        <?php $has = in_array($code, (array) $row['languages'], true); ?>
                        <a class="tag <?= $has ? 'tag--gold' : 'tag--missing' ?>" href="<?= $e($editUrl) ?>#lang-<?= $e($code) ?>" title="<?= $e($name($code)) ?>"><?= $e(strtoupper($code)) ?><span class="sr-only">: <?= $e($name($code)) ?><?= $has ? '' : ', ' . $e(t('missing', [], 'language tab')) ?></span></a>
                    <?php endforeach; ?>
                </td>
                <td class="cell-length muted"><?= $e(t('changed {when}', ['when' => Format::moment((string) $row['updated_at'])])) ?></td>
                <?php /* Edit and Delete as icon buttons like on the user list. */ ?>
                <td class="cell-action">
                    <div class="row-actions">
                        <div class="row-actions__pair">
                            <a class="link-button icon-button" title="<?= $e(t('Edit')) ?>" href="<?= $e($editUrl) ?>">
                                <?= icon('pencil') ?>
                                <span class="button__label"><?= $e(t('Edit')) ?></span>
                                <span class="sr-only">: <?= $e($title) ?></span>
                            </a>
                            <form method="post" action="<?= $e(url()) ?>"
                                  data-confirm="<?= $e(t('Permanently delete page “{title}”?', ['title' => $title])) ?>">
                                <input type="hidden" name="a" value="page_delete">
                                <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                <button type="submit" class="delete-button icon-button" title="<?= $e(t('Delete')) ?>">
                                    <?= icon('trash') ?>
                                    <span class="button__label"><?= $e(t('Delete')) ?></span>
                                    <span class="sr-only">: <?= $e($title) ?></span>
                                </button>
                            </form>
                        </div>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php if (count($order) > 1): ?>
<section class="list-section" aria-labelledby="langorder-title">
    <div class="panel__head">
        <div>
            <h2 id="langorder-title"><?= $e(t('Fallback order of the languages')) ?></h2>
            <p class="muted"><?= $e(t('Readers get a page in the language they chose in the language menu. Where the page lacks it, they get the first language of this order the page has. Drag a row or use its arrows.')) ?></p>
        </div>
    </div>

    <?php /* Announcements of the drag & drop (saved, failed), for screen readers. */ ?>
    <p class="sr-only" role="status" id="reorder-status"></p>

    <div class="table-wrap">
        <table class="grid grid--picker grid--langs"
               data-reorder data-reorder-action="pages_languages_reorder" data-csrf="<?= $e($csrf) ?>"
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
                                    <input type="hidden" name="a" value="pages_languages_move">
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
