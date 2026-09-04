<?php

declare(strict_types=1);

use Songwunsch\Format;
use Songwunsch\RoomRepository;
use Songwunsch\SuggestionRepository;

/** @var array<int,array<string,mixed>> $rows  open suggestions, for everyone */
/** @var array<int,string> $roomNames    room names by id, for the tags on the rows */
/** @var array<string,mixed> $room       current room; the default room has id 0 */
/** @var bool $canEdit                  editor or admin: list, adopt, delete */
/** @var string $q                      the editor's search, '' = the whole list */
/** @var int|null $suggestionCount      how many are waiting */
/** @var string $formToken              signed timestamp for the suggest form */
/** @var bool $paused                   wishing closed by the moderator -- suggesting is closed too */
/** @var array<string,string> $values   the form's input, kept after an error */
/** @var array<string,string> $errors */
/** @var string|null $guestName         the visitor's name, from the cookie */
/** @var \Songwunsch\Settings $settings  delete confirmation switches */
/** @var string $csrf */

$e       = static fn (?string $v): string => Format::e($v);
$current = url(['p' => 'suggestions', 'q' => $q]);
$open    = (int) ($suggestionCount ?? count($rows));
$inRoom  = (int) $room['id'] !== RoomRepository::DEFAULT_ID;

/** Field error as a hint below the input. */
$fieldError = static function (string $field) use ($errors, $e): string {
    if (!isset($errors[$field])) {
        return '';
    }

    return '<p class="field__error" id="err-suggest-' . $e($field) . '">' . $e($errors[$field]) . '</p>';
};

/** Attributes for an input: error state and length limit. */
$attrs = static function (string $field, int $max) use ($errors, $e): string {
    $out = isset($errors[$field]) ? ' aria-invalid="true" aria-describedby="err-suggest-' . $e($field) . '"' : '';

    return $out . ' maxlength="' . $max . '"';
};
?>

<div class="panel__head">
    <div>
        <h1><?= $e(t('Song suggestions')) ?><?= $inRoom ? ' <span class="muted">· ' . $e((string) $room['name']) . '</span>' : '' ?></h1>
        <p class="muted">
            <?= $e(t('Missing a song? Name it here – the editors decide whether it joins the repertoire.')) ?>
            <?php if ($inRoom): ?>
                <?= $e(t('Suggested from this room, the song is offered here once it is in.')) ?>
            <?php endif; ?>
        </p>
    </div>
</div>

<?php if ($paused): ?>
    <?php /* The moderator's pause closes the form; the header already says
             where it can be lifted. Editors still see the list below. */ ?>
    <p class="empty"><?= $e(t('The room is closed right now – no wishes and no suggestions.')) ?></p>
<?php else: ?>
<?php /* The suggest form -- for everyone, editors included. Artist and title
         side by side where there is room, the button below. Bot hurdles as
         on the wish form: honeypot and signed timestamp. */ ?>
<div class="login login--wide suggest">
    <form method="post" action="<?= $e(url()) ?>" class="login__form">
        <input type="hidden" name="a" value="suggest">
        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
        <input type="hidden" name="t" value="<?= $e($formToken) ?>">
        <div class="hp" aria-hidden="true">
            <input type="text" name="hp_url" tabindex="-1" autocomplete="off" value="">
        </div>

        <fieldset class="field field--group">
            <legend><?= $e(t('Suggest a song')) ?></legend>
            <div class="field-pair">
                <div class="field">
                    <label for="suggest-title"><?= $e(t('Title')) ?></label>
                    <input type="text" id="suggest-title" name="title" value="<?= $e($values['title'] ?? '') ?>"
                           autocomplete="off" required<?= $attrs('title', SuggestionRepository::MAX_TITLE) ?>>
                    <?= $fieldError('title') ?>
                </div>
                <div class="field">
                    <label for="suggest-artist"><?= $e(t('Artist')) ?></label>
                    <input type="text" id="suggest-artist" name="artist" value="<?= $e($values['artist'] ?? '') ?>"
                           autocomplete="off" required<?= $attrs('artist', SuggestionRepository::MAX_ARTIST) ?>>
                    <?= $fieldError('artist') ?>
                </div>
            </div>
            <p class="field__hint">
                <?php if ($guestName !== null): ?>
                    <?= $e(t('Your name, {name}, goes with the suggestion so the editors know who asked.', ['name' => $guestName])) ?>
                <?php else: ?>
                    <?= $e(t('The suggestion is passed on without a name; set one in the account menu if you like.')) ?>
                <?php endif; ?>
            </p>
        </fieldset>

        <div class="panel__actions">
            <button type="submit" class="wish-button"><?= icon('bulb') ?><?= $e(t('Suggest')) ?></button>
        </div>
    </form>
</div>
<?php endif; ?>

<?php /* The open suggestions, set apart from the form by a hairline and
         their own heading. Everyone may look and search; only editors get
         the buttons and "Clear list". */ ?>
