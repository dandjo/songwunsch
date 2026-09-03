<?php

declare(strict_types=1);

use Songwunsch\Format;
use Songwunsch\UserRepository;

/** @var array<int,array<string,mixed>> $rows */
/** @var int $selfId  id of the logged-in admin */
/** @var string $csrf */

$e = static fn (?string $v): string => Format::e($v);
?>

<div class="panel__head">
    <div>
        <h1><?= $e(t('Users')) ?></h1>
        <p class="muted">
            <?= $e(tn('{n} user.', '{n} users.', count($rows))) ?>
            <?= t('{editor} maintains the song list, {moderator} the wish list; the {admin} manages users and may do everything. There is exactly one admin; the role is handed over when editing a user.', [
                'editor'    => '<strong>' . $e(t('Editor', [], 'role')) . '</strong>',
                'moderator' => '<strong>' . $e(t('Moderator', [], 'role')) . '</strong>',
                'admin'     => '<strong>' . $e(t('Admin', [], 'role')) . '</strong>',
            ]) ?>
        </p>
    </div>

    <div class="panel__actions">
        <a class="link-button" href="<?= $e(url(['p' => 'user'])) ?>"><?= icon('plus') ?><?= $e(t('Add user')) ?></a>
    </div>
</div>

<div class="table-wrap">
    <table class="grid grid--users">
        <caption class="sr-only"><?= $e(t('Users with roles and status')) ?></caption>
        <thead>
        <tr>
            <th scope="col"><?= $e(t('Username')) ?></th>
            <th scope="col"><?= $e(t('Roles')) ?></th>
            <th scope="col"><?= $e(t('Status')) ?></th>
            <th scope="col"><span class="sr-only"><?= $e(t('Actions')) ?></span></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
            <?php
            $isAdmin = (int) $row['is_admin'] === 1;
            $isSelf  = (int) $row['id'] === $selfId;
            $labels  = UserRepository::roleLabels($row);
            ?>
            <tr>
                <td class="cell-title">
                    <?= $e((string) $row['username']) ?>
                    <?php if ($isSelf): ?><span class="muted"><?= $e(t('(you)')) ?></span><?php endif; ?>
                </td>
                <td class="cell-genre">
                    <?php if ($labels === []): ?>
                        <span class="muted"><?= $e(t('no role')) ?></span>
                    <?php else: ?>
                        <?php foreach ($labels as $i => $label): ?>
                            <span class="tag<?= $isAdmin && $i === 0 ? ' tag--gold' : '' ?>"><?= $e($label) ?></span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </td>
                <td class="cell-length">
                    <?php if ((int) $row['active'] === 1): ?>
                        <?= $e(t('active')) ?>
                    <?php else: ?>
                        <span class="muted"><?= $e(t('locked')) ?></span>
                    <?php endif; ?>
                </td>
                <?php /* Edit and Delete side by side 50/50 as icon buttons at the right
                         edge -- the label stays for screen readers and as tooltip. */ ?>
                <td class="cell-action">
                    <div class="row-actions">
                        <div class="row-actions__pair">
                            <a class="link-button icon-button" title="<?= $e(t('Edit')) ?>" href="<?= $e(url(['p' => 'user', 'id' => (int) $row['id']])) ?>">
                                <?= icon('pencil') ?>
                                <span class="button__label"><?= $e(t('Edit')) ?></span>
                                <span class="sr-only">: <?= $e((string) $row['username']) ?></span>
                            </a>
                            <?php if (!$isAdmin): ?>
                                <form method="post" action="<?= $e(url()) ?>"
                                      data-confirm="<?= $e(t('Permanently delete user “{name}”?', ['name' => (string) $row['username']])) ?>">
                                    <input type="hidden" name="a" value="user_delete">
                                    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                    <button type="submit" class="delete-button icon-button" title="<?= $e(t('Delete')) ?>">
                                        <?= icon('trash') ?>
                                        <span class="button__label"><?= $e(t('Delete')) ?></span>
                                        <span class="sr-only">: <?= $e((string) $row['username']) ?></span>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
