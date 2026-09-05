<?php

declare(strict_types=1);

use Songwunsch\Format;

/**
 * Admin only: every page -- imprint, FAQ, privacy notice -- with its
 * address and the languages it is written in. Add, edit, delete; the footer
 * is arranged on its own page (Administration -> Footer), the fallback
 * order of the languages, which the chips follow, under Administration ->
 * Languages.
 *
 * @var array<int,array<string,mixed>> $rows  one page of the pages by title, or of those matching the search;
 *                                            each with title, lang (of that title) and languages (codes)
 * @var int $total                             all of them
 * @var int $pageNo
 * @var int $pages
 * @var string $q                              the search, '' for all
 * @var string $csrf
 * @var array<string,string> $languages        code => native name
 * @var array<int,string> $order               the fallback order, codes (Administration -> Languages)
 */

$e    = static fn (?string $v): string => Format::e($v);
$name = static fn (string $code): string => $languages[$code] ?? strtoupper($code);
// This list with its search and page: where the forms lead back to.
$current = url(['p' => 'pages', 'q' => $q, 'page' => $pageNo > 1 ? $pageNo : null]);
?>

<div class="panel__head">
    <div>
        <div class="panel__title">
            <h1><?= $e(t('Pages')) ?></h1>
            <?= help_button('help-pages') ?>
        </div>
        <p class="muted help" id="help-pages">
            <?= $e($q !== '' ? tn('{n} page found.', '{n} pages found.', $total) : tn('{n} page.', '{n} pages.', $total)) ?>
            <?= t('Every page is open to everyone under its address and may link to any other – for an imprint, FAQs or a privacy notice. Which pages the footer links, and in which order, is set under {footer}; the order of the language chips is the fallback order set under {languages}.', [
                'footer'    => '<a href="' . $e(url(['p' => 'footer'])) . '">' . $e(t('Footer')) . '</a>',
                'languages' => '<a href="' . $e(url(['p' => 'languages'])) . '">' . $e(t('Languages')) . '</a>',
            ]) ?>
        </p>
    </div>

    <div class="panel__actions">
        <a class="link-button" href="<?= $e(url(['p' => 'page_edit', 'back' => $current])) ?>"><?= icon('plus') ?><?= $e(t('Add page')) ?></a>
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
            $address = url(['p' => 'page', 'slug' => (string) $row['slug']]);
            $editUrl = url(['p' => 'page_edit', 'id' => (int) $row['id'], 'back' => $current]);
            ?>
            <tr>
                <td class="cell-title"<?= (string) $row['lang'] !== $translator->code() ? ' lang="' . $e((string) $row['lang']) . '"' : '' ?>><?= $e($title) ?></td>
                <td class="cell-genre"><a class="address" href="<?= $e($address) ?>"><?= $e($address) ?></a></td>
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
                                <input type="hidden" name="back" value="<?= $e($current) ?>">
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

<?php
$pageUrl = static fn (int $page): string => url(['p' => 'pages', 'q' => $q, 'page' => $page > 1 ? $page : null]);
require __DIR__ . '/_pager.php';
?>
<?php endif; ?>
