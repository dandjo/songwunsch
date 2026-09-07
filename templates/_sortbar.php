<?php

declare(strict_types=1);

/**
 * Sort bar: the tables are cards on every screen size, so the column
 * headers are not available as sort switches. On wide screens a row of
 * chips, one tap each; on phones (CSS) the same choices as a popout like
 * the room switcher -- "Sort: <current> ▲" in one line, the list beneath
 * it -- so the row does not wrap over two or three lines. Both are rendered,
 * CSS shows one (sortbar--switching; the room list's filter uses the chip
 * row alone). The active choice, tapped again, reverses the direction.
 *
 * Expects:
 * @var array<string,string> $sortbarItems  sort key => label
 * @var string               $sortbarRoute  route for the links ('songs'|'wishes')
 * @var array<string,mixed>  $sortbarExtra  additional parameters (e.g. the query)
 * @var string               $sort
 * @var string               $dir
 */

use Songwunsch\Format;

$sortbarExtra ??= [];
$e = static fn (?string $v): string => Format::e($v);

$sortbarLinks = [];
foreach ($sortbarItems as $key => $label) {
    $active = $sort === $key;
    $sortbarLinks[$key] = [
        'label'  => $label,
        'active' => $active,
        'href'   => url($sortbarRoute, array_merge($sortbarExtra, ['sort' => $key, 'dir' => $active && $dir === 'asc' ? 'desc' : 'asc'])),
    ];
}
$arrow   = $dir === 'asc' ? '▲' : '▼';
$reverse = $dir === 'asc' ? t(', currently ascending, reverse') : t(', currently descending, reverse');
$current = $sortbarItems[$sort] ?? (string) reset($sortbarItems);
?>
<nav class="sortbar sortbar--switching" aria-label="<?= $e(t('Sorting')) ?>">
    <div class="sortbar__chips">
        <span class="sortbar__label"><?= $e(t('Sort:')) ?></span>
        <?php foreach ($sortbarLinks as $link): ?>
            <a class="sortbar__item<?= $link['active'] ? ' is-active' : '' ?>" href="<?= $e($link['href']) ?>">
                <?= $e($link['label']) ?>
                <?php if ($link['active']): ?>
                    <span aria-hidden="true"><?= $arrow ?></span>
                    <span class="sr-only"><?= $e($reverse) ?></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
    <details class="sortbar__menu">
        <summary class="sortbar__toggle">
            <span class="sortbar__label"><?= $e(t('Sort:')) ?></span>
            <span class="sortbar__current"><?= $e($current) ?> <span aria-hidden="true"><?= $arrow ?></span></span>
            <svg class="sortbar__chevron" viewBox="0 0 16 16" width="12" height="12" aria-hidden="true" focusable="false">
                <path d="M3 6l5 5 5-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </summary>
        <ul class="sortbar__panel" role="list">
            <?php foreach ($sortbarLinks as $link): ?>
                <li>
                    <a class="sortbar__option<?= $link['active'] ? ' is-active' : '' ?>" href="<?= $e($link['href']) ?>"<?= $link['active'] ? ' aria-current="true"' : '' ?>>
                        <span class="sortbar__option-label"><?= $e($link['label']) ?></span>
                        <?php if ($link['active']): ?>
                            <span aria-hidden="true"><?= $arrow ?></span>
                            <span class="sr-only"><?= $e($reverse) ?></span>
                        <?php endif; ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </details>
</nav>
