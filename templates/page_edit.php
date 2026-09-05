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
 * Languages: one form for every language of the language menu, a tab per
 * language over a title and a content field each (title[<code>],
 * body[<code>]). A language left empty is not part of the page; at least one
 * must be filled in. app.js turns the row of anchors into tabs and shows one
 * panel at a time; without JavaScript every panel is on the page, headed by
 * its language.
 *
 * @var int $id                            0 = new page
 * @var array{slug?:string,title?:array<string,string>,body?:array<string,string>} $values
 * @var array<string,string> $errors       slug, title.<code>, body.<code>, versions
 * @var string $csrf
 * @var string $back                      where Cancel and a save return to: the page the visitor came from, see destination()
 * @var Translator $translator
 * @var array<string,string> $languages    code => native name, the tabs
 * @var array<int,string> $saved           codes the page is saved in (tab marks)
 * @var string $activeLang                 the tab to show first
 */

$e       = static fn (?string $v): string => Format::e($v);
$isNew   = $id === 0;

$fieldError = static function (string $field, string $htmlId) use ($errors, $e): string {
    if (!isset($errors[$field])) {
        return '';
    }

    return '<p class="field__error" id="err-' . $e($htmlId) . '">' . $e($errors[$field]) . '</p>';
};

$invalid = static fn (string $field, string $htmlId): string => isset($errors[$field])
    ? ' aria-invalid="true" aria-describedby="err-' . $htmlId . ' hint-' . $htmlId . '"'
    : ' aria-describedby="hint-' . $htmlId . '"';
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
            <?= $e(t('Fill in the page in the languages you like, one tab each; readers get it in their language, or in the first language of the fallback order the page has.')) ?>
        </p>
    </div>
</div>

