<?php

declare(strict_types=1);

use Songwunsch\Format;
use Songwunsch\PageRepository;
use Songwunsch\Translator;

/**
 * Admin only: add or edit a page. The content is written in CKEditor
 * (assets/vendor/ckeditor5, wired up by app.js on textarea[data-editor]);
 * without JavaScript the textarea shows the HTML itself. The server reduces
 * whatever arrives to the allowed tags (src/Html.php).
 *
 * @var int $id                          0 = new page
 * @var array<string,string> $values     slug, title, body
 * @var array<string,string> $errors
 * @var string $csrf
 * @var Translator $translator
 */

$e       = static fn (?string $v): string => Format::e($v);
$isNew   = $id === 0;
$backUrl = url(['p' => 'pages']);

$fieldError = static function (string $field) use ($errors, $e): string {
    if (!isset($errors[$field])) {
        return '';
    }

    return '<p class="field__error" id="err-' . $e($field) . '">' . $e($errors[$field]) . '</p>';
};

$invalid = static fn (string $field): string => isset($errors[$field])
    ? ' aria-invalid="true" aria-describedby="err-' . $field . ' hint-' . $field . '"'
    : ' aria-describedby="hint-' . $field . '"';
?>

<div class="panel__head">
    <div>
        <h1><?= $e($isNew ? t('Add page') : t('Edit page')) ?></h1>
        <p class="muted">
            <?php if ($isNew): ?>
                <?= $e(t('Everyone can read the page under its address as soon as it is saved. Whether the footer links it is decided under Footer.')) ?>
            <?php else: ?>
                <?= $e(t('Changing the machine name changes the address – links already handed out stop working.')) ?>
            <?php endif; ?>
        </p>
    </div>
</div>

<div class="login login--wide">
    <form method="post" action="<?= $e(url()) ?>" class="login__form">
        <input type="hidden" name="a" value="page_save">
        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
        <input type="hidden" name="id" value="<?= (int) $id ?>">

        <div class="field">
            <label for="title"><?= $e(t('Title')) ?></label>
            <input type="text" id="title" name="title" value="<?= $e($values['title'] ?? '') ?>"
                   required autofocus maxlength="<?= PageRepository::MAX_TITLE ?>"<?= $invalid('title') ?>>
            <p class="field__hint" id="hint-title"><?= $e(t('The heading of the page and the text of its link in the footer, e.g. “Imprint”.')) ?></p>
            <?= $fieldError('title') ?>
        </div>

        <div class="field">
            <label for="slug"><?= $e(t('Machine name')) ?></label>
            <input type="text" id="slug" name="slug" value="<?= $e($values['slug'] ?? '') ?>"
                   required autocomplete="off" autocapitalize="none" spellcheck="false"
                   minlength="<?= PageRepository::MIN_SLUG ?>" maxlength="<?= PageRepository::MAX_SLUG ?>"
                   pattern="[a-z0-9]+(-[a-z0-9]+)*"<?= $invalid('slug') ?>>
            <?php /* The address follows what is typed (app.js); until something is
                     typed an example stands in. Without JavaScript the hint shows
                     the saved address or the example. */ ?>
            <p class="field__hint" id="hint-slug">
                <?= $e(t('Part of the address: lower-case letters a–z, digits and hyphens.')) ?>
                <code data-slug-preview="slug" data-slug-base="<?= $e(url(['p' => 'page', 'slug' => ''])) ?>" data-slug-example="imprint"><?= $e(url(['p' => 'page', 'slug' => ($values['slug'] ?? '') !== '' ? $values['slug'] : 'imprint'])) ?></code>
            </p>
            <?= $fieldError('slug') ?>
        </div>

        <div class="field field--editor">
            <label for="body"><?= $e(t('Content')) ?></label>
            <?php /* The editor takes the textarea's place and writes back into
                     it on submit. Its interface follows the site's language
                     when a translation file is bundled (layout). */ ?>
            <textarea id="body" name="body" rows="18" data-editor
                      data-editor-lang="<?= $e($translator->code()) ?>"
                      data-editor-placeholder="<?= $e(t('Write here …')) ?>"<?= $invalid('body') ?>><?= $e($values['body'] ?? '') ?></textarea>
            <p class="field__hint" id="hint-body"><?= $e(t('Headings, paragraphs, lists, links, tables and quotes are kept; anything else – scripts, styles, pictures – is removed when the page is saved. A link to another page is its address, e.g. {example}.', ['example' => url(['p' => 'page', 'slug' => 'faq'])])) ?></p>
            <?= $fieldError('body') ?>
        </div>

        <div class="panel__actions">
            <button type="submit" class="wish-button"><?= icon($isNew ? 'plus' : 'check') ?><?= $e($isNew ? t('Create') : t('Save')) ?></button>
            <a class="link-button" href="<?= $e($backUrl) ?>"><?= icon('cross') ?><?= $e(t('Cancel')) ?></a>
        </div>
    </form>
</div>
