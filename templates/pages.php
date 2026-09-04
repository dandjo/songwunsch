<?php

declare(strict_types=1);

use Songwunsch\Format;

/**
 * Admin only: every page -- imprint, FAQ, privacy notice -- with its
 * address. Add, edit, delete; the footer is arranged on its own page
 * (Administration -> Footer).
 *
 * @var array<int,array<string,mixed>> $rows  the pages, by title
 * @var string $csrf
 */

$e = static fn (?string $v): string => Format::e($v);
?>

<div class="panel__head">
    <div>
        <h1><?= $e(t('Pages')) ?></h1>
        <p class="muted">
            <?= $e(tn('{n} page.', '{n} pages.', count($rows))) ?>
            <?= t('Every page is open to everyone under its address and may link to any other – for an imprint, FAQs or a privacy notice. Which pages the footer links, and in which order, is set under {footer}.', [
                'footer' => '<a href="' . $e(url(['p' => 'footer'])) . '">' . $e(t('Footer')) . '</a>',
            ]) ?>
        </p>
    </div>

    <div class="panel__actions">
        <a class="link-button" href="<?= $e(url(['p' => 'page_edit'])) ?>"><?= icon('plus') ?><?= $e(t('Add page')) ?></a>
    </div>
</div>

<?php if ($rows === []): ?>
    <p class="empty"><?= $e(t('No page yet.')) ?></p>
<?php else: ?>
<div class="table-wrap">
    <table class="grid grid--pages">
        <caption class="sr-only"><?= $e(t('Pages with their addresses')) ?></caption>
        <thead>
        <tr>
            <th scope="col"><?= $e(t('Title')) ?></th>
            <th scope="col"><?= $e(t('Address')) ?></th>
            <th scope="col"><?= $e(t('Last change')) ?></th>
            <th scope="col"><span class="sr-only"><?= $e(t('Actions')) ?></span></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
            <?php
            $title   = (string) $row['title'];
            $pageUrl = url(['p' => 'page', 'slug' => (string) $row['slug']]);
            ?>
            <tr>
                <td class="cell-title"><?= $e($title) ?></td>
                <td class="cell-genre"><a class="address" href="<?= $e($pageUrl) ?>"><?= $e($pageUrl) ?></a></td>
                <td class="cell-length muted"><?= $e(t('changed {when}', ['when' => Format::moment((string) $row['updated_at'])])) ?></td>
                <?php /* Edit and Delete as icon buttons like on the user list. */ ?>
                <td class="cell-action">
                    <div class="row-actions">
                        <div class="row-actions__pair">
                            <a class="link-button icon-button" title="<?= $e(t('Edit')) ?>" href="<?= $e(url(['p' => 'page_edit', 'id' => (int) $row['id']])) ?>">
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
