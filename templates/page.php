<?php

declare(strict_types=1);

use Songwunsch\Format;

/**
 * A footer page for everyone: the admins' imprint, FAQ, privacy notice.
 *
 * @var array<string,mixed> $content  the page: slug, title, body (cleaned HTML, see Html)
 * @var bool $canEdit                 admins get a way to the form
 */

$e = static fn (?string $v): string => Format::e($v);
?>

<?php /* No description under the title, so the admins' Edit stands beside it
         (panel__head--inline) instead of on a row of its own. */ ?>
<div class="panel__head panel__head--inline">
    <div>
        <h1><?= $e((string) $content['title']) ?></h1>
    </div>
    <?php if ($canEdit): ?>
        <div class="panel__actions">
            <a class="link-button" href="<?= $e(url(['p' => 'page_edit', 'id' => (int) $content['id']])) ?>"><?= icon('pencil') ?><?= $e(t('Edit')) ?></a>
        </div>
    <?php endif; ?>
</div>

<?php /* The body is HTML the admin wrote; Html::clean() reduced it to text
         structure on save, so it is printed as it is. */ ?>
<div class="prose"><?= $content['body'] ?></div>
