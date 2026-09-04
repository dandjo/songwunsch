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
 */
final class PageRepository
{
    private const TABLE = '`' . Schema::PAGES . '`';

    public const MIN_SLUG  = 2;
    public const MAX_SLUG  = 64;
    public const MAX_TITLE = 128;
    /** Characters of HTML -- far more than an imprint or a FAQ page needs. */
    public const MAX_BODY  = 200000;

    /** Address part: lower-case letters, digits and single hyphens, like a room's slug. */
    public const SLUG_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    /** Words the address /pages/<...> uses for other things: /pages/new. */
    public const RESERVED_SLUGS = ['new'];

    public function __construct(private readonly Database $db)
    {
    }

    /**
     * Every page, by title.
     *
     * @return array<int,array<string,mixed>>
     */
    public function all(): array
    {
        return $this->db->all('SELECT * FROM ' . self::TABLE . ' ORDER BY title ASC, id ASC');
    }

    /**
     * The pages linked in the footer, in their order.
     *
     * @return array<int,array<string,mixed>>
     */
    public function inFooter(): array
    {
        return $this->db->all(
            'SELECT * FROM ' . self::TABLE . ' WHERE footer_position IS NOT NULL ORDER BY footer_position ASC, id ASC',
        );
    }

    /**
     * The pages not linked in the footer, by title -- the ones the footer
     * page offers to add.
     *
     * @return array<int,array<string,mixed>>
     */
    public function outsideFooter(): array
    {
        return $this->db->all(
            'SELECT * FROM ' . self::TABLE . ' WHERE footer_position IS NULL ORDER BY title ASC, id ASC',
        );
    }

    /**
     * The footer's links: id, slug and title, in order. Runs on every request,
     * so it fetches nothing but these three columns.
     *
     * @return array<int,array{id:int,slug:string,title:string}>
     */
    public function footerLinks(): array
    {
        $rows = $this->db->all(
            'SELECT id, slug, title FROM ' . self::TABLE . ' WHERE footer_position IS NOT NULL ORDER BY footer_position ASC, id ASC',
        );

        return array_map(static fn (array $row): array => [
            'id'    => (int) $row['id'],
            'slug'  => (string) $row['slug'],
            'title' => (string) $row['title'],
        ], $rows);
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        return $id > 0 ? $this->db->one('SELECT * FROM ' . self::TABLE . ' WHERE id = ?', [$id]) : null;
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

        return $this->db->one('SELECT * FROM ' . self::TABLE . ' WHERE slug = ?', [$slug]);
    }

    /**
     * Check the form input. The body is cleaned here as well, so the values
     * returned are exactly what gets stored.
     *
     * @param array<string,string> $input     slug, title, body
     * @param array<string,mixed>|null $existing  the page being edited, null for a new one
     * @return array{values:array<string,mixed>,errors:array<string,string>}
     */
    public function validate(array $input, ?array $existing): array
    {
        $errors = [];
        $values = [];

        $slug = mb_strtolower(trim((string) ($input['slug'] ?? '')));
        if ($slug === '') {
            $errors['slug'] = t('{field} is required.', ['field' => t('Machine name')]);
        } elseif (mb_strlen($slug) < self::MIN_SLUG || mb_strlen($slug) > self::MAX_SLUG) {
            $errors['slug'] = t('Machine name: {min} to {max} characters.', ['min' => self::MIN_SLUG, 'max' => self::MAX_SLUG]);
        } elseif (preg_match(self::SLUG_PATTERN, $slug) !== 1) {
            $errors['slug'] = t('Machine name: lower-case letters a–z, digits and hyphens, e.g. “imprint”.');
        } elseif (in_array($slug, self::RESERVED_SLUGS, true)) {
            $errors['slug'] = t('This machine name is reserved.');
        } else {
            $other = $this->findBySlug($slug);
            if ($other !== null && ($existing === null || (int) $other['id'] !== (int) $existing['id'])) {
                $errors['slug'] = t('This machine name is already taken.');
            } else {
                $values['slug'] = $slug;
            }
        }

        $title = trim(preg_replace('/\s+/u', ' ', (string) ($input['title'] ?? '')) ?? '');
        if ($title === '') {
            $errors['title'] = t('{field} is required.', ['field' => t('Title')]);
        } elseif (mb_strlen($title) > self::MAX_TITLE) {
            $errors['title'] = t('{field} is too long: at most {max} characters.', ['field' => t('Title'), 'max' => self::MAX_TITLE]);
        } else {
            $values['title'] = $title;
        }

        $raw = (string) ($input['body'] ?? '');
        if (mb_strlen($raw) > self::MAX_BODY) {
            $errors['body'] = t('The content is too long: at most {max} characters of HTML.', ['max' => self::MAX_BODY]);
        } else {
            $body = Html::clean($raw);
            // A page without a word of text is a dead link in the footer.
            if (trim(html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5, 'UTF-8'), " \t\n\r\0\x0B\u{a0}") === '') {
                $errors['body'] = t('{field} is required.', ['field' => t('Content')]);
            } else {
                $values['body'] = $body;
            }
        }

        return ['values' => $values, 'errors' => $errors];
    }

    /**
     * A new page starts outside the footer; the footer page adds it.
     *
     * @param array<string,mixed> $values slug, title, body
     */
    public function create(array $values): int
    {
        $now = date('Y-m-d H:i:s');
        $this->db->exec(
            'INSERT INTO ' . self::TABLE . ' (slug, title, body, footer_position, created_at, updated_at) VALUES (?, ?, ?, NULL, ?, ?)',
            [$values['slug'], $values['title'], $values['body'], $now, $now],
        );

        return (int) $this->db->pdo()->lastInsertId();
    }

    /** @param array<string,mixed> $values slug, title, body */
    public function update(int $id, array $values): void
    {
        $this->db->exec(
            'UPDATE ' . self::TABLE . ' SET slug = ?, title = ?, body = ?, updated_at = ? WHERE id = ? LIMIT 1',
            [$values['slug'], $values['title'], $values['body'], date('Y-m-d H:i:s'), $id],
        );
    }

    public function delete(int $id): bool
    {
        return $this->db->exec('DELETE FROM ' . self::TABLE . ' WHERE id = ? LIMIT 1', [$id]) === 1;
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
        $known = array_map('intval', array_column($this->inFooter(), 'id'));

        $ordered = [];
        foreach ($orderedIds as $id) {
            $id = (int) $id;
            if (in_array($id, $known, true) && !in_array($id, $ordered, true)) {
                $ordered[] = $id;
            }
        }
        if ($ordered === []) {
            return 0;
        }
        foreach ($known as $id) {
            if (!in_array($id, $ordered, true)) {
                $ordered[] = $id;
            }
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
