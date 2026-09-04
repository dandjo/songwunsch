<?php

declare(strict_types=1);

use Songwunsch\Format;
use Songwunsch\RoomRepository;
use Songwunsch\Security;
use Songwunsch\Translator;

/** @var string $title */
/** @var string $template */
/** @var string $page */
/** @var string $csrf */
/** @var Security $security */
/** @var Translator $translator */
/** @var array{type:string,message:string}|null $flash */
/** @var int|null $wishCount */
/** @var int|null $suggestionCount  open song suggestions, badge for editors */
/** @var bool $paused  wishing closed by the moderator */
/** @var \Songwunsch\Settings $settings */
/** @var array<string,mixed> $room  current room; the default room has id 0 */
/** @var array<int,array<string,mixed>> $roomList  all rooms, for the switcher */
/** @var string|null $guestName  the visitor's name for wishes, from the cookie */
/** @var bool $askName           first visit: ask for the name in a dialog */
/** @var array{url:string,rev:string}|null $live  polling address and current revision, wish list and suggestions */
/** @var string $footer  HTML for the footer from config.php ('footer'); empty = no footer */
/** @var array{id:int,mime:string,width:?int,height:?int}|null $logo  the live header logo, see Uploads */

$e      = static fn (?string $v): string => Format::e($v);
$inRoom = (int) $room['id'] !== RoomRepository::DEFAULT_ID;

// The account menu shows who is really signed in -- also in the guest view,
// where $security->user() and everything else answer as for a stranger.
$account   = $security->account();
$guestView = $security->guestView();
// Where a switch of the view leads back to: the current address.
$here      = url(array_merge(['p' => $page], $_GET));

// Language switcher: the current address with ?lang=<code> added.
$langLinks = [];
foreach ($translator->available() as $code => $name) {
    $langLinks[$code] = ['name' => $name, 'href' => url(array_merge(['p' => $page], $_GET, ['lang' => $code]))];
}
?>
<!doctype html>
<html lang="<?= $e($translator->htmlLang()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark">
    <title><?= $e($title) ?><?= $inRoom ? ' · ' . $e((string) $room['name']) : '' ?> · Songwunsch</title>
    <link rel="stylesheet" href="<?= $e(asset('assets/style.css')) ?>">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><text y='26' font-size='26'>🎵</text></svg>">
</head>
<?php /* data-live: the wish list and the suggestions poll this address for
         their revision and reload themselves when it moved on (app.js). */ ?>
<body data-endpoint="<?= $e(url()) ?>"<?php if (($live ?? null) !== null): ?> data-live="<?= $e($live['url']) ?>" data-live-rev="<?= $e($live['rev']) ?>" data-msg-updated="<?= $e(t('The list has been updated.')) ?>"<?php endif; ?>>
<a class="skip-link" href="#content"><?= $e(t('Skip to content')) ?></a>

