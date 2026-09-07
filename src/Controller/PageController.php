<?php

declare(strict_types=1);

namespace Songwunsch\Controller;

use Songwunsch\Http\Request;
use Songwunsch\Http\Response;
use Songwunsch\Limits;
use Songwunsch\PageRepository;
use Songwunsch\Pagination;
use Songwunsch\Template\View;
use Songwunsch\Translator;

/**
 * The operator's own pages: imprint, FAQ, ... Readable by everyone at
 * /pages/<slug>, written by the admins in one form with a tab per language.
 */
final class PageController extends Controller
{
    public function __construct(
        Support $support,
        private readonly PageRepository $pages,
        private readonly Translator $translator,
        private readonly Limits $limits,
    ) {
        parent::__construct($support);
    }

    /** A page for everyone -- in the footer or not. */
    public function show(Request $request): View
    {
        $found = $this->pages->findBySlug($request->routeString('slug'));
        if ($found === null) {
            // An unknown machine name is a 404 like any other unknown address.
            $this->notFound();
        }

        // findBySlug() answers in the interface language, or in the first
        // language of the fallback order the page has.
        return $this->view('page', (string) $found['title'], [
            'content' => $found,
            'canEdit' => $this->support->security->can('users'),
        ]);
    }

    /** Every page, with a mark on the ones the footer links. */
    public function index(Request $request): View
    {
        $q       = trim($request->query('q'));
        $perPage = $this->limits->get('per_page');
        // Sorted by title in the reader's language, so the page is cut out
        // of the complete list.
        $result  = Pagination::slice($this->pages->all($q), (int) $request->query('page', '1'), $perPage);

        return $this->view('pages', t('Pages'), [
            'q'         => $q,
            'rows'      => $result['rows'],
            'total'     => $result['total'],
            'pageNo'    => $result['page'],
            'pages'     => $result['pages'],
            'languages' => $this->translator->available(), // code => native name
            'order'     => $this->pages->languageOrder(),  // the fallback order, codes
        ]);
    }

    /** Add or edit a page; the body is written in CKEditor. */
    public function form(Request $request): View|Response
    {
        $id       = $request->routeInt('id'); // 0 = new page
        $existing = null;

        if ($id > 0) {
            $existing = $this->pages->find($id);
            if ($existing === null) {
                $this->notice('error', t('This page was not found.'));

                return $this->redirect('pages');
            }
        }

        $kept = $this->support->forms->take();

        // The form holds every language of the menu; the saved ones fill
        // their tabs. An error puts its tab in front, otherwise the
        // interface language's if the page has it, otherwise the first the
        // page has, otherwise the interface language's.
        $versions  = $id > 0 ? $this->pages->versions($id) : [];
        $errors    = $kept['errors'] ?? [];
        $languages = $this->translator->available();
        $withError = null;
        foreach (array_keys($languages) as $code) {
            if (isset($errors['title.' . $code]) || isset($errors['body.' . $code])) {
                $withError = $code;
                break;
            }
        }
        $filled = array_values(array_intersect(array_keys($languages), array_keys($kept['values']['title'] ?? $versions)));

        return $this->view('page_edit', $id === 0 ? t('Add page') : t('Edit page'), [
            'back'   => $this->destination($this->url('pages')),
            'id'     => $id,
            'errors' => $errors,
            'values' => $kept['values'] ?? [
                'slug'  => (string) ($existing['slug'] ?? ''),
                'title' => array_map(static fn (array $v): string => $v['title'], $versions),
                'body'  => array_map(static fn (array $v): string => $v['body'], $versions),
            ],
            'languages' => $languages,          // code => native name, the tabs
            'saved'     => array_keys($versions), // codes the page is saved in (tab marks)
            'activeLang' => $withError
                ?? (isset($versions[$this->translator->code()]) ? $this->translator->code() : ($filled[0] ?? $this->translator->code())),
        ], editor: true);
    }

    /**
     * One form for every language of the menu: title[<code>] and
     * body[<code>]; a language left empty is not part of the page. The
     * bodies are HTML from the editor and are reduced to the allowed tags in
     * validate() -- what is stored is what visitors get.
     */
    public function save(Request $request): Response
    {
        $id       = (int) $request->post('id'); // 0 = new page
        $existing = $id > 0 ? $this->pages->find($id) : null;

        if ($id > 0 && $existing === null) {
            $this->flash('error', t('This page was not found.'));

            return $this->redirect('pages');
        }
        // Where the form came from -- the list of pages or the page itself.
        $back = $this->destination($this->url('pages'));

        // "Remove <language>" on a tab: the language goes right away,
        // nothing else of the form is saved. A page keeps at least one.
        $remove = strtolower($request->post('remove_lang'));
        if ($remove !== '' && $existing !== null) {
            $label = $this->translator->available()[$remove] ?? strtoupper($remove);
            if ($this->pages->removeLanguage($id, $remove)) {
                $this->flash('ok', t('“{title}” is no longer available in {language}.', ['title' => (string) $existing['title'], 'language' => $label]));
            } else {
                $this->flash('error', t('{language} could not be removed: a page needs at least one language.', ['language' => $label]));
            }

            return $this->redirectTo(
                $this->url('page_edit', ['id' => $id, 'back' => $back]) . '#lang-' . rawurlencode($remove),
            );
        }

        $input = [
            'slug'  => $request->post('slug'),
            'title' => $request->postMap('title'),
            'body'  => $request->postMap('body'),
        ];
        $checked = $this->pages->validate($input, $existing);

        if ($checked['errors'] !== []) {
            $this->support->forms->remember($input, $checked['errors']);
            $this->notice('error', t('Please check the highlighted fields.'));

            return $this->redirectTo($id > 0
                ? $this->url('page_edit', ['id' => $id, 'back' => $back])
                : $this->url('page_new', ['back' => $back]));
        }

        $title = $this->pages->titleOf($checked['values']['versions']);
        if ($existing === null) {
            $this->pages->create($checked['values']);
            $this->flash('ok', t('Page “{title}” has been created. It is reachable under its address; the footer links it once you add it there.', ['title' => $title]));
        } else {
            $this->pages->update($id, $checked['values']);
            $this->flash('ok', t('Page “{title}” has been saved.', ['title' => $title]));
        }

        return $this->redirectTo($back);
    }

    /**
     * Deleting takes the page out of the footer as well, and its versions in
     * other languages with it.
     */
    public function delete(Request $request): Response
    {
        $target = $this->pages->find($request->routeInt('id'));

        if ($target === null) {
            $this->flash('error', t('This page was not found.'));
        } elseif ($this->pages->delete((int) $target['id'])) {
            $this->flash('ok', t('Page “{title}” has been deleted.', ['title' => (string) $target['title']]));
        } else {
            $this->flash('error', t('Deleting was not possible.'));
        }

        return $this->redirectTo($this->back($this->url('pages')));
    }
}