<section class="list-section" aria-labelledby="open-suggestions">
    <div class="panel__head">
        <div>
            <h2 id="open-suggestions"><?= $e(t('Open suggestions')) ?></h2>
            <p class="muted">
                <?php if ($q !== ''): ?>
                    <?= $e(tn('{n} suggestion found for “{q}”.', '{n} suggestions found for “{q}”.', count($rows), ['q' => $q])) ?>
                    <?= $e(tn('{n} suggestion waiting in total.', '{n} suggestions waiting in total.', $open)) ?>
                <?php elseif ($canEdit): ?>
                    <?= $e(tn('{n} suggestion waiting.', '{n} suggestions waiting.', $open)) ?>
                    <?= $e(t('Adopt puts the song on the list and onto the wish list once you have added what is missing; Delete drops the suggestion.')) ?>
                    <?= $e(t('A suggestion made in a room carries the room’s tag – the adopted song is offered there as well.')) ?>
                <?php else: ?>
                    <?= $e(tn('{n} suggestion is waiting for the editors.', '{n} suggestions are waiting for the editors.', $open)) ?>
                <?php endif; ?>
            </p>
        </div>

        <?php if ($canEdit && $open > 0): ?>
            <div class="panel__actions">
                <?php /* Bulk deletion always asks, whatever the personal setting says. */ ?>
                <form method="post" action="<?= $e(url()) ?>" data-confirm="<?= $e(t('Really delete all suggestions?')) ?>">
                    <input type="hidden" name="a" value="suggestions_clear">
                    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                    <button type="submit" class="danger-button"><?= icon('trash') ?><?= $e(t('Clear list')) ?></button>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($open > 0 || $q !== ''): ?>
        <?php /* Search over artist, title and name -- the same form as on the song list. */ ?>
        <form class="search" method="get" action="<?= $e(url(['p' => 'suggestions'])) ?>" role="search">
            <label class="sr-only" for="q"><?= $e(t('Search suggestions')) ?></label>
            <input type="search" id="q" name="q" value="<?= $e($q) ?>" placeholder="<?= $e(t('Artist, title, name …')) ?>" autocomplete="off">
            <button type="submit"><?= $e(t('Search')) ?></button>
            <?php if ($q !== ''): ?>
                <a class="search__reset" href="<?= $e(url(['p' => 'suggestions'])) ?>"><?= $e(t('reset')) ?></a>
            <?php endif; ?>
        </form>
    <?php endif; ?>

    <?php if ($rows === [] && $q !== ''): ?>
        <p class="empty"><?= $e(t('No suggestion matches. Try a different spelling?')) ?></p>
    <?php elseif ($rows === []): ?>
        <p class="empty"><?= $e($canEdit
            ? t('No suggestions yet. The audience has everything it needs – or has not found the form.')
            : t('No suggestions yet – yours could be the first.')) ?></p>
    <?php else: ?>
    <div class="table-wrap">
        <table class="grid grid--suggestions">
            <caption class="sr-only"><?= $e(t('Open song suggestions, oldest first, with who suggested them')) ?></caption>
            <thead>
            <tr>
                <th scope="col"><?= $e(t('Artist')) ?></th>
                <th scope="col"><?= $e(t('Title')) ?></th>
                <th scope="col"><?= $e(t('Room')) ?></th>
                <th scope="col"><?= $e(t('Received')) ?>, <?= $e(t('From')) ?></th>
                <?php if ($canEdit): ?>
                    <th scope="col"><span class="sr-only"><?= $e(t('Actions')) ?></span></th>
                <?php endif; ?>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php $label = t('{title} by {artist}', ['title' => (string) $row['title'], 'artist' => (string) $row['artist']]); ?>
                    <tr>
                        <td class="cell-artist"><?= $e((string) $row['artist']) ?></td>
                        <td class="cell-title"><?= $e((string) $row['title']) ?></td>
                        <?php /* The room the guest was in; the adopted song joins it.
                                 Nothing for the main room -- that is the master list. */ ?>
                        <td class="cell-genre cell-room">
                            <?php $rowRoom = (int) ($row['room_id'] ?? 0); ?>
                            <?php if ($rowRoom > 0): ?>
                                <span class="tag"><span class="sr-only"><?= $e(t('Room')) ?>: </span><?= $e($roomNames[$rowRoom] ?? t('deleted room')) ?></span>
                            <?php else: ?>
                                <span class="sr-only"><?= $e((string) RoomRepository::defaultRoom()['name']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="cell-meta">
                            <span class="cell-time">
                                <?= icon('clock', 14) ?><time datetime="<?= $e(str_replace(' ', 'T', (string) $row['created_at'])) ?>">
                                    <?= $e(Format::moment((string) $row['created_at'])) ?>
                                </time>
                                <span class="muted ago"><?= $e(Format::ago((string) $row['created_at'])) ?></span>
                            </span>
                            <?php if (($row['suggester'] ?? '') !== ''): ?>
                                <span class="cell-wisher">
                                    <?= icon('user', 14) ?><span class="sr-only"><?= $e(t('From')) ?> </span><span class="cell-wisher__name"><?= $e((string) $row['suggester']) ?></span>
                                </span>
                            <?php else: ?>
                                <span class="sr-only"><?= $e(t('No name given')) ?></span>
                            <?php endif; ?>
                        </td>
                        <?php if ($canEdit): ?>
                        <td class="cell-action">
                            <div class="row-actions">
                                <?php /* Adopt leads to the song form with artist and
                                         title filled in; the song is created there. */ ?>
                                <a class="wish-button" href="<?= $e(url(['p' => 'song', 'suggestion' => (int) $row['id'], 'back' => $current])) ?>">
                                    <?= icon('plus') ?><?= $e(t('Adopt')) ?><span class="sr-only">: <?= $e($label) ?></span>
                                </a>
                                <form method="post" action="<?= $e(url()) ?>"<?php if ($settings->confirmsDelete((int) ($security->user()['id'] ?? 0), 'suggestions')): ?> data-confirm="<?= $e(t('Delete the suggestion “{title}”?', ['title' => (string) $row['title']])) ?>"<?php endif; ?>>
                                    <input type="hidden" name="a" value="suggestion_delete">
                                    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                    <button type="submit" class="delete-button icon-button" title="<?= $e(t('Delete')) ?>">
                                        <?= icon('trash') ?>
                                        <span class="button__label"><?= $e(t('Delete')) ?></span>
                                        <span class="sr-only">: <?= $e($label) ?></span>
                                    </button>
                                </form>
                            </div>
                        </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
