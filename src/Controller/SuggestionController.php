<?php

declare(strict_types=1);

namespace Songwunsch\Controller;

use Songwunsch\GuestName;
use Songwunsch\Http\Request;
use Songwunsch\Http\Response;
use Songwunsch\Limits;
use Songwunsch\Settings;
use Songwunsch\SongRepository;
use Songwunsch\SuggestionRepository;
use Songwunsch\Template\View;
use Songwunsch\WishGuard;

/**
 * Song suggestions: the audience names what is missing from the repertoire,
 * the editors adopt or delete it.
 */
final class SuggestionController extends Controller
{
    public function __construct(
        Support $support,
        private readonly SuggestionRepository $suggestions,
        private readonly SongRepository $songs,
        private readonly WishGuard $guard,
        private readonly Limits $limits,
        private readonly Settings $settings,
        private readonly GuestName $guestName,
    ) {
        parent::__construct($support);
    }

    /**
     * Everyone sees the room's open suggestions and may search them; editors
     * also adopt and delete. The badge keeps the full count, whatever the
     * search shows.
     */
    public function index(Request $request): View
    {
        $kept    = $this->support->forms->take();
        $q       = trim($request->query('q'));
        $perPage = $this->limits->get('per_page');
        $pageNo  = max(1, (int) $request->query('page', '1'));
        $paused  = $this->guard->isPaused();

        $result = $this->suggestions->search($q, $pageNo, $perPage);

        return $this->view('suggestions', t('Song suggestions'), [
            'canEdit' => $this->support->security->can('suggestions'),
            'q'       => $q,
            'rows'    => $result['rows'],
            'found'   => $result['total'],
            'pageNo'  => $pageNo,
            'pages'   => max(1, (int) ceil($result['total'] / $perPage)),
            // No form while wishing is paused in this room.
            'formToken' => $paused ? '' : $this->guard->formToken(),
            'errors'  => $kept['errors'] ?? [],
            'values'  => $kept['values'] ?? ['artist' => '', 'title' => ''],
            'suggestionCount' => $q === '' ? $result['total'] : $this->suggestions->count(),
        ]);
    }

    /**
     * A guest names a song that is missing. Same bot hurdles as wishing (a
     * honeypot and a signed timestamp), its own session cooldown and a cap
     * on the room's open suggestions.
     */
    public function add(Request $request): Response
    {
        $back = $this->url('suggestions');

        // The moderator's pause closes suggesting in the room as well.
        if ($this->guard->isPaused()) {
            $this->flash('info', t('The room is closed right now – no wishes and no suggestions.'));

            return $this->redirectTo($back);
        }

        switch ($this->guard->checkForm($request->post('t'), $request->post('hp_url'))) {
            case WishGuard::CHECK_BOT:
                $this->flash('ok', t('Thanks, your suggestion is in.'));

                return $this->redirectTo($back);
            case WishGuard::CHECK_FAST:
                $this->flash('error', t('That was very quick – please click “Suggest” once more.'));

                return $this->redirectTo($back);
            case WishGuard::CHECK_STALE:
                $this->flash('error', t('The page has been open for a long time – please reload it and suggest again.'));

                return $this->redirectTo($back);
        }

        if ($this->support->security->throttled($this->limits->get('suggestion_cooldown_sec'), 'suggestion')) {
            $this->flash('error', t('One moment – please do not suggest that quickly in a row.'));

            return $this->redirectTo($back);
        }

        $input   = ['artist' => $request->post('artist'), 'title' => $request->post('title')];
        $checked = $this->suggestions->validate($input);

        if ($checked['errors'] !== []) {
            $this->support->forms->remember($input, $checked['errors']);
            $this->notice('error', t('Please check the highlighted fields.'));

            return $this->redirectTo($back);
        }

        $maxOpen = $this->limits->get('suggestion_max_open');
        if ($maxOpen > 0 && $this->suggestions->count() >= $maxOpen) {
            $this->support->forms->remember($input, []);
            $this->flash('error', t('The suggestion box is full – please try again later.'));

            return $this->redirectTo($back);
        }

        $artist = (string) $checked['values']['artist'];
        $title  = (string) $checked['values']['title'];

        if ($this->songs->exists($artist, $title)) {
            $this->flash('info', t('“{title}” by {artist} is already on the repertoire – you can wish for it right away.', [
                'title'  => $title,
                'artist' => $artist,
            ]));

            return $this->redirectTo($back);
        }

        if ($this->suggestions->isPending($artist, $title)) {
            $this->flash('info', t('“{title}” by {artist} has already been suggested.', [
                'title'  => $title,
                'artist' => $artist,
            ]));

            return $this->redirectTo($back);
        }

        // The suggestion goes onto the list of the room the guest is in:
        // once adopted, the song is offered there right away.
        $this->suggestions->add($checked['values'], $this->guestName->current());
        $this->settings->increment(SuggestionRepository::REVISION_KEY);
        $this->support->security->markWish('suggestion');

        $this->flash('ok', t('Thanks! “{title}” by {artist} has been passed on to the editors.', [
            'title'  => $title,
            'artist' => $artist,
        ]));

        return $this->redirectTo($back);
    }

    public function delete(Request $request): Response
    {
        if ($this->suggestions->delete($request->routeInt('id'))) {
            $this->settings->increment(SuggestionRepository::REVISION_KEY);
            $this->flash('ok', t('Suggestion deleted.'));
        } else {
            $this->flash('error', t('This suggestion was not found.'));
        }

        return $this->redirectTo($this->back($this->url('suggestions')));
    }

    public function clear(Request $request): Response
    {
        $removed = $this->suggestions->deleteAll();
        $this->settings->increment(SuggestionRepository::REVISION_KEY);
        $this->flash('ok', tn('{n} suggestion deleted.', '{n} suggestions deleted.', $removed));

        return $this->redirect('suggestions');
    }
}
