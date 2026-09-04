<?php

declare(strict_types=1);

/**
 * Pager below a paginated list: first, back, "Page x of y", next, last --
 * arrows as SVG glyphs so every link draws the same stroke. On phones the
 * words step back (style.css hides .pager__label visually, the name stays
 * for screen readers) and the arrows alone carry the links, so the pager
 * fits one row.
 * Links that would lead nowhere (first/back on page 1, next/last on the
 * final page) are left out. Rendered only when there is more than one page.
 *
 * Expects:
 * @var int      $pageNo
 * @var int      $pages
 * @var \Closure $pageUrl  fn (int $page): string – URL of a page of this list
 */

use Songwunsch\Format;

if ($pages <= 1) {
    return;
}
?>
<nav class="pager" aria-label="<?= Format::e(t('Pages')) ?>">
    <?php if ($pageNo > 1): ?>
        <a href="<?= Format::e($pageUrl(1)) ?>" rel="first"><?= icon('chevrons-left', 14) ?><span class="pager__label"><?= Format::e(t('first')) ?></span></a>
        <a href="<?= Format::e($pageUrl($pageNo - 1)) ?>" rel="prev"><?= icon('arrow-left', 14) ?><span class="pager__label"><?= Format::e(t('back')) ?></span></a>
    <?php endif; ?>
    <span><?= Format::e(t('Page {page} of {pages}', ['page' => $pageNo, 'pages' => $pages])) ?></span>
    <?php if ($pageNo < $pages): ?>
        <a href="<?= Format::e($pageUrl($pageNo + 1)) ?>" rel="next"><span class="pager__label"><?= Format::e(t('next')) ?></span><?= icon('arrow-right', 14, true) ?></a>
        <a href="<?= Format::e($pageUrl($pages)) ?>" rel="last"><span class="pager__label"><?= Format::e(t('last')) ?></span><?= icon('chevrons-right', 14, true) ?></a>
    <?php endif; ?>
</nav>