<div class="login login--wide">
    <form method="post" action="<?= $e(url()) ?>" class="login__form">
        <input type="hidden" name="a" value="page_save">
        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
        <input type="hidden" name="id" value="<?= (int) $id ?>">
        <input type="hidden" name="back" value="<?= $e($back) ?>">

        <div class="field">
            <label for="slug"><?= $e(t('Machine name')) ?></label>
            <input type="text" id="slug" name="slug" value="<?= $e($values['slug'] ?? '') ?>"
                   required autofocus autocomplete="off" autocapitalize="none" spellcheck="false"
                   minlength="<?= PageRepository::MIN_SLUG ?>" maxlength="<?= PageRepository::MAX_SLUG ?>"
                   pattern="[a-z0-9]+(-[a-z0-9]+)*"<?= $invalid('slug', 'slug') ?>>
            <?php /* The address follows what is typed (app.js); until something is
                     typed an example stands in. Without JavaScript the hint shows
                     the saved address or the example. */ ?>
            <p class="field__hint" id="hint-slug">
                <?= $e(t('Part of the address: lower-case letters a–z, digits and hyphens.')) ?>
                <code data-slug-preview="slug" data-slug-base="<?= $e(url(['p' => 'page', 'slug' => ''])) ?>" data-slug-example="imprint"><?= $e(url(['p' => 'page', 'slug' => ($values['slug'] ?? '') !== '' ? $values['slug'] : 'imprint'])) ?></code>
            </p>
            <?= $fieldError('slug', 'slug') ?>
        </div>

        <?php /* The languages: a row of tabs, then one panel each. The anchors
                 lead to the panels without JavaScript; app.js makes real tabs
                 of them and starts on data-tabs-active. Each tab marks its
                 state: a tick where the page is saved in that language, a
                 dashed chip where it is not yet, an alert where its fields
                 need a look. */ ?>
        <div class="langtabs" data-tabs data-tabs-active="<?= $e($activeLang) ?>">
            <nav class="tabs" aria-label="<?= $e(t('Languages')) ?>">
                <ul role="list">
                    <?php foreach ($languages as $code => $name): ?>
                        <?php $hasError = isset($errors['title.' . $code]) || isset($errors['body.' . $code]); ?>
                        <li>
                            <a href="#lang-<?= $e($code) ?>" class="tabs__item<?= $hasError ? ' has-error' : '' ?>" data-tab="<?= $e($code) ?>">
                                <span lang="<?= $e($code) ?>"><?= $e($name) ?></span>
                                <?php if ($hasError): ?>
                                    <span class="tabs__alert" aria-hidden="true">!</span><span class="sr-only"><?= $e(t('needs a look', [], 'language tab')) ?></span>
                                <?php elseif (in_array($code, $saved, true)): ?>
                                    <?= icon('check', 14, true) ?><span class="sr-only"><?= $e(t('saved', [], 'language tab')) ?></span>
                                <?php elseif (!$isNew): ?>
                                    <span class="tag"><?= $e(t('missing', [], 'language tab')) ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </nav>
            <?php if (isset($errors['versions'])): ?>
                <p class="field__error langtabs__error" role="alert"><?= $e($errors['versions']) ?></p>
            <?php endif; ?>

            <?php foreach ($languages as $code => $name): ?>
                <?php $htmlCode = preg_replace('/[^a-z0-9]/', '-', $code) ?? $code; ?>
                <fieldset class="langpanel" id="lang-<?= $e($code) ?>" data-panel="<?= $e($code) ?>">
                    <legend class="langpanel__legend"><span lang="<?= $e($code) ?>"><?= $e($name) ?></span></legend>

                    <div class="field">
                        <label for="title-<?= $e($htmlCode) ?>"><?= $e(t('Title')) ?></label>
                        <input type="text" id="title-<?= $e($htmlCode) ?>" name="title[<?= $e($code) ?>]" value="<?= $e($values['title'][$code] ?? '') ?>"
                               maxlength="<?= PageRepository::MAX_TITLE ?>" lang="<?= $e($code) ?>"<?= $invalid('title.' . $code, 'title-' . $htmlCode) ?>>
                        <p class="field__hint" id="hint-title-<?= $e($htmlCode) ?>"><?= $e(t('The heading of the page and the text of its link in the footer, e.g. “Imprint”.')) ?></p>
                        <?= $fieldError('title.' . $code, 'title-' . $htmlCode) ?>
                    </div>

                    <div class="field field--editor">
                        <label for="body-<?= $e($htmlCode) ?>"><?= $e(t('Content')) ?></label>
                        <?php /* The editor takes the textarea's place and writes back into
                                 it on submit. Its interface follows the site's language
                                 when a translation file is bundled (layout). */ ?>
                        <textarea id="body-<?= $e($htmlCode) ?>" name="body[<?= $e($code) ?>]" rows="18" data-editor
                                  data-editor-lang="<?= $e($translator->code()) ?>"
                                  data-editor-placeholder="<?= $e(t('Write here …')) ?>" lang="<?= $e($code) ?>"<?= $invalid('body.' . $code, 'body-' . $htmlCode) ?>><?= $e($values['body'][$code] ?? '') ?></textarea>
                        <p class="field__hint" id="hint-body-<?= $e($htmlCode) ?>"><?= $e(t('Headings, paragraphs, lists, links, tables and quotes are kept; anything else – scripts, styles, pictures – is removed when the page is saved. A link to another page is its address, e.g. {example}.', ['example' => url(['p' => 'page', 'slug' => 'faq'])])) ?></p>
                        <?= $fieldError('body.' . $code, 'body-' . $htmlCode) ?>
                    </div>

                    <?php /* Takes the language off the saved page right away -- a
                             submit of the form with remove_lang, checked before
                             anything else is saved; the browser's own validation
                             is skipped for it. Only where the page has the
                             language; a page keeps at least one. */ ?>
                    <?php if (!$isNew && in_array($code, $saved, true) && count($saved) > 1): ?>
                        <p class="langpanel__remove">
                            <button type="submit" class="delete-button" name="remove_lang" value="<?= $e($code) ?>" formnovalidate
                                    data-confirm="<?= $e(t('Remove {language} from this page? Readers in {language} then get another language of the page. Changes not yet saved are lost.', ['language' => $name])) ?>">
                                <?= icon('trash') ?><?= $e(t('Remove {language}', ['language' => $name])) ?>
                            </button>
                        </p>
                    <?php endif; ?>
                </fieldset>
            <?php endforeach; ?>
        </div>

        <div class="panel__actions">
            <button type="submit" class="wish-button"><?= icon($isNew ? 'plus' : 'check') ?><?= $e($isNew ? t('Create') : t('Save')) ?></button>
            <a class="link-button" href="<?= $e($back) ?>"><?= icon('cross') ?><?= $e(t('Cancel')) ?></a>
        </div>
    </form>
</div>
