<?php

declare(strict_types=1);

use Songwunsch\Format;

/** @var string $message */

$e = static fn (?string $v): string => Format::e($v);
?>

<div class="login">
    <h1><?= $e(t('The claw is stuck')) ?></h1>
    <p class="error-text"><?= $e($message) ?></p>
    <p class="muted">
        <?= t('Check the database credentials in {config}. The application creates its tables itself; if the database user is not allowed to, use {schema}.', [
            'config' => '<code>config.php</code>',
            'schema' => '<code>sql/schema.sql</code>',
        ]) ?>
    </p>
</div>
