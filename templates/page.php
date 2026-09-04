<?php

declare(strict_types=1);

use Songwunsch\Format;

/**
 * A footer page for everyone: the admins' imprint, FAQ, privacy notice --
 * in the interface language where the page has it, otherwise in the first
 * language of the fallback order it has (PageRepository).
 *
 * @var array<string,mixed> $content  the page: slug, title, body (cleaned HTML, see Html),
 *                                    lang (of the text shown), languages (codes it has)
 * @var bool $canEdit                 admins get a way to the form
 * @var \Songwunsch\Translator $translator
 */

$e = static fn (?string $v): string => Format::e($v);

// The text's language differs from the interface's when the page falls back
// to another language: say so in the markup, for screen readers and
// hyphenation.
$textLang = (string) $content['lang'] !== $translator->code() ? ' lang="' . $e((string) $content['lang']) . '"' : '';

// Admins land on the tab of their interface language -- the one to fill in
// when the page fell back to another.
$editUrl = url(['p' => 'page_edit', 'id' => (int) $content['id']]) . '#lang-' . rawurlencode($translator->code());
?>

<?php /* No description under the title, so the admins' Edit stands beside it
         (panel__head--inline) instead of on a row of its own. */ ?>
<div class="panel__head panel__head--inline">
    <div>
        <h1<?= $textLang ?>><?= $e((string) $content['title']) ?></h1>
    </div>
    <?php if ($canEdit): ?>
        <div class="panel__actions">
            <a class="link-button" href="<?= $e($editUrl) ?>"><?= icon('pencil') ?><?= $e(t('Edit')) ?></a>
        </div>
    <?php endif; ?>
</div>

<?php /* The body is HTML the admin wrote; Html::clean() reduced it to text
         structure on save, so it is printed as it is. */ ?>
<div class="prose"<?= $textLang ?>><?= $content['body'] ?></div>
