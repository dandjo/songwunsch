<?php

declare(strict_types=1);

namespace Songwunsch\Controller;

use RuntimeException;
use Songwunsch\ErrorPresenter;
use Songwunsch\Format;
use Songwunsch\Http\Request;
use Songwunsch\Http\Response;
use Songwunsch\Limits;
use Songwunsch\Pagination;
use Songwunsch\RoomContext;
use Songwunsch\RoomRepository;
use Songwunsch\RoomServices;
use Songwunsch\Settings;
use Songwunsch\SongRepository;
use Songwunsch\SuggestionRepository;
use Songwunsch\Template\View;
use Songwunsch\WishGuard;
use Songwunsch\WishRepository;

/**
 * The repertoire: the start page, and the editors' song form.
 */
final class SongController extends Controller
{
    public function __construct(
        Support $support,
        private readonly SongRepository $songs,
        private readonly SuggestionRepository $suggestions,
        private readonly RoomRepository $rooms,
        private readonly WishRepository $wishes,
        private readonly Settings $settings,
        private readonly Limits $limits,
        private readonly WishGuard $guard,
        private readonly RoomContext $room,
        private readonly RoomServices $roomServices,
        private readonly ErrorPresenter $errors,
    ) {
        parent::__construct($support);
    }

    /** The song list: what the audience picks from, in the room it is in. */
    public function index(Request $request): View
    {
        $perPage = $this->limits->get('per_page');
        $sort    = $request->query('sort', 'artist');
        $dir     = $request->query('dir', 'asc');
        $q       = trim($request->query('q'));
        $roomId  = $this->room->id();

        // Through Pagination::of(): when the last songs of a page are gone --
        // a live update brings another editor's delete -- the page that is
        // left takes its place instead of an empty list.
        $result = Pagination::of(
            fn (int $page): array => $this->songs->search($q, $sort, $dir, $page, $perPage, $roomId),
            max(1, (int) $request->query('page', '1')),
            $perPage,
        );

        return $this->view('home', t('Repertoire'), [
            'repo'    => $this->songs,
            'rows'    => $result['rows'],
            'total'   => $result['total'],
            'q'       => $q,
            'sort'    => array_key_exists($sort, $this->songs->sortableFields()) ? $sort : 'artist',
            'dir'     => strtolower($dir) === 'desc' ? 'desc' : 'asc',
            'pageNo'  => $result['page'],
            'perPage' => $perPage,
            'pages'   => $result['pages'],
            // No wish buttons while the room is closed, and so no form token.
            'formToken' => $this->guard->isPaused() ? '' : $this->guard->formToken(),
            // Without a search the total is the room's song count, which is
            // what the badge on the tab shows: the shell need not count
            // again. With a search the two differ and the badge keeps its
            // own number.
        ] + ($q === '' ? ['songCount' => $result['total']] : []));
    }

