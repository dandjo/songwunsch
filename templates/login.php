<?php

declare(strict_types=1);

use Songwunsch\Format;

/** @var string $csrf */

$e = static fn (?string $v): string => Format::e($v);
?>

<div class="login">
    <h1><?= $e(t('Log in')) ?></h1>
    <p class="muted"><?= $e(t('Editing the wish list, the songs and the users is reserved for staff.')) ?></p>

    <form method="post" action="<?= $e(url()) ?>" class="login__form">
        <input type="hidden" name="a" value="login">
        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">

        <div class="field">
            <label for="user"><?= $e(t('Username')) ?></label>
            <input type="text" id="user" name="user" autocomplete="username" required autofocus>
        </div>

        <div class="field">
            <label for="pass"><?= $e(t('Password')) ?></label>
            <div class="password" data-reveal>
                <input type="password" id="pass" name="pass" autocomplete="current-password" required>
                <?= password_toggle() ?>
            </div>
        </div>

        <button type="submit" class="wish-button wish-button--wide"><?= $e(t('Log in')) ?></button>
    </form>
</div>
