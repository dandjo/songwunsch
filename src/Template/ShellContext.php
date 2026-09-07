<?php

declare(strict_types=1);

namespace Songwunsch\Template;

use Songwunsch\Colors;
use Songwunsch\FlashBag;
use Songwunsch\GuestName;
use Songwunsch\Http\Request;
use Songwunsch\LiveSignal;
use Songwunsch\LiveTokens;
use Songwunsch\PageRepository;
use Songwunsch\RoomContext;
use Songwunsch\RoomMemory;
use Songwunsch\RoomRepository;
use Songwunsch\Routing\RouteCollection;
use Songwunsch\Routing\UrlGenerator;
use Songwunsch\Security;
use Songwunsch\Settings;
use Songwunsch\SongRepository;
use Songwunsch\SuggestionRepository;
use Songwunsch\Translator;
use Songwunsch\Ui;
use Songwunsch\Uploads;
use Songwunsch\WishRepository;
use Throwable;

/**
 * Everything the page shell shows, around whatever the page itself shows.
 *
 * The header with its tab counters and the room switcher, the closed-room
 * notice, the logo, the colours, the footer, the pop-up message, the
 * first-visit name question and the live-update address: on every screen the
 * same, so no controller says any of it. A controller returns a View with
 * its own rows and fields; this puts the shell around them.
 *
 * The long constructor is deliberate and is the honest price of the page
 * shell: it lists exactly what one rendered screen costs. Everything here is
 * lazy, so a redirect, a poll, an image or a JSON answer builds none of it.
 */
final class ShellContext
{
    public function __construct(
        private readonly Request $request,
        private readonly RouteCollection $routes,
        private readonly UrlGenerator $urls,
        private readonly Security $security,
        private readonly Settings $settings,
        private readonly Translator $translator,
        private readonly RoomContext $roomContext,
        private readonly RoomRepository $rooms,
        private readonly RoomMemory $roomMemory,
        private readonly SongRepository $songs,
        private readonly WishRepository $wishes,
        private readonly SuggestionRepository $suggestions,
        private readonly PageRepository $pages,
        private readonly Uploads $uploads,
        private readonly Ui $ui,
        private readonly LiveTokens $live,
        private readonly GuestName $guestName,
        private readonly FlashBag $flash,
    ) {
    }

    /**
     * @return array<string,mixed> the variables templates/layout.php receives
     */
    public function build(View $view): array
    {
        $page   = $this->request->page();
        $room   = $this->roomContext->room();
        $roomId = $this->roomContext->id();

        // The current address, with its query string: the forms in the header
        // that must land back on this very page, and the language links. A
        // failure so early that no route was matched still has to render the
        // page that says so, hence the fallback.
        $routeName   = $this->request->routeName();
        $routeParams = $this->request->routeParams();
        try {
            $here = $this->urls->generate($routeName, $routeParams + $this->request->queryAll());
        } catch (Throwable $e) {
            $routeName = 'songs';
            $routeParams = [];
            $here = $this->urls->generate('songs');
        }

        $shell = [
            'page'       => $page,
            'routeName'  => $routeName,
            'title'      => $view->title,
            'template'   => $view->template,
            'editor'     => $view->editor,   // load CKEditor for a textarea[data-editor] on this page
            'room'       => $room,
            'security'   => $this->security,
            'settings'   => $this->settings,
            'translator' => $this->translator,
            'csrf'       => $this->security->csrfToken(),
            'flash'      => $this->flash->take(),
            'here'       => $here,
            // A checked ?back= of this address, for links that pass it on.
            'backParam'  => $this->urls->safeTarget($this->request->query('back')),
            'langLinks'  => [],
            'toastSec'   => 0,     // how long a pop-up message stays, 0 = until dismissed
            'roomCount'  => null,  // badge on the Rooms tab: active rooms besides the main one (editors and admins)
            'songCount'  => null,  // badge on the Repertoire tab: songs in the room, or on the main list
            'wishCount'  => null,
            'suggestionCount' => null, // badge on the Suggestions tab, for everyone
            'live'       => null,  // polling for live updates; see LiveTokens for the two tokens
            'paused'     => false, // wishing closed by the moderator -- notice in the header
            'roomList'   => [],    // rooms for the switcher in the header
            'ownRooms'   => [],    // guests: the unlisted rooms they entered, "Your rooms" in the switcher
            'guestName'  => null,  // the visitor's name for wishes, account menu
            'askName'    => false, // first visit: ask for the name (dialog in the layout)
            'footer'     => '',    // the operator's own footer line, HTML printed as is
            'footerLang' => '',    // the language that line is written in, '' when it is the interface language
            'footerPages' => [],   // the admins' pages the footer links, in order
            'colorsCss'  => '',    // colour overrides the admins set under Colours, '' = stylesheet defaults
            'logo'       => null,  // the live header logo (Uploads)
        ];

        // The language switcher: the current address with ?lang=<code>.
        try {
            foreach ($this->translator->available() as $code => $name) {
                $shell['langLinks'][$code] = [
                    'name' => $name,
                    'href' => $this->urls->generate(
                        $routeName,
                        $routeParams + $this->request->queryAll() + ['lang' => $code],
                    ),
                ];
            }
        } catch (Throwable $e) {
            $shell['langLinks'] = [];
        }

        // From here on the database is involved. A failure must still leave
        // a page that can carry the error message, so the shell falls back
        // to the defaults above instead of taking the whole answer down.
        try {
            $shell = $this->withData($shell, $view, $page, $roomId, $room);
        } catch (Throwable $e) {
            // Nothing to add; the defaults stand and the view says what
            // went wrong.
        }

        // The template's own values last: a page that has already counted
        // its rows reports the number instead of making the shell ask for
        // it a second time.
        return $view->vars + $shell;
    }