    /**
     * Add or edit a song -- and adopt a suggestion, which is the same form
     * filled from the suggestion: the editor adds length and genre.
     */
    public function form(Request $request): View|Response
    {
        $adopting = $request->routeName() === 'song_adopt';
        $key      = $adopting ? 0 : ($request->routeString('id') === 'new' ? 0 : $request->routeInt('id'));
        $song     = null;
        $adopt    = null;

        if ($key > 0) {
            $song = $this->songs->find($key);
            if ($song === null) {
                $this->notice('error', t('This song was not found.'));

                return $this->redirect('songs');
            }
        } elseif ($adopting) {
            $adopt = $this->suggestions->find($request->routeInt('id'));
            if ($adopt === null) {
                $this->notice('error', t('This suggestion was not found.'));

                return $this->redirect('suggestions');
            }
        }

        // After a failed validation the input and its errors are in the
        // session; otherwise the values come from the database.
        $kept   = $this->support->forms->take();
        $values = $kept['values'] ?? [
            'artist' => (string) ($adopt['artist'] ?? $song['artist'] ?? ''),
            'title'  => (string) ($adopt['title'] ?? $song['title'] ?? ''),
            'length' => Format::lengthInput($song['length_sec'] ?? null),
            'genre'  => (string) ($song['genre'] ?? ''),
        ];
        // Where an adopted wish queues: the top unless the editor chose the
        // bottom before a save failed.
        $values['wish_position'] = ($values['wish_position'] ?? '') === 'bottom' ? 'bottom' : 'top';

        return $this->view('song', match (true) {
            $adopt !== null => t('Adopt suggestion'),
            $key === 0      => t('Add song'),
            default         => t('Edit song'),
        }, [
            'repo'  => $this->songs,
            'key'   => $key,
            'adopt' => $adopt,
            // The room the suggestion was made in -- the song will join it.
            'adoptRoom' => $adopt !== null && (int) $adopt['room_id'] > 0
                ? $this->rooms->find((int) $adopt['room_id'])
                : null,
            // Already on the repertoire? Then Add offers the existing song
            // instead of creating a second one -- the form says so.
            'adoptExisting' => $adopt !== null
                ? $this->songs->findByName((string) $adopt['artist'], (string) $adopt['title'])
                : null,
            'errors' => $kept['errors'] ?? [],
            'back'   => $this->destination($this->url($adopt !== null ? 'suggestions' : 'songs')),
            'values' => $values,
        ]);
    }

    public function save(Request $request): Response
    {
        $key = (int) $request->post('key'); // 0 = new song
        // A new song may adopt a suggestion: the suggestion's id travels
        // with the form and is deleted once the song is in.
        $adopting = $key === 0 ? (int) $request->post('suggestion') : 0;

        $input = [
            'artist' => $request->post('artist'),
            'title'  => $request->post('title'),
            'length' => $request->post('length'),
            'genre'  => $request->post('genre'),
            // Where an adopted wish queues; anything but 'bottom' is the
            // top. Kept in the input so a failed save remembers the choice;
            // validate() only looks at the song fields.
            'wish_position' => $request->post('wish_position') === 'bottom' ? 'bottom' : 'top',
        ];
        $checked = $this->songs->validate($input);
        $formUrl = $adopting > 0
            ? $this->url('song_adopt', ['id' => $adopting, 'back' => $this->back()])
            : $this->url('song', ['id' => $key > 0 ? $key : 'new', 'back' => $this->back()]);

        if ($checked['errors'] !== []) {
            $this->support->forms->remember($input, $checked['errors']);
            $this->notice('error', t('Please check the highlighted fields.'));

            return $this->redirectTo($formUrl);
        }

        try {
            if ($key === 0) {
                $this->create($checked['values'], $input, $adopting);
            } else {
                $this->songs->update($key, $checked['values']);
                $this->settings->increment(RoomRepository::REVISION_KEY);
                $this->flash('ok', t('“{title}” by {artist} has been saved.', [
                    'title'  => $input['title'],
                    'artist' => $input['artist'],
                ]));
            }
        } catch (RuntimeException $e) {
            // Deleted row, missing write permission: keep the input.
            $detail = $this->errors->detail($e);
            $this->support->forms->remember($input, ['form' => $detail]);
            $this->flash('error', $detail);

            return $this->redirectTo($formUrl);
        }

        return $this->redirectTo($this->back());
    }

