<?php

declare(strict_types=1);

namespace Songwunsch\Controller;

use Songwunsch\Http\Request;
use Songwunsch\Http\Response;
use Songwunsch\PageRepository;
use Songwunsch\Template\View;
use Songwunsch\Translator;

/**
 * The fallback order of the languages: which language a page falls back to
 * when it has no version in the one being read.
 */
final class LanguageController extends Controller
{
    public function __construct(
        Support $support,
        private readonly PageRepository $pages,
        private readonly Translator $translator,
    ) {
        parent::__construct($support);
    }

    /** Drag & drop or arrow buttons, like the wish list. */
    public function index(Request $request): View
    {
        return $this->view('languages', t('Languages'), [
            'languages' => $this->translator->available(), // code => native name
            'order'     => $this->pages->languageOrder(),  // the fallback order, codes
        ]);
    }

    /**
     * One step (up, down) or to the very end (top, bottom) -- the keyboard's
     * way and the way without JavaScript.
     */
    public function move(Request $request): Response
    {
        $dir = $request->post('dir', 'up');
        $this->pages->moveLanguage(
            $request->routeString('code'),
            in_array($dir, ['up', 'down', 'top', 'bottom'], true) ? $dir : 'up',
        );

        return $this->redirect('languages');
    }

    /** "de,en,fr" -- the languages in their new order, from drag & drop. */
    public function reorder(Request $request): Response
    {
        $saved = $this->pages->reorderLanguages(explode(',', $request->post('order')));

        if ($request->wantsJson()) {
            return $this->json(['ok' => $saved]);
        }

        $this->flash($saved ? 'ok' : 'error', $saved
            ? t('Order saved.')
            : t('The order could not be saved.'));

        return $this->redirect('languages');
    }
}
