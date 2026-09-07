<?php

declare(strict_types=1);

namespace Songwunsch\EventListener;

use Songwunsch\FlashBag;
use Songwunsch\Http\Request;
use Songwunsch\Http\Response;
use Songwunsch\RoomContext;
use Songwunsch\RoomMemory;
use Songwunsch\RoomRepository;
use Songwunsch\Routing\RouteCollection;
use Songwunsch\Routing\UrlGenerator;
use Songwunsch\Schema;
use Songwunsch\Security;
use Songwunsch\Settings;
use Throwable;

/**
 * Which room is this request in?
 *
 * The address may name one (/rooms/<slug>/...), the visitor may have been in
 * one before (RoomMemory), and the editors may have set a room new visitors
 * start in. All of that used to sit inside the routing patterns themselves,
 * where a preg_match branch asked the database, wrote a cookie and
 * redirected. It is one step of its own now, downstream of a matcher that
 * only matches.
 *
 * The rules, unchanged:
 *
 *  - An unknown, malformed or -- for a guest -- archived room is no dead
 *    end: the visitor lands on the start page with a short notice. A link to
 *    a room that has since been deleted or renamed must not be a wall.
 *  - /rooms/<slug>/... names its room and is remembered. The bare /, /wishes
 *    and /suggestions lead into the remembered room, every time; only the
 *    explicit switch to the main room remembers the main room instead.
 *  - Pages without a room in their address stay in the remembered room, so
 *    the context never changes on its own.
 *  - A visitor with no memory at all who opens a bare address lands in the
 *    start room the editors set, if there is one.
 *  - The QR page names its room outright, the main room included: the
 *    address wins, the memory neither steps in nor changes -- an editor
 *    looking at a code is not entering the room.
 */
final class RoomListener implements RequestListener
{
    public function __construct(
        private readonly RouteCollection $routes,
        private readonly RoomContext $context,
        private readonly RoomRepository $rooms,
        private readonly RoomMemory $memory,
        private readonly Schema $schema,
        private readonly Security $security,
        private readonly Settings $settings,
        private readonly UrlGenerator $urls,
        private readonly FlashBag $flash,
    ) {
    }

    public function handle(Request $request): ?Response
    {
        $this->nameMainRoom();

        $route       = $this->routes->get($request->routeName());
        $routeParams = $request->routeParams();
        $isGet       = !$request->isPost();

        // Room-bound: the page belongs to a room and follows the memory.
        // The QR page is room-scoped as well but names its room itself.
        $roomBound = $route->isRoomScoped() && !$route->addressNamesRoom();

        $room        = RoomRepository::defaultRoom();
        $fromAddress = false;

        $slug = (string) $request->attribute('room', '');
        if ($slug !== '') {
            $found = $this->find($slug);
            if ($found === null) {
                $this->flash->notice('info', t('There is no room at this address – here is the start page.'));

                return Response::redirect($this->urls->generate('songs', ['room' => '']));
            }
            // An archived room is open to signed-in users only; for a guest
            // it is as good as gone.
            if ((int) $found['active'] === 0 && !$this->security->isLoggedIn()) {
                $this->flash->notice('info', t('This room has been archived – here is the start page.'));

                return Response::redirect($this->urls->generate('songs', ['room' => '']));
            }
            $room        = $found;
            $fromAddress = true;
        }

        $remembered = $this->memory->slug();
        $bareMain   = $roomBound && (int) $room['id'] === RoomRepository::DEFAULT_ID;

        // No memory at all, on a bare room page: the start room takes over.
        if ($remembered === null && $bareMain && $isGet) {
            $start = $this->startRoom();
            if ($start !== null) {
                return Response::redirect($this->urls->generate(
                    $request->routeName(),
                    ['room' => (string) $start['slug']] + $routeParams + $request->queryAll(),
                ));
            }
        }

        $useMemory = $remembered !== null && $remembered !== ''
            && !$route->addressNamesRoom()
            && (!$roomBound || (int) $room['id'] === RoomRepository::DEFAULT_ID);

        if ($useMemory) {
            $kept = $this->find((string) $remembered);
            if ($kept === null || ((int) $kept['active'] === 0 && !$this->security->isLoggedIn())) {
                // The room is gone -- or archived, which for a guest is the
                // same: the memory goes with it.
                $this->memory->forget();
            } elseif ($roomBound && $isGet) {
                return Response::redirect($this->urls->generate(
                    $request->routeName(),
                    ['room' => (string) $kept['slug']] + $routeParams + $request->queryAll(),
                ));
            } else {
                // A POST to a bare address, or a page outside any room:
                // handle it in the remembered room.
                $room = $kept;
            }
        }

        if ($roomBound && (int) $room['id'] !== RoomRepository::DEFAULT_ID) {
            $this->memory->remember((string) $room['slug']);
            // A guest entering an unlisted room through its address: the
            // switcher offers it to nobody, so it is kept under "Your rooms".
            if (!$this->security->isLoggedIn() && (int) ($room['listed'] ?? 0) === 0) {
                $this->memory->noteVisit((string) $room['slug']);
            }
        }

        $this->context->set($room, $fromAddress);

        return null;
    }

    /**
     * The main room may carry a name of its own and may be kept out of the
     * switcher (Rooms -> Edit on the main room). Both entries come in one
     * query: they share a prefix, and this runs on every request, the
     * live-update poll included.
     */
    private function nameMainRoom(): void
    {
        try {
            $main = $this->settings->withPrefix(RoomRepository::MAIN_KEY_PREFIX);
            RoomRepository::nameMainRoom((string) ($main['name'] ?? ''));
            RoomRepository::listMainRoom((string) ($main['listed'] ?? '1') === '1');
        } catch (Throwable $e) {
            // No database yet: the translated default stands, and the page
            // that follows reports the problem.
        }
    }

    /**
     * A room by its machine name. Anything the pattern rejects is unknown
     * without a query (RoomRepository::findBySlug); a database that is not
     * there yet is unknown as well, and the page behind this says so.
     *
     * @return array<string,mixed>|null
     */
    private function find(string $slug): ?array
    {
        try {
            $this->schema->ensure();

            return $this->rooms->findBySlug($slug);
        } catch (Throwable $e) {
            return null;
        }
    }

    /** @return array<string,mixed>|null the start room, if there is an active one */
    private function startRoom(): ?array
    {
        try {
            $this->schema->ensure();
            $id    = (int) $this->settings->get(RoomRepository::START_ROOM_KEY, '0');
            $start = $id > 0 ? $this->rooms->find($id) : null;
        } catch (Throwable $e) {
            return null;
        }

        // An archived start room does not receive visitors; they stay in the
        // main room.
        return $start !== null && (int) $start['active'] === 1 ? $start : null;
    }
}