<div class="cabinet">
    <header class="dome">
        <div class="dome__inner<?= $logo !== null ? ' dome__inner--logo' : '' ?>">
            <?php if ($logo !== null): ?>
                <?php /* The admin's logo takes the place of word mark and claim; CSS
                         scales it to the header's height, width and height keep the
                         layout still while it loads. In a room the room's name stands
                         beside it. */ ?>
                <p class="dome__brand dome__brand--logo">
                    <a href="<?= $e(url(['p' => 'songs'])) ?>"><img class="dome__logo" src="<?= $e(url(['p' => 'logo', 'room' => '', 'id' => $logo['id']])) ?>" alt="Songwunsch"<?php
                        if ($logo['width'] !== null && $logo['height'] !== null): ?> width="<?= (int) $logo['width'] ?>" height="<?= (int) $logo['height'] ?>"<?php endif; ?>></a>
                </p>
            <?php else: ?>
                <p class="dome__brand"><a href="<?= $e(url(['p' => 'songs'])) ?>">Song<span>wunsch</span></a></p>
            <?php endif; ?>
            <?php if ($inRoom): ?>
                <p class="dome__room"><span class="sr-only"><?= $e(t('Room')) ?>: </span><?= $e((string) $room['name']) ?><?php if ((int) ($room['active'] ?? 1) === 0 && $security->can('rooms')): ?> <span class="tag"><?= $e(t('archived')) ?></span><?php endif; ?></p>
            <?php elseif ($logo === null): ?>
                <p class="dome__claim"><?= $e(t('Pick your song – we will play it')) ?></p>
            <?php endif; ?>
        </div>

        <nav class="nav" aria-label="<?= $e(t('Main navigation')) ?>">
            <?php if ($roomList !== []): ?>
                <?php /* Room switcher: a tab-styled <details> like the language menu,
                         left of the page tabs. Lists the main room and every room;
                         works without JavaScript. */ ?>
                <details class="roomswitch">
                    <summary class="roomswitch__toggle" aria-label="<?= $e(t('Switch room')) ?>: <?= $e((string) $room['name']) ?>">
                        <span class="roomswitch__current"><?= $e((string) $room['name']) ?></span>
                        <svg class="roomswitch__chevron" viewBox="0 0 16 16" width="12" height="12" aria-hidden="true" focusable="false">
                            <path d="M3 6l5 5 5-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </summary>
                    <div class="roomswitch__panel">
                    <?php if (count($roomList) > 6): ?>
                        <?php /* Filter field, wired up by app.js; without JavaScript
                                 the full list simply stays visible. */ ?>
                        <label class="roomswitch__filter">
                            <span class="sr-only"><?= $e(t('Filter rooms')) ?></span>
                            <input type="search" data-roomfilter placeholder="<?= $e(t('Filter …')) ?>" autocomplete="off">
                        </label>
                    <?php endif; ?>
                    <ul class="roomswitch__menu" role="list">
                        <?php
                        // Switching stays on the current sub-page when the target room
                        // has it: wish list to wish list, song picker to song picker.
                        // The main room has no picker; pages outside rooms (users,
                        // login, ...) lead to the song list.
                        $switchable = array_merge([RoomRepository::defaultRoom()], $roomList);
                        foreach ($switchable as $entry):
                            $active     = (int) $entry['id'] === (int) $room['id'];
                            $targetSlug = (string) ($entry['slug'] ?? '');
                            $switchPage = match (true) {
                                $page === 'wishes'                          => 'wishes',
                                $page === 'suggestions'                     => 'suggestions',
                                $page === 'room_songs' && $targetSlug !== '' => 'room_songs',
                                default                                     => 'songs',
                            };
                        ?>
                            <li>
                                <?php if ($targetSlug === ''): ?>
                                    <?php /* The main room has no address of its own that could
                                             be told apart from a bare visit, so choosing it is
                                             a POST that clears the remembered room (RoomMemory). */ ?>
                                    <form method="post" action="<?= $e(url(['p' => $page])) ?>">
                                        <input type="hidden" name="a" value="room_switch">
                                        <input type="hidden" name="slug" value="">
                                        <input type="hidden" name="to" value="<?= $e($switchPage) ?>">
                                        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                                        <button type="submit" class="roomswitch__item<?= $active ? ' is-active' : '' ?>"<?= $active ? ' aria-current="true"' : '' ?>>
                                            <?= $e((string) $entry['name']) ?>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <a href="<?= $e(url(['p' => $switchPage, 'room' => $targetSlug])) ?>"
                                       class="roomswitch__item<?= $active ? ' is-active' : '' ?>"<?= $active ? ' aria-current="true"' : '' ?>>
                                        <?= $e((string) $entry['name']) ?>
                                    </a>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <p class="roomswitch__none muted" data-roomfilter-empty hidden><?= $e(t('No room matches.')) ?></p>
                    </div>
                </details>
            <?php endif; ?>
            <?php if ($security->can('rooms')): ?>
                <a href="<?= $e(url(['p' => 'rooms'])) ?>"<?= in_array($page, ['rooms', 'room'], true) ? ' aria-current="page"' : '' ?>><?= icon('door') ?><?= $e(t('Rooms')) ?></a>
            <?php endif; ?>
            <a href="<?= $e(url(['p' => 'songs'])) ?>"<?= in_array($page, ['songs', 'room_songs'], true) ? ' aria-current="page"' : '' ?>><?= icon('note') ?><?= $e(t('Repertoire')) ?></a>
            <?php /* The wish list is visible to everyone; the counter badge only
                     for those who work it. Other areas only with a role. */ ?>
            <a href="<?= $e(url(['p' => 'wishes'])) ?>"<?= $page === 'wishes' ? ' aria-current="page"' : '' ?>>
                <?= icon('star') ?><?= $e(t('Wish list')) ?><?php if ($wishCount !== null && $security->can('wishes')): ?>
                    <span class="badge"><span aria-hidden="true"><?= (int) $wishCount ?></span><span class="sr-only"><?= $e(tn('{n} open wish', '{n} open wishes', (int) $wishCount)) ?></span></span>
                <?php endif; ?>
            </a>
            <?php /* Song suggestions: everyone may suggest, so the tab is
                     public; the counter only for the editors who work it. */ ?>
            <a href="<?= $e(url(['p' => 'suggestions'])) ?>"<?= $page === 'suggestions' ? ' aria-current="page"' : '' ?>>
                <?= icon('bulb') ?><?= $e(t('Suggestions')) ?><?php if ($suggestionCount !== null && $security->can('suggestions')): ?>
                    <span class="badge"><span aria-hidden="true"><?= (int) $suggestionCount ?></span><span class="sr-only"><?= $e(tn('{n} open suggestion', '{n} open suggestions', (int) $suggestionCount)) ?></span></span>
                <?php endif; ?>
            </a>
            <?php if ($security->can('users')): ?>
                <a href="<?= $e(url(['p' => 'users'])) ?>"<?= in_array($page, ['users', 'user'], true) ? ' aria-current="page"' : '' ?>><?= icon('users') ?><?= $e(t('Users')) ?></a>
                <a href="<?= $e(url(['p' => 'logos'])) ?>"<?= $page === 'logos' ? ' aria-current="page"' : '' ?>><?= icon('image') ?><?= $e(t('Logos')) ?></a>
            <?php endif; ?>
        </nav>

        <?php /* Top right next to the word mark, outside the navigation so on
                 phones they stay in the first row while the navigation wraps
                 below: the language menu and the account menu. Both are
                 <details> popouts -- they work without JavaScript and are
                 keyboard-accessible. */ ?>
        <div class="dome__tools">
        <?php if (count($langLinks) > 1): ?>
            <?php /* Compact language menu; the list scales to any number of languages. */ ?>
            <details class="lang">
                <summary class="lang__toggle" aria-label="<?= $e(t('Language')) ?>: <?= $e($langLinks[$translator->code()]['name'] ?? $translator->code()) ?>">
                    <svg class="lang__icon" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false">
                        <circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="1.6"/>
                        <path d="M3 12h18M12 3c3 3.2 3 14.8 0 18M12 3c-3 3.2-3 14.8 0 18" fill="none" stroke="currentColor" stroke-width="1.6"/>
                    </svg>
                    <span class="lang__current"><?= $e(strtoupper($translator->code())) ?></span>
                </summary>
                <ul class="lang__menu" role="list">
                    <?php foreach ($langLinks as $code => $link): ?>
                        <?php $active = $code === $translator->code(); ?>
                        <li>
                            <a href="<?= $e($link['href']) ?>" lang="<?= $e($code) ?>" hreflang="<?= $e($code) ?>"
                               class="lang__item<?= $active ? ' is-active' : '' ?>"<?= $active ? ' aria-current="true"' : '' ?>>
                                <?= $e($link['name']) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </details>
        <?php endif; ?>

            <?php /* Account menu behind a person icon: Log in for guests; name,
                     guest view switch and Log out for the signed-in user. In
                     the guest view the dot becomes a ring. */ ?>
            <?php
            $accountLabel = match (true) {
                $account === null && $guestName !== null => t('Account: not signed in, wishing as {name}', ['name' => $guestName]),
                $account === null => t('Account: not signed in'),
                $guestView        => t('Account: {name}, guest view on', ['name' => (string) $account['username']]),
                default           => t('Account: signed in as {name}', ['name' => (string) $account['username']]),
            };
            // Where the name form leads: /name, back to the current address.
            $nameHref = url(['p' => 'name', 'back' => $here]);
            ?>
            <details class="account">
                <summary class="account__toggle" aria-label="<?= $e($accountLabel) ?>"<?= $page === 'login' ? ' aria-current="page"' : '' ?>>
                    <svg class="account__icon" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false">
                        <circle cx="12" cy="8" r="4" fill="none" stroke="currentColor" stroke-width="1.6"/>
                        <path d="M4 20c0-4 3.6-6.5 8-6.5s8 2.5 8 6.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                    </svg>
                    <?php if ($account !== null): ?><span class="account__dot<?= $guestView ? ' account__dot--guest' : '' ?>" aria-hidden="true"></span><?php endif; ?>
                </summary>
                <div class="account__menu">
                    <?php if ($account !== null): ?>
                        <p class="account__name"><span class="sr-only"><?= $e(t('Signed in as')) ?> </span><?= $e((string) $account['username']) ?></p>
                        <?php /* Staff wish too; the name from the cookie goes
                                 with their wishes like with anyone else's. */ ?>
                        <a class="account__item<?= $page === 'name' ? ' is-active' : '' ?>" href="<?= $e($nameHref) ?>"<?= $page === 'name' ? ' aria-current="page"' : '' ?>>
                            <?php /* The name as a small chip, pre-escaped into the translation. */ ?>
                            <?= icon('user', 14) ?><span class="account__label"><?= $guestName !== null
                                ? t('Wishing as {name}', ['name' => '<span class="account__value">' . $e($guestName) . '</span>'])
                                : $e(t('Name for wishes')) ?></span>
                        </a>
                        <?php /* Personal settings (password, delete confirmations)
                                 -- they concern the account only, so they live here
                                 and not among the page tabs. */ ?>
                        <a class="account__item<?= $page === 'settings' ? ' is-active' : '' ?>" href="<?= $e(url(['p' => 'settings'])) ?>"<?= $page === 'settings' ? ' aria-current="page"' : '' ?>>
                            <?= icon('gear', 14) ?><span class="account__label"><?= $e(t('Settings')) ?></span>
                        </a>
                        <?php /* See the site as a visitor without a login does
                                 -- to check what guests get -- and back. Posts
                                 to the current page so the server knows where
                                 the switch happened. */ ?>
                        <form method="post" action="<?= $e(url(['p' => $page])) ?>">
                            <input type="hidden" name="a" value="guest_view">
                            <input type="hidden" name="on" value="<?= $guestView ? '0' : '1' ?>">
                            <input type="hidden" name="back" value="<?= $e($here) ?>">
                            <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                            <button type="submit" class="account__item<?= $guestView ? ' is-active' : '' ?>" aria-pressed="<?= $guestView ? 'true' : 'false' ?>"><?= icon('eye', 14) ?><span class="account__label"><?= $e(t('View as guest')) ?></span></button>
                        </form>
                        <form method="post" action="<?= $e(url()) ?>">
                            <input type="hidden" name="a" value="logout">
                            <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                            <button type="submit" class="account__item"><?= icon('logout', 14) ?><span class="account__label"><?= $e(t('Log out')) ?></span></button>
                        </form>
                    <?php else: ?>
                        <?php /* A visitor heads the menu with the name they gave
                                 for the wish list -- the way a signed-in user
                                 heads it with their username. */ ?>
                        <?php if ($guestName !== null): ?>
                            <p class="account__name"><span class="sr-only"><?= $e(t('Your name on the wish list:')) ?> </span><?= $e($guestName) ?></p>
                        <?php else: ?>
                            <p class="account__name account__name--none"><?= $e(t('No name yet')) ?></p>
                        <?php endif; ?>
                        <a class="account__item<?= $page === 'name' ? ' is-active' : '' ?>" href="<?= $e($nameHref) ?>"<?= $page === 'name' ? ' aria-current="page"' : '' ?>>
                            <?= icon('user', 14) ?><span class="account__label"><?= $e($guestName !== null ? t('Change name') : t('Set name')) ?></span>
                        </a>
                        <a class="account__item<?= $page === 'login' ? ' is-active' : '' ?>" href="<?= $e(url(['p' => 'login'])) ?>"<?= $page === 'login' ? ' aria-current="page"' : '' ?>><?= icon('login', 14) ?><span class="account__label"><?= $e(t('Log in')) ?></span></a>
                    <?php endif; ?>
                </div>
            </details>
        </div>

        <?php if ($guestView): ?>
            <?php /* A visible reminder while the guest view is on -- otherwise
                     the missing controls look like a lost login. */ ?>
            <?php /* A <div>, not a <p>: the paragraph could not hold the form
                     -- browsers would close it early and drop the button
                     out of the notice. */ ?>
            <div class="dome__notice dome__notice--guest" role="status">
                <?= icon('eye', 22) ?>
                <span>
                    <strong><?= $e(t('Guest view.')) ?></strong>
                    <?= $e(t('You see the site as a visitor without a login does.')) ?>
                </span>
                <form method="post" action="<?= $e(url(['p' => $page])) ?>" class="dome__notice-action">
                    <input type="hidden" name="a" value="guest_view">
                    <input type="hidden" name="on" value="0">
                    <input type="hidden" name="back" value="<?= $e($here) ?>">
                    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                    <button type="submit" class="link-button"><?= $e(t('End guest view')) ?></button>
                </form>
            </div>
        <?php endif; ?>

        <?php if ($paused): ?>
            <?php /* A <div> like the guest notice: it may hold the form with
                     which a moderator opens the room right here. */ ?>
            <div class="dome__notice dome__notice--closed" role="status">
                <?php /* A stop sign: an octagon with a bar. */ ?>
                <svg class="dome__notice-icon" viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false">
                    <path d="M7.8 1.5h8.4l6.3 6.3v8.4l-6.3 6.3H7.8l-6.3-6.3V7.8z" fill="currentColor" opacity=".18"/>
                    <path d="M7.8 1.5h8.4l6.3 6.3v8.4l-6.3 6.3H7.8l-6.3-6.3V7.8z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                    <rect x="7" y="10.5" width="10" height="3" rx="1" fill="currentColor"/>
                </svg>
                <span>
                    <strong><?= t('{room} is closed right now.', [
                        'room' => '<span class="dome__notice-room">' . $e((string) $room['name']) . '</span>',
                    ]) ?></strong>
                    <?= $e($security->can('wishes')
                        ? t('The audience sees the repertoire but cannot wish or suggest anything.')
                        : t('The repertoire stays visible – wishing and suggesting will be back later.')) ?>
                </span>
                <?php if ($security->can('wishes')): ?>
                    <form method="post" action="<?= $e(url(['p' => $page])) ?>" class="dome__notice-action">
                        <input type="hidden" name="a" value="pause">
                        <input type="hidden" name="state" value="0">
                        <input type="hidden" name="back" value="<?= $e($here) ?>">
                        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                        <?php /* Like "End guest view" in the guest notice, in the notice's own colour. */ ?>
                        <button type="submit" class="link-button link-button--danger"><?= icon('play') ?><?= $e(t('Open room')) ?></button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($security->usesDefaultPassword()): ?>
            <?php /* Only the admin sees this, only while the shipped password is in use. */ ?>
            <p class="dome__notice dome__notice--warn" role="alert">
                <svg class="dome__notice-icon" viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false">
                    <path d="M12 2.5 22.5 20.5H1.5z" fill="currentColor" opacity=".18"/>
                    <path d="M12 2.5 22.5 20.5H1.5z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                    <path d="M12 9v5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
                    <circle cx="12" cy="17" r="1.3" fill="currentColor"/>
                </svg>
                <span>
                    <strong><?= $e(t('The admin account still uses the default password.')) ?></strong>
                    <?= t('Anyone who knows this software can sign in – {change}.', [
                        'change' => '<a href="' . $e(url(['p' => 'user', 'id' => (int) $security->user()['id']])) . '">' . $e(t('change it now')) . '</a>',
                    ]) ?>
                </span>
            </p>
        <?php endif; ?>
    </header>

    <main id="content" class="panel">
        <?php if ($flash !== null): ?>
            <p class="flash flash--<?= $e($flash['type']) ?>" role="status"><?= $e($flash['message']) ?></p>
        <?php endif; ?>

        <?php if ($askName): ?>
            <?php /* First visit: ask for the name. A <dialog> that is open from
                     the start, so without JavaScript it stands as a card at the
                     top of the panel; the inline script right behind it turns
                     it modal before anything below is painted. */ ?>
            <dialog class="namebox" open data-namebox aria-labelledby="namebox-title">
                <h2 class="namebox__title" id="namebox-title"><?= $e(t('Welcome! What is your name?')) ?></h2>
                <?php
                $nameBack = $here;
                $nameAsk  = true;
                require __DIR__ . '/_name_form.php';
                ?>
            </dialog>
            <script>(function (d) { if (d && typeof d.showModal === 'function') { d.removeAttribute('open'); d.showModal(); } }(document.currentScript.previousElementSibling));</script>
        <?php endif; ?>

        <?php require __DIR__ . '/' . $template . '.php'; ?>
    </main>

    <?php if ($footer !== ''): ?>
        <?php /* The operator's own line (credits, imprint link) -- HTML from
                 config.php, deliberately printed unescaped. */ ?>
        <footer class="base"><?= $footer ?></footer>
    <?php endif; ?>
</div>
<?php /* One script for every page; each enhancement checks for its markup. */ ?>
<script src="<?= $e(asset('assets/app.js')) ?>" defer></script>
</body>
</html>
