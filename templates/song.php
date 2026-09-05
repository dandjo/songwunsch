<?php

declare(strict_types=1);

use Songwunsch\Format;
use Songwunsch\SongRepository;

/** @var SongRepository $repo */
/** @var int $key                           0 = new song */
/** @var array<string,mixed>|null $adopt    the suggestion this new song adopts, if any */
/** @var array<string,mixed>|null $adoptRoom  the room the suggestion was made in -- the song joins it */
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
        <h1><?= $e($adopt !== null ? t('Adopt suggestion') : ($isNew ? t('Add song') : t('Edit song'))) ?></h1>
        <p class="muted">
            <?php if ($adopt !== null): ?>
                <?= $e(t('Artist and title come from the suggestion – check them and add length and genre. The song goes on the list and onto the wish list, the suggestion off it.')) ?>
                <?php if ($adoptRoom !== null): ?>
                    <?= $e(t('It was suggested in room “{room}”, so the song is offered there as well.', ['room' => (string) $adoptRoom['name']])) ?>
                <?php endif; ?>
            <?php else: ?>
                <?= $e($isNew
                    ? t('The audience can pick the song right away.')
                    : t('Wishes already received keep their previous wording.')) ?>
            <?php endif; ?>
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
        <?php if ($adopt !== null): ?>
            <input type="hidden" name="suggestion" value="<?= (int) $adopt['id'] ?>">
        <?php endif; ?>
        <input type="hidden" name="back" value="<?= $e($back) ?>">

        <div class="field">
            <label for="artist"><?= $e(t('Artist')) ?></label>
            <?php /* Adopting: artist and title are already there, the cursor
                     goes to the first missing field. */ ?>
            <input type="text" id="artist" name="artist" value="<?= $e($values['artist'] ?? '') ?>"
                   autocomplete="off" required<?= $adopt === null ? ' autofocus' : '' ?><?= $attrs('artist', SongRepository::MAX_ARTIST) ?>>
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
                   inputmode="numeric" placeholder="3:45" maxlength="10"<?= $adopt !== null ? ' autofocus' : '' ?>
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

        <?php if ($adopt !== null): ?>
            <?php /* An adopted suggestion is a wish already; the editor says
                     where it queues. The top is preselected: a suggestion is
                     usually adopted the moment it comes up, so the audience
                     should see it played soon. */ ?>
            <?php $atBottom = ($values['wish_position'] ?? 'top') === 'bottom'; ?>
            <fieldset class="field field--group" aria-describedby="hint-wish_position">
                <legend><?= $e(t('On the wish list')) ?></legend>
                <label class="check">
                    <input type="radio" name="wish_position" value="top"<?= $atBottom ? '' : ' checked' ?>>
                    <span><?= $e(t('At the top of the wish list')) ?></span>
                </label>
                <label class="check">
                    <input type="radio" name="wish_position" value="bottom"<?= $atBottom ? ' checked' : '' ?>>
                    <span><?= $e(t('At the bottom of the wish list')) ?></span>
                </label>
                <p class="field__hint" id="hint-wish_position">
                    <?= $e(t('The song is put on the wish list of the room the suggestion was made in.')) ?>
                </p>
            </fieldset>
        <?php endif; ?>

        <div class="panel__actions">
            <button type="submit" class="wish-button">
                <?= icon($isNew ? 'plus' : 'check') ?><?= $e($isNew ? t('Add') : t('Save')) ?>
            </button>
            <a class="link-button" href="<?= $e($back) ?>"><?= icon('cross') ?><?= $e(t('Cancel')) ?></a>
        </div>
    </form>
</div>
