<?php

declare(strict_types=1);

namespace Songwunsch;

/**
 * Pages the admins write -- an imprint, FAQs, a privacy notice. Every page
 * is public under /pages/<slug> and may link to any other. The footer at the
 * bottom of every screen links the pages that have a footer_position, in
 * that order (Administration -> Footer); a page without one is reachable
 * through its address alone. The body is HTML from the editor, reduced to an
 * allowed set of tags on save (Html::clean()).
 *
 * Languages: a page is its address (the pages table) plus a title and body
 * per language of the language menu (page_translations, one row each). Every
 * language is equal; a page needs at least one. A reader gets the row in the
 * interface language and, where the page has none, the first language of the
 * fallback order (LANGUAGES_KEY, an admin setting) the page does have. Every
 * read method answers in the interface language that way, so the callers
 * never see more than one title and body per page. The operator's own
 * footer line follows the same rule (footerLine()).
 */
final class PageRepository
{
    private const TABLE    = '`' . Schema::PAGES . '`';
    private const VERSIONS = '`' . Schema::PAGE_TRANSLATIONS . '`';

    /** Setting: the fallback order of the languages, codes separated by commas. */
    public const LANGUAGES_KEY = 'pages_languages';

    public const MIN_SLUG  = 2;
    public const MAX_SLUG  = 64;
    public const MAX_TITLE = 128;
    /** Characters of HTML -- far more than an imprint or a FAQ page needs. */
    public const MAX_BODY  = 200000;

    /** Address part: lower-case letters, digits and single hyphens, like a room's slug. */
    public const SLUG_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    /** @var array<int,string>|null the fallback order, lazily read */
    private ?array $order = null;

    public function __construct(
        private readonly Database $db,
        private readonly Settings $settings,
        private readonly Translator $translator,
    ) {
    }

    // ---- The languages ---------------------------------------------------------

    /**
     * The fallback order: every language of the menu, the ones the admins
     * ordered first, any newer one behind them in the menu's order. A
     * reader's own language always comes before all of these.
     *
     * @return array<int,string> codes
     */
    public function languageOrder(): array
    {
        if ($this->order !== null) {
            return $this->order;
        }

        $available = array_keys($this->translator->available());
        $stored    = array_filter(array_map('trim', explode(',', (string) $this->settings->get(self::LANGUAGES_KEY, ''))));
        $order     = array_values(array_intersect($stored, $available));
        foreach ($available as $code) {
            if (!in_array($code, $order, true)) {
                $order[] = $code;
            }
        }

        return $this->order = $order;
    }

    /**
     * Store a new fallback order, "de,en,fr" from drag & drop or a move
     * button. Unknown codes are ignored, languages left out keep their
     * relative order behind the given ones. False if nothing usable came in.
     *
     * @param array<int,string> $codes
     */
    public function reorderLanguages(array $codes): bool
    {
        $known   = $this->languageOrder();
        $ordered = [];
        foreach ($codes as $code) {
            $code = strtolower(trim((string) $code));
            if (in_array($code, $known, true) && !in_array($code, $ordered, true)) {
                $ordered[] = $code;
            }
        }
        if ($ordered === []) {
            return false;
        }
        foreach ($known as $code) {
            if (!in_array($code, $ordered, true)) {
                $ordered[] = $code;
            }
        }

        $this->settings->set(self::LANGUAGES_KEY, implode(',', $ordered));
        $this->order = $ordered;

        return true;
    }

    /** Move a language one step ('up', 'down') or to an end ('top', 'bottom'). */
    public function moveLanguage(string $code, string $dir): bool
    {
        $order = $this->languageOrder();
        $index = array_search(strtolower($code), $order, true);
        if ($index === false) {
            return false;
        }
        $target = match ($dir) {
            'top'    => 0,
            'bottom' => count($order) - 1,
            'down'   => $index + 1,
            default  => $index - 1,
        };
        if ($target === $index || $target < 0 || $target >= count($order)) {
            return false;
        }

        array_splice($order, $index, 1);
        array_splice($order, $target, 0, [$code]);

        return $this->reorderLanguages($order);
    }

    /**
     * Pick the version a reader gets: their language, then the fallback
     * order. Null only for a page without any version, which validate()
     * does not let through.
     *
     * @param array<string,array<string,mixed>> $versions lang => row
     * @return array<string,mixed>|null
     */
    private function pick(array $versions): ?array
    {
        foreach ([$this->translator->code(), ...$this->languageOrder()] as $code) {
            if (isset($versions[$code])) {
                return $versions[$code];
            }
        }

        return $versions === [] ? null : reset($versions);
    }

