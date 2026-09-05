<?php

declare(strict_types=1);

use Songwunsch\Format;
use Songwunsch\UserRepository;

/** @var array<int,array<string,mixed>> $rows  one page of the users, or of those matching the search */
/** @var int $total   all of them */
/** @var int $pageNo */
/** @var int $pages */
/** @var string $q       the search, '' for all */
/** @var int $selfId  id of the signed-in admin */
/** @var string $csrf */

$e = static fn (?string $v): string => Format::e($v);
// This list with its search and page: where the forms lead back to.
$current = url(['p' => 'users', 'q' => $q, 'page' => $pageNo > 1 ? $pageNo : null]);
?>

<div class="panel__head">
    <div>
        <div class="panel__title">
            <h1><?= $e(t('Users')) ?></h1>
            <?= help_button('help-users') ?>
        </div>
        <p class="muted help" id="help-users">
            <?= $e($q !== '' ? tn('{n} user found.', '{n} users found.', $total) : tn('{n} user.', '{n} users.', $total)) ?>
            <?= t('{editor} maintains the repertoire, {moderator} the wish list; {admin} manages users, hands out every role and may do everything. Roles can be combined; at least one active admin always remains.', [
                'editor'    => '<strong>' . $e(t('Editor', [], 'role')) . '</strong>',
                'moderator' => '<strong>' . $e(t('Moderator', [], 'role')) . '</strong>',
                'admin'     => '<strong>' . $e(t('Admin', [], 'role')) . '</strong>',
            ]) ?>
        </p>
    </div>

    <div class="panel__actions">
        <a class="link-button" href="<?= $e(url(['p' => 'user', 'back' => $current])) ?>"><?= icon('plus') ?><?= $e(t('Add user')) ?></a>
    </div>
</div>

<form class="search" method="get" action="<?= $e(url(['p' => 'users'])) ?>" role="search">
    <label class="sr-only" for="q"><?= $e(t('Search users')) ?></label>
    <input type="search" id="q" name="q" value="<?= $e($q) ?>" placeholder="<?= $e(t('Username …')) ?>" autocomplete="off">
    <button type="submit"><?= $e(t('Search')) ?></button>
    <?php if ($q !== ''): ?>
        <a class="search__reset" href="<?= $e(url(['p' => 'users'])) ?>"><?= $e(t('reset')) ?></a>
    <?php endif; ?>
</form>

<?php if ($rows === []): ?>
    <p class="empty"><?= $e(t('No user found. Try a different spelling?')) ?></p>
<?php else: ?>
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
            $isAdmin = (int) $row['role_admin'] === 1;
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
                         edge -- the label stays for screen readers and as tooltip.
                         Nobody deletes themselves. */ ?>
                <td class="cell-action">
                    <div class="row-actions">
                        <div class="row-actions__pair">
                            <a class="link-button icon-button" title="<?= $e(t('Edit')) ?>" href="<?= $e(url(['p' => 'user', 'id' => (int) $row['id'], 'back' => $current])) ?>">
                                <?= icon('pencil') ?>
                                <span class="button__label"><?= $e(t('Edit')) ?></span>
                                <span class="sr-only">: <?= $e((string) $row['username']) ?></span>
                            </a>
                            <?php if (!$isSelf): ?>
                                <form method="post" action="<?= $e(url()) ?>"
                                      data-confirm="<?= $e(t('Permanently delete user “{name}”?', ['name' => (string) $row['username']])) ?>">
                                    <input type="hidden" name="a" value="user_delete">
                                    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                                    <input type="hidden" name="back" value="<?= $e($current) ?>">
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

<?php
$pageUrl = static fn (int $page): string => url(['p' => 'users', 'q' => $q, 'page' => $page > 1 ? $page : null]);
require __DIR__ . '/_pager.php';
?>
<?php endif; ?>
