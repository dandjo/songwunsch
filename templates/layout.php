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
/** @var bool $paused  wishing closed by the moderator */
/** @var array<string,mixed> $room  current room; the default room has id 0 */
/** @var array<int,array<string,mixed>> $roomList  all rooms, for the switcher */

$e      = static fn (?string $v): string => Format::e($v);
$inRoom = (int) $room['id'] !== RoomRepository::DEFAULT_ID;

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
<body data-endpoint="<?= $e(url()) ?>">
<a class="skip-link" href="#content"><?= $e(t('Skip to content')) ?></a>

<div class="cabinet">
    <header class="dome">
        <div class="dome__inner">
            <p class="dome__brand"><a href="<?= $e(url(['p' => 'songs'])) ?>">Song<span>wunsch</span></a></p>
            <?php if ($inRoom): ?>
                <p class="dome__room"><span class="sr-only"><?= $e(t('Room')) ?>: </span><?= $e((string) $room['name']) ?><?php if ((int) ($room['active'] ?? 1) === 0 && $security->can('rooms')): ?> <span class="tag"><?= $e(t('archived')) ?></span><?php endif; ?></p>
            <?php else: ?>
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
                                $page === 'room_songs' && $targetSlug !== '' => 'room_songs',
                                default                                     => 'songs',
                            };
                        ?>
                            <li>
                                <a href="<?= $e(url(['p' => $switchPage, 'room' => $targetSlug])) ?>"
                                   class="roomswitch__item<?= $active ? ' is-active' : '' ?>"<?= $active ? ' aria-current="true"' : '' ?>>
                                    <?= $e((string) $entry['name']) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <p class="roomswitch__none muted" data-roomfilter-empty hidden><?= $e(t('No room matches.')) ?></p>
                    </div>
                </details>
            <?php endif; ?>
            <?php if ($security->can('rooms')): ?>
                <a href="<?= $e(url(['p' => 'rooms'])) ?>"<?= in_array($page, ['rooms', 'room'], true) ? ' aria-current="page"' : '' ?>><?= $e(t('Rooms')) ?></a>
            <?php endif; ?>
            <a href="<?= $e(url(['p' => 'songs'])) ?>"<?= in_array($page, ['songs', 'room_songs'], true) ? ' aria-current="page"' : '' ?>><?= $e(t('Songs')) ?></a>
            <?php /* The wish list is visible to everyone; the counter badge only
                     for those who work it. Other areas only with a role. */ ?>
            <a href="<?= $e(url(['p' => 'wishes'])) ?>"<?= $page === 'wishes' ? ' aria-current="page"' : '' ?>>
                <?= $e(t('Wish list')) ?><?php if ($wishCount !== null && $security->can('wishes')): ?>
                    <span class="badge"><span aria-hidden="true"><?= (int) $wishCount ?></span><span class="sr-only"><?= $e(tn('{n} open wish', '{n} open wishes', (int) $wishCount)) ?></span></span>
                <?php endif; ?>
            </a>
            <?php if ($security->can('users')): ?>
                <a href="<?= $e(url(['p' => 'users'])) ?>"<?= in_array($page, ['users', 'user'], true) ? ' aria-current="page"' : '' ?>><?= $e(t('Users')) ?></a>
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

            <?php /* Account menu behind a person icon: Log in for guests, name
                     and Log out for the signed-in user. */ ?>
            <details class="account">
                <summary class="account__toggle" aria-label="<?= $e($security->isLoggedIn() ? t('Account: signed in as {name}', ['name' => $security->username()]) : t('Account: not signed in')) ?>"<?= $page === 'login' ? ' aria-current="page"' : '' ?>>
                    <svg class="account__icon" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false">
                        <circle cx="12" cy="8" r="4" fill="none" stroke="currentColor" stroke-width="1.6"/>
                        <path d="M4 20c0-4 3.6-6.5 8-6.5s8 2.5 8 6.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                    </svg>
                    <?php if ($security->isLoggedIn()): ?><span class="account__dot" aria-hidden="true"></span><?php endif; ?>
                </summary>
                <div class="account__menu">
                    <?php if ($security->isLoggedIn()): ?>
                        <p class="account__name"><span class="sr-only"><?= $e(t('Signed in as')) ?> </span><?= $e($security->username()) ?></p>
                        <form method="post" action="<?= $e(url()) ?>">
                            <input type="hidden" name="a" value="logout">
                            <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
                            <button type="submit" class="account__item"><?= $e(t('Log out')) ?></button>
                        </form>
                    <?php else: ?>
                        <a class="account__item<?= $page === 'login' ? ' is-active' : '' ?>" href="<?= $e(url(['p' => 'login'])) ?>"<?= $page === 'login' ? ' aria-current="page"' : '' ?>><?= $e(t('Log in')) ?></a>
                    <?php endif; ?>
                </div>
            </details>
        </div>

        <?php if ($paused): ?>
            <p class="dome__notice" role="status">
                <svg class="dome__notice-icon" viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false">
                    <circle cx="12" cy="12" r="11" fill="currentColor" opacity=".18"/>
                    <rect x="8" y="7" width="3" height="10" rx="1" fill="currentColor"/>
                    <rect x="13" y="7" width="3" height="10" rx="1" fill="currentColor"/>
                </svg>
                <span>
                    <strong><?= t('Wishing is paused in {room} right now.', [
                        'room' => '<span class="dome__notice-room">' . $e((string) $room['name']) . '</span>',
                    ]) ?></strong>
                    <?php if ($security->can('wishes')): ?>
                        <?= t('You can resume it on the room’s {wishlist}.', [
                            'wishlist' => '<a href="' . $e(url(['p' => 'wishes'])) . '">' . $e(t('wish list')) . '</a>',
                        ]) ?>
                    <?php else: ?>
                        <?= $e(t('The song list stays open – wishing will be back later.')) ?>
                    <?php endif; ?>
                </span>
            </p>
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

        <?php require __DIR__ . '/' . $template . '.php'; ?>
    </main>

    <footer class="base">
        <p>Powered by <a href="https://magicmusic.at" rel="noopener">magicmusic.at</a></p>
    </footer>
</div>
</body>
</html>