    // ---- The footer line -------------------------------------------------------

    /**
     * The operator's own footer line in every language it is written in:
     * code => cleaned HTML, for the form. The one line of earlier versions
     * (Settings::FOOTER_HTML, no language) counts as the first language of
     * the fallback order until the form saves once.
     *
     * @return array<string,string>
     */
    public function footerLines(): array
    {
        $available = $this->translator->available();
        $lines     = [];
        foreach ($this->settings->withPrefix(Settings::FOOTER_HTML_PREFIX) as $code => $html) {
            if (isset($available[$code]) && $html !== '') {
                $lines[$code] = $html;
            }
        }
        if ($lines === []) {
            $legacy = (string) $this->settings->get(Settings::FOOTER_HTML, '');
            $first  = $this->languageOrder()[0] ?? null;
            if ($legacy !== '' && $first !== null) {
                $lines[$first] = $legacy;
            }
        }

        return $lines;
    }

    /**
     * The footer line a reader gets: their language, then the fallback
     * order -- html and the code it is written in. Null without any line.
     *
     * @return array{html:string,lang:string}|null
     */
    public function footerLine(): ?array
    {
        $lines = $this->footerLines();
        foreach ([$this->translator->code(), ...$this->languageOrder()] as $code) {
            if (isset($lines[$code])) {
                return ['html' => $lines[$code], 'lang' => $code];
            }
        }

        return null;
    }

    /**
     * Store the footer line in every language of the menu: code => HTML
     * from the editor, reduced to the allowed tags here; an empty one drops
     * that language. The line of earlier versions goes with the first save.
     * Returns the number of languages that have a line now.
     *
     * @param array<string,string> $htmlByCode
     */
    public function saveFooterLines(array $htmlByCode): int
    {
        $kept = 0;
        foreach (array_keys($this->translator->available()) as $code) {
            $html = Html::clean((string) ($htmlByCode[$code] ?? ''));
            if ($html === '') {
                $this->settings->delete(Settings::FOOTER_HTML_PREFIX . $code);
            } else {
                $this->settings->set(Settings::FOOTER_HTML_PREFIX . $code, $html);
                $kept++;
            }
        }
        $this->settings->delete(Settings::FOOTER_HTML);

        return $kept;
    }

    // ---- Reading ---------------------------------------------------------------

    /**
     * Every page, by title -- or only those whose title in any language or
     * machine name contains $query. Each row carries title, body and lang of
     * the version the reader gets, plus 'languages': the codes it has.
     *
     * @return array<int,array<string,mixed>>
     */
    public function all(string $query = ''): array
    {
        $query = trim($query);
        if ($query === '') {
            return $this->sorted($this->withVersions($this->db->all('SELECT * FROM ' . self::TABLE)));
        }
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $query) . '%';

