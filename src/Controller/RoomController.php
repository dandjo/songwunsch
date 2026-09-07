<?php

declare(strict_types=1);

namespace Songwunsch\Controller;

use Songwunsch\Http\Request;
use Songwunsch\Http\Response;
use Songwunsch\Limits;
use Songwunsch\Pagination;
use Songwunsch\QrCode;
use Songwunsch\RoomContext;
use Songwunsch\RoomMemory;
use Songwunsch\RoomRepository;
use Songwunsch\RoomServices;
use Songwunsch\Settings;
use Songwunsch\SongRepository;
use Songwunsch\Template\View;
use Songwunsch\WishGuard;

/**
 * Rooms: the list everyone may switch with, the editors' room form, a room's
 * song selection and its QR code.
 */
final class RoomController extends Controller
{
    public function __construct(
        Support $support,
        private readonly RoomRepository $rooms,
        private readonly SongRepository $songs,
        private readonly Settings $settings,
        private readonly Limits $limits,
        private readonly WishGuard $guard,
        private readonly RoomContext $room,
        private readonly RoomMemory $memory,
        private readonly RoomServices $roomServices,
    ) {
        parent::__construct($support);
    }

    /**
     * Everyone sees the active rooms and may switch -- guests only the
     * listed ones, signed-in users every active room; editors also manage
     * them and may list archived rooms.
     */
    public function index(Request $request): View
    {
        $canEdit = $this->support->security->can('rooms');
        $perPage = $this->limits->get('per_page');
        $q       = trim($request->query('q'));
        // Editors see every room by default (archived ones tagged), guests
        // only the active ones, whatever the parameter says.
        $filter  = $request->query('filter', 'all');
        $filter  = $canEdit && in_array($filter, RoomRepository::FILTERS, true) ? $filter : 'active';
        $guest   = !$this->support->security->isLoggedIn();

        // Through Pagination::of(), like the repertoire: a room deleted
        // meanwhile must not leave the viewer on a page that is gone.
        $result = Pagination::of(
            fn (int $page): array => $this->rooms->search($q, $filter, $page, $perPage, $guest),
            max(1, (int) $request->query('page', '1')),
            $perPage,
        );

        $isAdmin  = $this->support->security->isAdmin();
        $canPause = $this->support->security->can('wishes');

        // Moderators close and open rooms from the list: which are closed?
        $pausedRooms = [];
        if ($canPause) {
            foreach ([RoomRepository::DEFAULT_ID, ...array_map('intval', array_column($result['rows'], 'id'))] as $id) {
                $pausedRooms[$id] = $this->guard->pausedIn($id);
            }
        }

        return $this->view('rooms', t('Rooms'), [
            'rows'        => $result['rows'],
            'total'       => $result['total'],
            'q'           => $q,
            'filter'      => $filter,
            'pageNo'      => $result['page'],
            'pages'       => $result['pages'],
            'canEdit'     => $canEdit,
            'startRoomId' => (int) $this->settings->get(RoomRepository::START_ROOM_KEY, '0'),
            // The admins' switch closes wishing in every room at once and
            // later hands every room its previous state back.
            'isAdmin'     => $isAdmin,
            'pausedAll'   => $isAdmin && $this->guard->isPausedEverywhere(),
            'canPause'    => $canPause,
            'pausedRooms' => $pausedRooms,
            'mainSongs'   => $this->songs->count(),
            'mainWishes'  => $this->roomServices->wishes(RoomRepository::DEFAULT_ID)->count(),
        ]);
    }

    /**
     * Add or edit a room. /rooms/main/edit edits the main room: its name and
     * its listed switch, which live in the settings because it has no row.
     */
    public function form(Request $request): View|Response
    {
        $main = $request->routeName() === 'room_main_edit';
        $id   = $main ? 0 : $request->routeInt('id');
        $edit = null;

        if ($main) {
            $kept = $this->support->forms->take();

            return $this->view('room', t('Edit “General”'), [
                'id'          => 0,
                'main'        => true,
                'back'        => $this->destination($this->url('rooms')),
                'startRoomId' => (int) $this->settings->get(RoomRepository::START_ROOM_KEY, '0'),
                'roomClosed'  => $this->guard->pausedIn(RoomRepository::DEFAULT_ID),
                'roomActive'  => true,
                'roomSlug'    => '',
                'errors'      => $kept['errors'] ?? [],
                'values'      => $kept['values'] ?? [
                    'name'   => (string) $this->settings->get(RoomRepository::MAIN_NAME_KEY, ''),
                    'listed' => (string) $this->settings->get(RoomRepository::MAIN_LISTED_KEY, '1'),
                ],
            ]);
        }

        if ($id > 0) {
            $edit = $this->rooms->find($id);
            if ($edit === null) {
                $this->notice('error', t('This room was not found.'));

                return $this->redirect('rooms');
            }
        }

        $kept = $this->support->forms->take();

        return $this->view('room', $id === 0 ? t('Add room') : t('Edit room'), [
            'id'   => $id,
            'main' => false,
            'back' => $this->destination($this->url('rooms')),
            // The switches beside the title (start room, close/open) -- for
            // an existing room.
            'startRoomId' => (int) $this->settings->get(RoomRepository::START_ROOM_KEY, '0'),
            'roomClosed'  => $id > 0 && $this->guard->pausedIn($id),
            'roomActive'  => (int) ($edit['active'] ?? 1) === 1,
            'roomSlug'    => (string) ($edit['slug'] ?? ''),
            'errors'      => $kept['errors'] ?? [],
            'values'      => $kept['values'] ?? [
                'slug'   => (string) ($edit['slug'] ?? ''),
                'name'   => (string) ($edit['name'] ?? ''),
                'active' => (string) ($edit['active'] ?? '1'),
                'listed' => (string) ($edit['listed'] ?? '0'), // a new room starts unlisted
            ],
        ]);
    }

