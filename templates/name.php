<?php

declare(strict_types=1);

use Songwunsch\Format;

/** @var string|null $guestName  the current name, null when none is set */
/** @var string $back            where the visitor came from */
/** @var string $csrf */

$e = static fn (?string $v): string => Format::e($v);

$nameBack = $back;
$nameAsk  = false;
?>

<div class="panel__head">
    <div>
        <h1><?= $e(t('Your name')) ?></h1>
        <p class="muted">
            <?php if ($guestName !== null): ?>
                <?= $e(t('Your wishes currently carry the name “{name}”.', ['name' => $guestName])) ?>
            <?php else: ?>
                <?= $e(t('Your wishes currently carry no name.')) ?>
            <?php endif; ?>
        </p>
    </div>
</div>

<div class="login login--wide">
    <?php require __DIR__ . '/_name_form.php'; ?>
</div>