        return $this->sorted($this->withVersions($this->db->all(
            'SELECT p.* FROM ' . self::TABLE . ' p
             WHERE p.slug LIKE ? OR EXISTS (SELECT 1 FROM ' . self::VERSIONS . ' v WHERE v.page_id = p.id AND v.title LIKE ?)',
            [$like, $like],
        )));
    }

    /**
     * The pages linked in the footer, in their order.
     *
     * @return array<int,array<string,mixed>>
     */
    public function inFooter(): array
    {
        return $this->withVersions($this->db->all(
            'SELECT * FROM ' . self::TABLE . ' WHERE footer_position IS NOT NULL ORDER BY footer_position ASC, id ASC',
        ));
    }

    /**
     * The pages not linked in the footer, by title -- the ones the footer
     * page offers to add.
     *
     * @return array<int,array<string,mixed>>
     */
    public function outsideFooter(): array
    {
        return $this->sorted($this->withVersions($this->db->all(
            'SELECT * FROM ' . self::TABLE . ' WHERE footer_position IS NULL',
        )));
    }

    /**
     * The footer's links: id, slug and title, in order -- the title in the
     * reader's language, or the first of the fallback order. Runs on every
     * request, so it is one query without the bodies.
     *
     * @return array<int,array{id:int,slug:string,title:string}>
     */
    public function footerLinks(): array
    {
        $rows = $this->db->all(
            'SELECT p.id, p.slug, v.lang, v.title
             FROM ' . self::TABLE . ' p
             JOIN ' . self::VERSIONS . ' v ON v.page_id = p.id
             WHERE p.footer_position IS NOT NULL ORDER BY p.footer_position ASC, p.id ASC',
        );

        /** @var array<int,array{id:int,slug:string,versions:array<string,array<string,mixed>>}> $pages */
        $pages = [];
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $pages[$id] ??= ['id' => $id, 'slug' => (string) $row['slug'], 'versions' => []];
            $pages[$id]['versions'][(string) $row['lang']] = $row;
        }

        $links = [];
        foreach ($pages as $page) {
            $version = $this->pick($page['versions']);
            if ($version !== null) {
                $links[] = ['id' => $page['id'], 'slug' => $page['slug'], 'title' => (string) $version['title']];
            }
        }

        return $links;
    }

    /**
     * A page with the version the reader gets (title, body, lang) and the
     * codes of all its languages.
     *
     * @return array<string,mixed>|null
     */
    public function find(int $id): ?array
    {
        $page = $id > 0 ? $this->db->one('SELECT * FROM ' . self::TABLE . ' WHERE id = ?', [$id]) : null;

        return $page === null ? null : $this->withVersions([$page])[0];
    }

    /**
     * Look a page up by its address part. Anything outside SLUG_PATTERN is
     * unknown without a query.
     *
     * @return array<string,mixed>|null
     */
    public function findBySlug(string $slug): ?array
    {
        if (preg_match(self::SLUG_PATTERN, $slug) !== 1) {
            return null;
        }
        $page = $this->db->one('SELECT * FROM ' . self::TABLE . ' WHERE slug = ?', [$slug]);

        return $page === null ? null : $this->withVersions([$page])[0];
    }

    /**
     * Every version of a page, for the form: lang => title, body.
     *
     * @return array<string,array{title:string,body:string}>
     */
    public function versions(int $pageId): array
    {
        $rows = $this->db->all('SELECT lang, title, body FROM ' . self::VERSIONS . ' WHERE page_id = ?', [$pageId]);

        $byLang = [];
        foreach ($rows as $row) {
            $byLang[(string) $row['lang']] = ['title' => (string) $row['title'], 'body' => (string) $row['body']];
        }

        return $byLang;
    }

    /**
     * Give page rows the version the reader gets -- title, body, lang -- and
     * 'languages', the sorted codes of all versions. One query for all rows.
     *
     * @param array<int,array<string,mixed>> $pages
     * @return array<int,array<string,mixed>>
     */
    private function withVersions(array $pages): array
    {
        if ($pages === []) {
            return [];
        }
        $ids  = array_map(static fn (array $p): int => (int) $p['id'], $pages);
        $rows = $this->db->all(
            'SELECT * FROM ' . self::VERSIONS . ' WHERE page_id IN (' . implode(', ', array_fill(0, count($ids), '?')) . ') ORDER BY lang ASC',
            $ids,
        );

        /** @var array<int,array<string,array<string,mixed>>> $versions page id => lang => row */
        $versions = [];
        foreach ($rows as $row) {
            $versions[(int) $row['page_id']][(string) $row['lang']] = $row;
        }

        foreach ($pages as &$page) {
            $own     = $versions[(int) $page['id']] ?? [];
            $version = $this->pick($own);
            $page['title']     = (string) ($version['title'] ?? '');
            $page['body']      = (string) ($version['body'] ?? '');
            $page['lang']      = (string) ($version['lang'] ?? $this->translator->code());
            $page['languages'] = array_keys($own);
        }
        unset($page);

        return $pages;
    }

    /**
     * By the title the reader sees, then by id -- what ORDER BY title did
     * while the title was one column.
     *
     * @param array<int,array<string,mixed>> $pages
     * @return array<int,array<string,mixed>>
     */
    private function sorted(array $pages): array
    {
        usort($pages, static fn (array $a, array $b): int =>
            strcmp(mb_strtolower((string) $a['title']), mb_strtolower((string) $b['title'])) ?: (int) $a['id'] <=> (int) $b['id']);

        return $pages;
    }

    // ---- Writing ---------------------------------------------------------------

    /**
     * Check the form input: the address and, per language of the menu, title
     * and body. A language with both left empty is simply not there; one
     * with only one of them filled is an error; without a single language
     * the page cannot be saved. Bodies are cleaned here, so the values
     * returned are exactly what gets stored.
     *
     * @param array{slug?:string,title?:array<string,string>,body?:array<string,string>} $input
     * @param array<string,mixed>|null $existing  the page being edited, null for a new one
     * @return array{values:array{slug?:string,versions:array<string,array{title:string,body:string}>},errors:array<string,string>}
     */
    public function validate(array $input, ?array $existing): array
    {
        $errors = [];
        $values = ['versions' => []];

        $slug = mb_strtolower(trim((string) ($input['slug'] ?? '')));
        if ($slug === '') {
            $errors['slug'] = t('{field} is required.', ['field' => t('Machine name')]);
        } elseif (mb_strlen($slug) < self::MIN_SLUG || mb_strlen($slug) > self::MAX_SLUG) {
            $errors['slug'] = t('Machine name: {min} to {max} characters.', ['min' => self::MIN_SLUG, 'max' => self::MAX_SLUG]);
        } elseif (preg_match(self::SLUG_PATTERN, $slug) !== 1) {
            $errors['slug'] = t('Machine name: lower-case letters a–z, digits and hyphens, e.g. “imprint”.');
        } else {
            $other = $this->db->one('SELECT id FROM ' . self::TABLE . ' WHERE slug = ?', [$slug]);
            if ($other !== null && ($existing === null || (int) $other['id'] !== (int) $existing['id'])) {
                $errors['slug'] = t('This machine name is already taken.');
            } else {
                $values['slug'] = $slug;
            }
        }

        $titles = is_array($input['title'] ?? null) ? $input['title'] : [];
        $bodies = is_array($input['body'] ?? null) ? $input['body'] : [];

        foreach (array_keys($this->translator->available()) as $code) {
            $title = trim(preg_replace('/\s+/u', ' ', (string) ($titles[$code] ?? '')) ?? '');
            $raw   = (string) ($bodies[$code] ?? '');
            $body  = mb_strlen($raw) > self::MAX_BODY ? $raw : Html::clean($raw);
            // A body without a word of text counts as empty -- the editor
            // leaves "<p>&nbsp;</p>" behind.
            $empty = trim(html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5, 'UTF-8'), " \t\n\r\0\x0B\u{a0}") === '';

            if ($title === '' && $empty) {
                continue; // not in this language
            }
            if ($title === '') {
                $errors['title.' . $code] = t('{field} is required.', ['field' => t('Title')]);
            } elseif (mb_strlen($title) > self::MAX_TITLE) {
                $errors['title.' . $code] = t('{field} is too long: at most {max} characters.', ['field' => t('Title'), 'max' => self::MAX_TITLE]);
            }
            if (mb_strlen($raw) > self::MAX_BODY) {
                $errors['body.' . $code] = t('The content is too long: at most {max} characters of HTML.', ['max' => self::MAX_BODY]);
            } elseif ($empty) {
                $errors['body.' . $code] = t('{field} is required.', ['field' => t('Content')]);
            }
            if (!isset($errors['title.' . $code]) && !isset($errors['body.' . $code])) {
                $values['versions'][$code] = ['title' => $title, 'body' => $body];
            }
        }

        if ($values['versions'] === [] && !array_filter(array_keys($errors), static fn (string $k): bool => str_starts_with($k, 'title.') || str_starts_with($k, 'body.'))) {
            $errors['versions'] = t('Fill in the page in at least one language.');
        }

        return ['values' => $values, 'errors' => $errors];
    }

    /**
     * The title a message names for these values: in the interface
     * language, or the first of the fallback order.
     *
     * @param array<string,array{title:string,body:string}> $versions
     */
    public function titleOf(array $versions): string
    {
        return (string) ($this->pick($versions)['title'] ?? '');
    }

    /**
     * A new page starts outside the footer; the footer page adds it.
     *
     * @param array{slug:string,versions:array<string,array{title:string,body:string}>} $values
     */
    public function create(array $values): int
    {
        $now = date('Y-m-d H:i:s');
        $pdo = $this->db->pdo();
        $pdo->beginTransaction();
        try {
            $this->db->exec(
                'INSERT INTO ' . self::TABLE . ' (slug, footer_position, created_at, updated_at) VALUES (?, NULL, ?, ?)',
                [$values['slug'], $now, $now],
            );
            $id = (int) $pdo->lastInsertId();
            $this->writeVersions($id, $values['versions'], $now);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return $id;
    }

    /** @param array{slug:string,versions:array<string,array{title:string,body:string}>} $values */
    public function update(int $id, array $values): void
    {
        $now = date('Y-m-d H:i:s');
        $pdo = $this->db->pdo();
        $pdo->beginTransaction();
        try {
            $this->db->exec(
                'UPDATE ' . self::TABLE . ' SET slug = ?, updated_at = ? WHERE id = ? LIMIT 1',
                [$values['slug'], $now, $id],
            );
            $this->db->exec('DELETE FROM ' . self::VERSIONS . ' WHERE page_id = ?', [$id]);
            $this->writeVersions($id, $values['versions'], $now);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** @param array<string,array{title:string,body:string}> $versions */
    private function writeVersions(int $pageId, array $versions, string $now): void
    {
        foreach ($versions as $code => $version) {
            $this->db->exec(
                'INSERT INTO ' . self::VERSIONS . ' (page_id, lang, title, body, updated_at) VALUES (?, ?, ?, ?, ?)',
                [$pageId, $code, $version['title'], $version['body'], $now],
            );
        }
    }

    /**
     * Take one language off a page -- unless it is the last one, since a
     * page needs at least one. Readers in that language fall back to the
     * next of the fallback order.
     */
    public function removeLanguage(int $id, string $code): bool
    {
        $code  = strtolower($code);
        $count = (int) ($this->db->one('SELECT COUNT(*) AS n FROM ' . self::VERSIONS . ' WHERE page_id = ?', [$id])['n'] ?? 0);
        if ($count < 2) {
            return false;
        }
        $gone = $this->db->exec('DELETE FROM ' . self::VERSIONS . ' WHERE page_id = ? AND lang = ?', [$id, $code]) === 1;
        if ($gone) {
            $this->db->exec('UPDATE ' . self::TABLE . ' SET updated_at = ? WHERE id = ? LIMIT 1', [date('Y-m-d H:i:s'), $id]);
        }

        return $gone;
    }

    /** Deleting a page takes its languages with it. */
    public function delete(int $id): bool
    {
        $gone = $this->db->exec('DELETE FROM ' . self::TABLE . ' WHERE id = ? LIMIT 1', [$id]) === 1;
        if ($gone) {
            $this->db->exec('DELETE FROM ' . self::VERSIONS . ' WHERE page_id = ?', [$id]);
        }

        return $gone;
    }

    // ---- The footer -----------------------------------------------------------

    /** Link a page at the end of the footer; a page already there stays where it is. */
    public function addToFooter(int $id): bool
    {
        $page = $this->find($id);
        if ($page === null) {
            return false;
        }
        if ($page['footer_position'] !== null) {
            return true;
        }
        $last = $this->db->one('SELECT COALESCE(MAX(footer_position), 0) AS p FROM ' . self::TABLE);
        $this->db->exec(
            'UPDATE ' . self::TABLE . ' SET footer_position = ? WHERE id = ? LIMIT 1',
            [(int) ($last['p'] ?? 0) + 1, $id],
        );

        return true;
    }

    /** Take a page out of the footer; it stays reachable through its address. */
    public function removeFromFooter(int $id): bool
    {
        return $this->db->exec(
            'UPDATE ' . self::TABLE . ' SET footer_position = NULL WHERE id = ? AND footer_position IS NOT NULL LIMIT 1',
            [$id],
        ) === 1;
    }

    /**
     * Move a footer link: one step ('up', 'down') or to the very end ('top',
     * 'bottom'), like a wish on the wish list. False if the page is not in
     * the footer or already at that end.
     */
    public function moveInFooter(int $id, string $dir): bool
    {
        $ids   = array_map('intval', array_column($this->inFooter(), 'id'));
        $index = array_search($id, $ids, true);
        if ($index === false) {
            return false;
        }
        $target = match ($dir) {
            'top'    => 0,
            'bottom' => count($ids) - 1,
            'down'   => $index + 1,
            default  => $index - 1,
        };
        if ($target === $index || $target < 0 || $target >= count($ids)) {
            return false;
        }

        array_splice($ids, $index, 1);
        array_splice($ids, $target, 0, [$id]);
        $this->renumberFooter($ids);

        return true;
    }

    /**
     * The footer's new order, top to bottom, from drag & drop. Ids that are
     * not in the footer are ignored; footer pages not passed keep their
     * relative order and go to the end. Returns how many links were ordered.
     *
     * @param array<int,int|string> $orderedIds
     */
    public function reorderFooter(array $orderedIds): int
    {
        // The ids passed -- all of them or one page -- take the places those
        // same pages held; the rest keeps its place (WishRepository::placed).
        $ordered = WishRepository::placed(array_map('intval', array_column($this->inFooter(), 'id')), $orderedIds);
        if ($ordered === []) {
            return 0;
        }

        $this->renumberFooter($ordered);

        return count($ordered);
    }

    /**
     * Write the footer positions 1..n in the given order, so gaps and
     * duplicates heal themselves with every change.
     *
     * @param array<int,int> $ids
     */
    private function renumberFooter(array $ids): void
    {
        foreach (array_values($ids) as $i => $pageId) {
            $this->db->exec('UPDATE ' . self::TABLE . ' SET footer_position = ? WHERE id = ? LIMIT 1', [$i + 1, $pageId]);
        }
    }
}
