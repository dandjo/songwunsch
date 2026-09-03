<?php

declare(strict_types=1);

/**
 * Sort bar for narrow screens: on a phone the tables become cards, so the
 * column headers are not available as sort switches.
 *
 * Expects:
 * @var array<string,string> $sortbarItems  sort key => label
 * @var string               $sortbarPage   page for the links ('songs'|'wishes')
 * @var array<string,mixed>  $sortbarExtra  additional parameters (e.g. the query)
 * @var string               $sort
 * @var string               $dir
 */

use Songwunsch\Format;

$sortbarExtra ??= [];
?>
<nav class="sortbar" aria-label="<?= Format::e(t('Sorting')) ?>">
    <span class="sortbar__label"><?= Format::e(t('Sort:')) ?></span>
    <?php foreach ($sortbarItems as $key => $label): ?>
        <?php
        $active  = $sort === $key;
        $nextDir = $active && $dir === 'asc' ? 'desc' : 'asc';
        $href    = url(array_merge($sortbarExtra, ['p' => $sortbarPage, 'sort' => $key, 'dir' => $nextDir]));
        ?>
        <a class="sortbar__item<?= $active ? ' is-active' : '' ?>" href="<?= Format::e($href) ?>">
            <?= Format::e($label) ?>
            <?php if ($active): ?>
                <span aria-hidden="true"><?= $dir === 'asc' ? '▲' : '▼' ?></span>
                <span class="sr-only"><?= Format::e($dir === 'asc' ? t(', currently ascending, reverse') : t(', currently descending, reverse')) ?></span>
            <?php endif; ?>
        </a>
    <?php endforeach; ?>
</nav>