    /**
     * @param array<string,mixed> $shell
     * @param array<string,mixed> $room
     * @return array<string,mixed>
     */
    private function withData(array $shell, View $view, string $page, int $roomId, array $room): array
    {
        $shell['guestName'] = $this->guestName->current();
        $shell['toastSec']  = $this->ui->get('toast_sec');
        $shell['paused']    = $this->live->paused();

        $route    = $this->routes->get($this->request->routeName());
        $interval = $route->isRaw() ? 0 : $this->live->interval($page);
        if ($interval > 0) {
            // The doorbell open pages ask for instead of asking PHP, and its
            // value right now; written here if it is missing, which it is
            // after a deployment. Empty when it cannot be written -- an
            // assets folder the web server may not write into: then every
            // poll goes to PHP, as it did before the doorbell existed.
            $signal = LiveSignal::ensure();
            $shell['live'] = [
                // The poll address is this page's own, ids and all.
                'url'      => $this->urls->generate(
                    $this->request->routeName(),
                    $this->request->routeParams() + ['poll' => 1],
                ),
                'rev'      => $this->live->content($page),
                'head'     => $this->live->head(),
                'interval' => $interval,
                'gate'     => $signal === '' ? '' : LiveSignal::url(),
                'signal'   => $signal,
            ];
        }

        // The room switcher: guests get the listed rooms only, plus the
        // unlisted rooms they entered through their address, under "Your
        // rooms". Rooms that are gone, archived or listed by now leave that
        // memory.
        $shell['roomList'] = $this->rooms->names(!$this->security->isLoggedIn());
        // A signed-in user in an archived room (reached through its address
        // or the room list): the switcher offers that room as well, tagged,
        // in its place by name -- as the entry marked current and as the way
        // to any other room. Without it the switcher would name a room it
        // does not list, or -- with no other room active -- not appear at all.
        if ($this->security->isLoggedIn() && $roomId !== RoomRepository::DEFAULT_ID && (int) ($room['active'] ?? 1) === 0) {
            $entry = ['id' => $roomId, 'slug' => (string) $room['slug'], 'name' => (string) $room['name'], 'active' => 0];
            $at    = count($shell['roomList']);
            foreach ($shell['roomList'] as $i => $listed) {
                if (strcasecmp((string) $listed['name'], (string) $entry['name']) > 0) {
                    $at = $i;
                    break;
                }
            }
            array_splice($shell['roomList'], $at, 0, [$entry]);
        }
        if (!$this->security->isLoggedIn()) {
            $own = array_values(array_filter(
                $this->rooms->bySlugs($this->roomMemory->visited()),
                static fn (array $r): bool => (int) $r['active'] === 1 && (int) $r['listed'] === 0,
            ));
            $this->roomMemory->pruneVisited(array_map(static fn (array $r): string => (string) $r['slug'], $own));
            $shell['ownRooms'] = $own;
        }

        // The footer links the pages the admins put there, on every screen,
        // and carries the operator's own line below them.
        $shell['footerPages'] = $this->pages->footerLinks();
        $footerLine           = $this->pages->footerLine();
        $shell['footer']      = $footerLine['html'] ?? '';
        $shell['footerLang']  = $footerLine !== null && $footerLine['lang'] !== $this->translator->code()
            ? $footerLine['lang']
            : '';

        // Visitors without a login are asked for their name once, on the
        // public pages where wishing happens. Staff in the guest view see it
        // as well -- that is what the guest view is for.
        $shell['askName'] = !$this->security->isLoggedIn()
            && $this->guestName->shouldAsk()
            && in_array($page, ['songs', 'wishes', 'suggestions', 'rooms'], true);

        // The live logo takes the word mark's place in the header; a deleted
        // one falls back to the word mark by itself.
        $logoId        = (int) $this->settings->get(Settings::LOGO_ID, '0');
        $shell['logo'] = $logoId > 0 ? $this->uploads->info($logoId) : null;
        // The colours set under Interface, as a :root block over the stylesheet.
        $shell['colorsCss'] = Colors::css(Colors::load($this->settings));

        // The counters on the Repertoire, Wish list and Suggestions tabs --
        // for everyone, guests included; the pages behind them are public as
        // well. The Rooms tab, and so its counter, only for those who see
        // the tab. A page that read the number already says so in its own
        // values and is not asked again.
        if (!array_key_exists('roomCount', $view->vars) && $this->security->can('rooms')) {
            $shell['roomCount'] = $this->rooms->count();
        }
        if (!array_key_exists('songCount', $view->vars)) {
            $shell['songCount'] = $this->songs->count($roomId);
        }
        if (!array_key_exists('wishCount', $view->vars)) {
            $shell['wishCount'] = $this->wishes->count();
        }
        if (!array_key_exists('suggestionCount', $view->vars)) {
            $shell['suggestionCount'] = $this->suggestions->count();
        }

        return $shell;
    }
}