    public function save(Request $request): Response
    {
        $id       = (int) $request->post('id'); // 0 = new room
        $existing = $id > 0 ? $this->rooms->find($id) : null;

        if ($id > 0 && $existing === null) {
            $this->flash('error', t('This room was not found.'));

            return $this->redirect('rooms');
        }
        $back = $this->destination($this->url('rooms'));

        $input = [
            'slug'   => $request->post('slug'),
            'name'   => $request->post('name'),
            'active' => $request->post('active'),
            'listed' => $request->post('listed'),
        ];
        $checked = $this->rooms->validate($input, $existing);

        if ($checked['errors'] !== []) {
            $this->support->forms->remember($input, $checked['errors']);
            $this->notice('error', t('Please check the highlighted fields.'));

            return $this->redirectTo($id > 0
                ? $this->url('room_edit', ['id' => $id, 'back' => $back])
                : $this->url('room_new', ['back' => $back]));
        }

        if ($existing === null) {
            $id = $this->rooms->create($checked['values']);
        } else {
            $this->rooms->update($id, $checked['values']);
        }
        $this->settings->increment(RoomRepository::REVISION_KEY);

        // Archiving closes wishing in that room -- signed-in users still
        // reach it. Reactivating does not reopen it; that is the moderator's
        // call on the room's wish list.
        $archivedNow = (int) $checked['values']['active'] === 0
            && ($existing === null || (int) $existing['active'] === 1);
        if ($archivedNow) {
            $this->roomServices->guard($id)->setPaused(true);
        }

        if ($existing === null) {
            // A new room is empty: the way leads on to its song selection,
            // not back -- the only save that does not. The destination
            // travels along, so Back there leads home.
            $this->flash('ok', t('Room “{name}” has been created. Now pick its songs from the main list.', ['name' => $checked['values']['name']]));

            return $this->redirect('room_songs', ['room' => $checked['values']['slug'], 'back' => $back]);
        }

        $this->flash('ok', $archivedNow
            ? t('Room “{name}” has been archived and closed.', ['name' => $checked['values']['name']])
            : t('Room “{name}” has been saved.', ['name' => $checked['values']['name']]));

        return $this->redirectTo($back);
    }

    /**
     * The main room's name and its listed switch. It has no row of its own;
     * both live in the settings, each absent at its default (the translated
     * name, listed).
     */
    public function saveMain(Request $request): Response
    {
        $back   = $this->destination($this->url('rooms'));
        $name   = trim(preg_replace('/\s+/u', ' ', $request->post('name')) ?? '');
        $listed = $request->post('listed') === '1';

        if (mb_strlen($name) > RoomRepository::MAX_NAME) {
            $this->support->forms->remember(
                ['name' => $name, 'listed' => $listed ? '1' : '0'],
                ['name' => t('{field} is too long: at most {max} characters.', ['field' => t('Name'), 'max' => RoomRepository::MAX_NAME])],
            );
            $this->notice('error', t('Please check the highlighted fields.'));

            return $this->redirect('room_main_edit', ['back' => $back]);
        }

        $renamed = $name !== (string) $this->settings->get(RoomRepository::MAIN_NAME_KEY, '');
        if ($name === '') {
            $this->settings->delete(RoomRepository::MAIN_NAME_KEY);
        } else {
            $this->settings->set(RoomRepository::MAIN_NAME_KEY, $name);
        }
        RoomRepository::nameMainRoom($name);

        if ($listed) {
            $this->settings->delete(RoomRepository::MAIN_LISTED_KEY);
        } else {
            $this->settings->set(RoomRepository::MAIN_LISTED_KEY, '0');
        }
        RoomRepository::listMainRoom($listed);

        $this->flash('ok', match (true) {
            $renamed && $name === '' => t('“General” has its default name again.'),
            $renamed                 => t('“General” is now called “{name}”.', ['name' => $name]),
            default                  => t('Room “{name}” has been saved.', ['name' => (string) RoomRepository::defaultRoom()['name']]),
        });
        $this->settings->increment(RoomRepository::REVISION_KEY);

        return $this->redirectTo($back);
    }

