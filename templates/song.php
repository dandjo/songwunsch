<?php

declare(strict_types=1);

use Songwunsch\Format;
use Songwunsch\SongRepository;

/** @var SongRepository $repo */
/** @var int $key                           0 = new song */
/** @var array<string,string> $values */
/** @var array<string,string> $errors */
/** @var string $back */
/** @var string $csrf */

$e     = static fn (?string $v): string => Format::e($v);
$isNew = $key === 0;

/** Field error as a hint below the input. */
$fieldError = static function (string $field) use ($errors, $e): string {
    if (!isset($errors[$field])) {
        return '';
    }

    return '<p class="field__error" id="err-' . $e($field) . '">' . $e($errors[$field]) . '</p>';
};

/** Attributes for an input: error state and length limit. */
$attrs = static function (string $field, int $max) use ($errors, $e): string {
    $out = isset($errors[$field]) ? ' aria-invalid="true" aria-describedby="err-' . $e($field) . '"' : '';

    return $out . ' maxlength="' . $max . '"';
};
?>

<div class="panel__head">
    <div>
        <h1><?= $e($isNew ? t('Add song') : t('Edit song')) ?></h1>
        <p class="muted">
            <?= $e($isNew
                ? t('The audience can pick the song right away.')
                : t('Wishes already received keep their previous wording.')) ?>
        </p>
    </div>
</div>

<?php if (isset($errors['form'])): ?>
    <p class="flash flash--error" role="alert"><?= $e($errors['form']) ?></p>
<?php endif; ?>

<div class="login login--wide">
    <form method="post" action="<?= $e(url()) ?>" class="login__form">
        <input type="hidden" name="a" value="song_save">
        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
        <input type="hidden" name="key" value="<?= (int) $key ?>">
        <input type="hidden" name="back" value="<?= $e($back) ?>">

        <div class="field">
            <label for="artist"><?= $e(t('Artist')) ?></label>
            <input type="text" id="artist" name="artist" value="<?= $e($values['artist'] ?? '') ?>"
                   autocomplete="off" required autofocus<?= $attrs('artist', SongRepository::MAX_ARTIST) ?>>
            <?= $fieldError('artist') ?>
        </div>

        <div class="field">
            <label for="title"><?= $e(t('Title')) ?></label>
            <input type="text" id="title" name="title" value="<?= $e($values['title'] ?? '') ?>"
                   autocomplete="off" required<?= $attrs('title', SongRepository::MAX_TITLE) ?>>
            <?= $fieldError('title') ?>
        </div>

        <div class="field">
            <label for="length"><?= $e(t('Length')) ?> <span class="muted"><?= $e(t('(optional)')) ?></span></label>
            <input type="text" id="length" name="length" value="<?= $e($values['length'] ?? '') ?>"
                   inputmode="numeric" placeholder="3:45" maxlength="10"
                   aria-describedby="hint-length<?= isset($errors['length']) ? ' err-length' : '' ?>"
                   <?= isset($errors['length']) ? 'aria-invalid="true"' : '' ?>>
            <p class="field__hint" id="hint-length">
                <?= $e(t('As m:ss (3:45) or in seconds (225). Stored in seconds.')) ?>
            </p>
            <?= $fieldError('length') ?>
        </div>

        <div class="field">
            <label for="genre"><?= $e(t('Genre')) ?> <span class="muted"><?= $e(t('(optional)')) ?></span></label>
            <input type="text" id="genre" name="genre" value="<?= $e($values['genre'] ?? '') ?>"
                   autocomplete="off" list="genre-suggestions"<?= $attrs('genre', SongRepository::MAX_GENRE) ?>>
            <?php $known = $repo->knownGenres(); ?>
            <?php if ($known !== []): ?>
                <datalist id="genre-suggestions">
                    <?php foreach ($known as $genre): ?>
                        <option value="<?= $e($genre) ?>"></option>
                    <?php endforeach; ?>
                </datalist>
            <?php endif; ?>
            <?= $fieldError('genre') ?>
        </div>

        <div class="panel__actions">
            <button type="submit" class="wish-button">
                <?= icon($isNew ? 'plus' : 'check') ?><?= $e($isNew ? t('Add') : t('Save')) ?>
            </button>
            <a class="link-button" href="<?= $e($back) ?>"><?= icon('cross') ?><?= $e(t('Cancel')) ?></a>
        </div>
    </form>
</div>
