<?php

declare(strict_types=1);

namespace Songwunsch\Controller;

use Songwunsch\GuestName;
use Songwunsch\Http\Request;
use Songwunsch\Http\Response;
use Songwunsch\Limits;
use Songwunsch\Pagination;
use Songwunsch\RoomContext;
use Songwunsch\SongRepository;
use Songwunsch\Template\View;
use Songwunsch\WishGuard;
use Songwunsch\WishRepository;

/**
 * The wish list of a room: everyone may look, moderators work through it.
 */
final class WishController extends Controller
{
    public function __construct(
        Support $support,
        private readonly WishRepository $wishes,
        private readonly SongRepository $songs,
        private readonly WishGuard $guard,
        private readonly Limits $limits,
        private readonly GuestName $guestName,
        private readonly RoomContext $room,
    ) {
        parent::__construct($support);
    }

    /**
     * Everyone may look at the list; only moderators get controls, and only
     * they may change the sorting -- guests always see the manual (play)
     * order.
     */
    public function index(Request $request): View
    {
        $canEdit = $this->support->security->can('wishes');
        $sort    = $canEdit ? $request->query('sort', 'manual') : 'manual';
        $dir     = $canEdit ? $request->query('dir', 'asc') : 'asc';
        $perPage = $this->limits->get('per_page');

        $result = Pagination::of(
            fn (int $page): array => $this->wishes->page($sort, $dir, $page, $perPage),
            (int) $request->query('page', '1'),
            $perPage,
        );

        return $this->view('wishes', t('Wish list'), [
            'canEdit' => $canEdit,
            'rows'    => $result['rows'],
            'total'   => $result['total'],
            'pageNo'  => $result['page'],
            'pages'   => $result['pages'],
            'offset'  => ($result['page'] - 1) * $perPage, // rank of the first row, minus one
            'sort'    => array_key_exists($sort, $this->wishes->sortableFields()) ? $sort : 'manual',
            'dir'     => strtolower($dir) === 'desc' ? 'desc' : 'asc',
            // The tab counter is this very number; no second query for it.
            'wishCount' => $result['total'],
        ]);
    }

    /**
     * A wish from the audience. The hurdles in front of it, in order: the
     * room may be closed, a bot may have filled the honeypot or been too
     * quick, the sender may be wishing too often, and the room may have
     * reached its cap of open wishes.
     */
    public function add(Request $request): Response
    {
        if ($this->guard->isPaused()) {
            $this->flash('info', t('The room is closed right now.'));

            return $this->redirectTo($this->back());
        }

        switch ($this->guard->checkForm($request->post('t'), $request->post('hp_url'))) {
            case WishGuard::CHECK_BOT:
                // Discard silently -- a script should get no hint about what
                // it failed on.
                $this->flash('ok', t('Thanks, your wish is in.'));

                return $this->redirectTo($this->back());
            case WishGuard::CHECK_FAST:
                $this->flash('error', t('That was very quick – please click “Wish” once more.'));

                return $this->redirectTo($this->back());
            case WishGuard::CHECK_STALE:
                $this->flash('error', t('The page has been open for a long time – please reload it and wish again.'));

                return $this->redirectTo($this->back());
        }

        if ($this->support->security->throttled($this->limits->get('wish_cooldown_sec'))) {
            $this->flash('error', t('One moment – please do not wish that quickly in a row.'));

            return $this->redirectTo($this->back());
        }

        $song = $this->songs->find((int) $request->post('key'), $this->room->id());
        if ($song === null) {
            $this->flash('error', t('This song was not found.'));

            return $this->redirectTo($this->back());
        }

        // A song that is already open is not added a second time: the
        // existing entry counts the wish. Such a wish adds no row, so the
        // cap on open wishes does not apply to it (open count 0); the
        // per-sender and per-minute limits still do.
        $again = $this->wishes->isPending((int) $song['id']);
        $limit = $this->guard->limitReached($again ? 0 : $this->wishes->count());
        if ($limit !== null) {
            $this->flash('error', $limit);

            return $this->redirectTo($this->back());
        }

        // wishAgain() answers null if the entry went in the meantime; then
        // the song is simply added like any other.
        $counted = $again ? $this->wishes->wishAgain((int) $song['id']) : null;
        if ($counted === null) {
            $this->wishes->add($song, $this->guestName->current());
        }
        $this->guard->touch();
        $this->guard->record();
        $this->support->security->markWish();

        $this->flash('ok', $counted !== null
            ? t('“{title}” is on the list already – wished {n} times now.', [
                'title' => (string) $song['title'],
                'n'     => (int) $counted['wished'],
            ])
            : t('“{title}” by {artist} is in.', [
                'title'  => (string) $song['title'],
                'artist' => (string) $song['artist'],
            ]));

        return $this->redirectTo($this->back());
    }

    public function delete(Request $request): Response
    {
        if ($this->wishes->delete($request->routeInt('id'))) {
            $this->guard->touch();
            $this->flash('ok', t('Wish deleted.'));
        } else {
            $this->flash('error', t('Wish not found.'));
        }

        return $this->redirectTo($this->back($this->url('wishes')));
    }

    /** One step (up, down) or to the very end (top, bottom). */
    public function move(Request $request): Response
    {
        $id  = $request->routeInt('id');
        $dir = $request->post('dir', 'up');

        $moved = $dir === 'top' || $dir === 'bottom'
            ? $this->wishes->moveToEnd($id, $dir === 'top')
            : $this->wishes->move($id, $dir === 'down' ? 1 : -1);
        if ($moved) {
            $this->guard->touch();
        }

        return $this->redirectTo($this->back($this->url('wishes')));
    }

    /** "3,7,1" -- the ids in their new order from top to bottom (drag & drop). */
    public function reorder(Request $request): Response
    {
        $ids   = array_filter(array_map('intval', explode(',', $request->post('order'))));
        $count = $this->wishes->reorder($ids);
        if ($count > 0) {
            $this->guard->touch();
        }

        if ($request->wantsJson()) {
            return $this->json(['ok' => $count > 0, 'count' => $count]);
        }

        $this->flash($count > 0 ? 'ok' : 'error', $count > 0
            ? t('Order saved.')
            : t('The order could not be saved.'));

        return $this->redirectTo($this->back($this->url('wishes')));
    }

    public function clear(Request $request): Response
    {
        $removed = $this->wishes->deleteAll();
        $this->guard->touch();
        $this->flash('ok', tn('{n} wish deleted.', '{n} wishes deleted.', $removed));

        return $this->redirect('wishes');
    }
}