    public function delete(Request $request): Response
    {
        $target = $this->rooms->find($request->routeInt('id'));

        if ($target === null) {
            $this->flash('error', t('This room was not found.'));
        } elseif ($this->rooms->delete((int) $target['id'])) {
            if ((int) $this->settings->get(RoomRepository::START_ROOM_KEY, '0') === (int) $target['id']) {
                $this->settings->delete(RoomRepository::START_ROOM_KEY);
            }
            // Its pause switch and revision counter go with it.
            $this->guard->forgetRoom((int) $target['id']);
            $this->settings->increment(RoomRepository::REVISION_KEY);
            $this->flash('ok', t('Room “{name}” has been deleted together with its wishes.', ['name' => (string) $target['name']]));
        } else {
            $this->flash('error', t('Deleting was not possible.'));
        }

        return $this->redirect('rooms');
    }

    /**
     * Which room a visitor without any remembered room lands in when opening
     * the bare address. Id 0 is the main room, which needs no setting.
     */
    public function start(Request $request): Response
    {
        $startId = $request->routeInt('id');

        if ($startId === RoomRepository::DEFAULT_ID) {
            $this->settings->delete(RoomRepository::START_ROOM_KEY);
            $this->flash('ok', t('New visitors start in “General” again.'));
        } else {
            $startRoom = $this->rooms->find($startId);
            if ($startRoom === null) {
                $this->flash('error', t('This room was not found.'));

                return $this->redirect('rooms');
            }
            $this->settings->set(RoomRepository::START_ROOM_KEY, (string) $startId);
            $this->flash('ok', t('New visitors now start in “{name}”.', ['name' => (string) $startRoom['name']]));
        }
        $this->settings->increment(RoomRepository::REVISION_KEY);

        return $this->redirectTo($this->back($this->url('rooms')));
    }

    /**
     * Close or open a room: the one named in the path -- the current room
     * from the header notice, any room from the room list. Id 0 is the main
     * room.
     */
    public function pause(Request $request): Response
    {
        $targetId   = $request->routeInt('id');
        $targetRoom = $targetId === RoomRepository::DEFAULT_ID
            ? RoomRepository::defaultRoom()
            : $this->rooms->find($targetId);

        if ($targetRoom === null) {
            $this->flash('error', t('This room was not found.'));

            return $this->redirectTo($this->back($this->url('rooms')));
        }

        $guard  = $targetId === $this->room->id() ? $this->guard : $this->roomServices->guard($targetId);
        $paused = $request->post('state') === '1';
        $guard->setPaused($paused);

        $this->flash('ok', $paused
            ? t('“{name}” is closed. The audience can see its repertoire but cannot wish or suggest anything there.', ['name' => (string) $targetRoom['name']])
            : t('“{name}” is open again.', ['name' => (string) $targetRoom['name']]));

        return $this->redirectTo($this->back($this->url('rooms')));
    }

    /** Admins only: close every room at once, and hand every room its previous state back. */
    public function pauseAll(Request $request): Response
    {
        if ($request->post('state') === '1') {
            $this->guard->pauseEverywhere($this->rooms->ids());
            $this->flash('ok', t('“General” and every room are closed.'));
        } else {
            $this->guard->resumeEverywhere($this->rooms->ids());
            $this->flash('ok', t('The closing is lifted; every room is back to the state it had before.'));
        }

        return $this->redirect('rooms');
    }

    /**
     * An explicit change of room, from the switcher or the room list.
     *
     * Rooms are reached through their address; this is the way to the main
     * room, which has none of its own: the memory is cleared, so / means the
     * main room again. 'to' names the page to land on -- the same sub-page
     * the visitor is on. From a page without a room in its address (users,
     * admin, login, ...) 'back' names that page: only the remembered room
     * changes, and the visitor stays where they are.
     */
    public function switchTo(Request $request): Response
    {
        $to   = $request->post('to', 'songs');
        $to   = in_array($to, ['songs', 'wishes', 'suggestions'], true) ? $to : 'songs';
        $slug = $request->post('slug');
        $stay = $this->support->urls->safeTarget($request->post('back'));

        if ($slug === '') {
            // The main room, chosen on purpose -- remembered as such, so the
            // start room does not take over on the next visit.
            $this->memory->remember('');

            return $this->redirectTo($stay ?? $this->url($to, ['room' => '']));
        }

        // An archived room is open to signed-in users only.
        $target = $this->rooms->findBySlug($slug);
        if ($target === null || ((int) $target['active'] === 0 && !$this->support->security->isLoggedIn())) {
            $this->flash('error', t('This room was not found.'));

            return $this->redirect('rooms');
        }
        $this->memory->remember($slug);

        return $this->redirectTo($stay ?? $this->url($to, ['room' => $slug]));
    }