    /**
     * A new song, possibly out of a suggestion. Split off from save() only
     * because it is the one branch with more than a save in it.
     *
     * @param array<string,mixed>  $values the validated song
     * @param array<string,string> $input  what was typed, for the messages
     */
    private function create(array $values, array $input, int $adopting): void
    {
        // Adopting a song the repertoire already has -- it was suggested in
        // another room and adopted there, or overlooked: no second copy. The
        // existing song joins the room and the wish list instead; what the
        // editor typed for length and genre is not applied to it.
        $existing = $adopting > 0
            ? $this->songs->findByName((string) $values['artist'], (string) $values['title'])
            : null;
        if ($existing !== null) {
            $newId = (int) $existing['id'];
        } else {
            $newId = $this->songs->create($values);
            $this->settings->increment(RoomRepository::REVISION_KEY);
        }
        $args = ['title' => $input['title'], 'artist' => $input['artist']];

        // The suggestion has served its purpose. If someone deleted it
        // meanwhile, the song is in all the same. A suggestion made inside a
        // room puts the song into that room as well -- unless the room is gone.
        $adopted  = $adopting > 0 ? $this->suggestions->find($adopting) : null;
        $joinRoom = $adopted !== null && (int) $adopted['room_id'] > 0
            ? $this->rooms->find((int) $adopted['room_id'])
            : null;
        if ($adopting > 0) {
            $this->suggestions->delete($adopting);
            $this->settings->increment(SuggestionRepository::REVISION_KEY);
        }
        if ($joinRoom !== null && $this->rooms->addSongs((int) $joinRoom['id'], [$newId]) > 0 && $existing !== null) {
            // A new song raised the revision above already.
            $this->settings->increment(RoomRepository::REVISION_KEY);
        }

        // An adopted suggestion is a wish already: it goes onto the wish
        // list of the room it was made in, in the name of whoever suggested
        // it. A song that is open on that list already is counted once more
        // instead, like a guest's repeated wish.
        if ($adopted !== null) {
            $this->wishFor($newId, $joinRoom, $adopted, $existing !== null, (string) $input['wish_position']);
        }

        $this->flash('ok', match (true) {
            $existing !== null && $joinRoom !== null => t('“{title}” by {artist} was on the repertoire already – it has been added to room “{room}”, put on its wish list and taken off the suggestions.', $args + ['room' => (string) $joinRoom['name']]),
            $existing !== null => t('“{title}” by {artist} was on the repertoire already – it has been put on the wish list and taken off the suggestions.', $args),
            $joinRoom !== null => t('“{title}” by {artist} has been added to the repertoire and to room “{room}”, put on its wish list and taken off the suggestions.', $args + ['room' => (string) $joinRoom['name']]),
            $adopted !== null  => t('“{title}” by {artist} has been added to the repertoire, put on the wish list and taken off the suggestions.', $args),
            default            => t('“{title}” by {artist} has been added to the repertoire.', $args),
        });
    }

    /**
     * Put an adopted song onto the wish list of the room it was suggested in.
     *
     * @param array<string,mixed>|null $joinRoom
     * @param array<string,mixed>      $adopted
     */
    private function wishFor(int $songId, ?array $joinRoom, array $adopted, bool $wasExisting, string $position): void
    {
        $wishRoomId = $joinRoom !== null ? (int) $joinRoom['id'] : RoomRepository::DEFAULT_ID;
        $newSong    = $this->songs->find($songId);
        if ($newSong === null) {
            return;
        }

        $wishList = $wishRoomId === $this->room->id() ? $this->wishes : $this->roomServices->wishes($wishRoomId);
        $counted  = $wasExisting ? $wishList->wishAgain($songId) : null;
        if ($counted === null) {
            $wishId = $wishList->add($newSong, (string) ($adopted['suggester'] ?? ''));
            // add() appends, which is the bottom. The top is the default
            // because the editor adopts a suggestion right when it comes up,
            // and the audience should see it played soon.
            if ($position === 'top') {
                $wishList->moveToEnd($wishId, true);
            }
        }
        $guard = $wishRoomId === $this->room->id() ? $this->guard : $this->roomServices->guard($wishRoomId);
        $guard->touch();
    }

    public function delete(Request $request): Response
    {
        $song = $this->songs->find($request->routeInt('id'));

        if ($song === null) {
            $this->flash('error', t('This song was not found.'));

            return $this->redirectTo($this->back());
        }

        $this->songs->delete((int) $song['id']);
        $this->rooms->removeSongEverywhere((int) $song['id']);
        $this->settings->increment(RoomRepository::REVISION_KEY);
        $this->flash('ok', t('“{title}” has been removed from the repertoire. Wishes already received are kept.', [
            'title' => (string) $song['title'],
        ]));

        return $this->redirectTo($this->back());
    }
}
