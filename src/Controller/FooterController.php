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
 * The footer: which pages it links, in which order, and the operator's own
 * line below them.
 */
final class FooterController extends Controller
{
    public function __construct(
        Support $support,
        private readonly PageRepository $pages,
        private readonly Translator $translator,
        private readonly Limits $limits,
    ) {
        parent::__construct($support);
    }

    /**
     * Drag & drop or arrow buttons, like the wish list -- and the pages that
     * could be added. Below that the operator's own line, one tab per
     * language like a page's form.
     */
    public function index(Request $request): View
    {
        $texts     = $this->pages->footerLines();
        $languages = $this->translator->available();
        $perPage   = $this->limits->get('per_page');
        // Each column paged on its own: 'page' the pages outside the footer
        // (left), 'rpage' the footer itself (right).
        $outside  = Pagination::slice($this->pages->outsideFooter(), (int) $request->query('page', '1'), $perPage);
        $inFooter = Pagination::slice($this->pages->inFooter(), (int) $request->query('rpage', '1'), $perPage);

        return $this->view('footer', t('Footer'), [
            'linked'         => $inFooter['rows'],
            'linkedTotal'    => $inFooter['total'],
            'linkedPageNo'   => $inFooter['page'],
            'linkedPages'    => $inFooter['pages'],
            'linkedOffset'   => ($inFooter['page'] - 1) * $perPage,
            'available'      => $outside['rows'],
            'availableTotal' => $outside['total'],
            'pageNo'         => $outside['page'],
            'pages'          => $outside['pages'],
            'footerTexts'    => $texts,      // code => cleaned HTML, the languages with a line
            'languages'      => $languages,  // code => native name, the tabs
            // The tab to start on: the interface language's, or the first that has a line.
            'activeLang'     => isset($texts[$this->translator->code()])
                ? $this->translator->code()
                : (array_key_first($texts) ?? $this->translator->code()),
        ], editor: true);
    }

    /**
     * The operator's own footer line -- credits, a link -- below the page
     * links, one per language of the menu (text[<code>]) like a page's
     * versions. The HTML is reduced to what the pages may contain; an empty
     * language drops its line.
     */
    public function saveText(Request $request): Response
    {
        $kept = $this->pages->saveFooterLines($request->postMap('text'));
        $this->flash('ok', $kept === 0
            ? t('The footer line has been removed.')
            : t('The footer line has been saved.'));

        return $this->redirect('footer');
    }

    /** Link a page at the end of the footer. */
    public function add(Request $request): Response
    {
        $target = $this->pages->find($request->routeInt('id'));
        if ($target === null) {
            $this->flash('error', t('This page was not found.'));
        } else {
            $this->pages->addToFooter((int) $target['id']);
            $this->flash('ok', t('“{title}” is linked in the footer now.', ['title' => (string) $target['title']]));
        }

        return $this->redirectTo($this->back($this->url('footer')));
    }

    /** Take a page out of the footer; it keeps its address. */
    public function remove(Request $request): Response
    {
        $target = $this->pages->find($request->routeInt('id'));
        if ($target === null) {
            $this->flash('error', t('This page was not found.'));
        } else {
            $this->pages->removeFromFooter((int) $target['id']);
            $this->flash('ok', t('“{title}” is no longer linked in the footer. It stays reachable under its address.', ['title' => (string) $target['title']]));
        }

        return $this->redirectTo($this->back($this->url('footer')));
    }

    /**
     * One step (up, down) or to the very end (top, bottom) -- the keyboard's
     * way and the way without JavaScript.
     */
    public function move(Request $request): Response
    {
        $dir = $request->post('dir', 'up');
        $this->pages->moveInFooter(
            $request->routeInt('id'),
            in_array($dir, ['up', 'down', 'top', 'bottom'], true) ? $dir : 'up',
        );

        return $this->redirectTo($this->back($this->url('footer')));
    }

    /** "3,7,1" -- the footer's links in their new order, from drag & drop. */
    public function reorder(Request $request): Response
    {
        $count = $this->pages->reorderFooter(array_filter(array_map(
            'intval',
            explode(',', $request->post('order')),
        )));

        if ($request->wantsJson()) {
            return $this->json(['ok' => $count > 0, 'count' => $count]);
        }

        $this->flash($count > 0 ? 'ok' : 'error', $count > 0
            ? t('Order saved.')
            : t('The order could not be saved.'));

        return $this->redirectTo($this->back($this->url('footer')));
    }
}