    /**
     * A room's songs, picked from the main list: two columns, one search,
     * each column paged on its own ('page' left, 'rpage' right).
     */
    public function songs(Request $request): View|Response
    {
        $perPage = $this->limits->get('per_page');
        $sort    = $request->query('sort', 'artist');
        $dir     = $request->query('dir', 'asc');
        $q       = trim($request->query('q'));
        $roomId  = $this->room->id();

        $available = Pagination::of(
            fn (int $page): array => $this->songs->searchAvailable($q, $sort, $dir, $page, $perPage, $roomId),
            (int) $request->query('page', '1'),
            $perPage,
        );
        $inRoom = Pagination::of(
            fn (int $page): array => $this->songs->search($q, $sort, $dir, $page, $perPage, $roomId),
            (int) $request->query('rpage', '1'),
            $perPage,
        );

        return $this->view('room_songs', t('Songs of the room'), [
            'back'           => $this->destination($this->url('songs')),
            'available'      => $available['rows'],
            'availableTotal' => $available['total'],
            'pageNo'         => $available['page'],
            'pages'          => $available['pages'],
            'roomRows'       => $inRoom['rows'],
            'roomTotal'      => $inRoom['total'],
            'roomPageNo'     => $inRoom['page'],
            'roomPages'      => $inRoom['pages'],
            'roomSongCount'  => $this->songs->count($roomId),
            'mainCount'      => $this->songs->count(),
            'q'              => $q,
        ]);
    }

    public function songsAdd(Request $request): Response
    {
        return $this->pick($request, true);
    }

    public function songsRemove(Request $request): Response
    {
        return $this->pick($request, false);
    }

    /**
     * Single ids in key[] or, with all=1, every main-list song matching the
     * search q.
     */
    private function pick(Request $request, bool $add): Response
    {
        $roomId = $this->room->id();
        if ($roomId === RoomRepository::DEFAULT_ID) {
            $this->flash('error', t('“General” always offers the whole repertoire.'));

            return $this->redirect('songs');
        }

        $ids = $request->post('all') === '1'
            ? $this->songs->idsMatching($request->post('q'))
            : array_map('intval', $request->postList('key'));

        $n = $add ? $this->rooms->addSongs($roomId, $ids) : $this->rooms->removeSongs($roomId, $ids);
        if ($n > 0) {
            $this->settings->increment(RoomRepository::REVISION_KEY);
        }

        $this->flash('ok', $add
            ? tn('{n} song added to the room.', '{n} songs added to the room.', $n)
            : tn('{n} song removed from the room.', '{n} songs removed from the room.', $n));

        return $this->redirectTo($this->back($this->url('room_songs')));
    }

    /**
     * The room's address as a QR code -- for table cards, posters, a slide.
     * Made on this server (QrCode), so the address goes to no third party.
     */
    public function qr(Request $request): View
    {
        $address = $this->support->urls->absolute($this->url('songs', ['room' => $this->room->slug()]));

        return $this->view('room_qr', t('QR code'), [
            // "Back" leads where one came from -- the room list or the
            // room's edit form, both hand their address over; the list is
            // the fallback.
            'back'    => $this->destination($this->url('rooms')),
            'address' => $address,
            'svg'     => QrCode::svg($address),
            'hasPng'  => function_exists('imagecreate'),
        ]);
    }

    /** The same code as a file: the page offers it as SVG and as PNG. */
    public function qrImage(Request $request): Response
    {
        $format  = $request->routeString('format');
        $address = $this->support->urls->absolute($this->url('songs', ['room' => $this->room->slug()]));
        $image   = $format === 'svg' ? QrCode::svg($address) : QrCode::png($address);

        if ($image === null) {
            // PNG needs the gd extension; the page then offers SVG alone.
            $this->notFound();
        }

        $file = 'songwunsch-' . ($this->room->isMain() ? 'main' : $this->room->slug());

        return Response::file($image, $format === 'svg' ? 'image/svg+xml' : 'image/png', [
            'Content-Disposition' => 'inline; filename="' . $file . '.' . $format . '"',
        ]);
    }
}
