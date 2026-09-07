<?php

declare(strict_types=1);

namespace Songwunsch;

/**
 * One page cut out of a list.
 *
 * The number of pages follows from the total, and a page beyond the last --
 * the last entry of a page was just moved or deleted, or the address is old
 * -- falls back to the last page. So nobody stands on an empty list under a
 * pager that says "Page 4 of 3", which on a live-updating list is a matter
 * of seconds rather than of bad luck.
 */
final class Pagination
{
    /**
     * For a list a repository fetches page by page.
     *
     * @param callable(int): array{rows: array<int,mixed>, total: int} $fetch
     * @return array{rows: array<int,mixed>, total: int, page: int, pages: int}
     */
    public static function of(callable $fetch, int $pageNo, int $perPage): array
    {
        $pageNo  = max(1, $pageNo);
        $perPage = max(1, $perPage);
        $result  = $fetch($pageNo);
        $pages   = max(1, (int) ceil($result['total'] / $perPage));

        if ($pageNo > $pages) {
            $pageNo = $pages;
            $result = $fetch($pageNo);
        }

        return ['rows' => $result['rows'], 'total' => $result['total'], 'page' => $pageNo, 'pages' => $pages];
    }

    /**
     * The same for a list that is complete and sorted in PHP already -- the
     * pages, whose order depends on the reader's language.
     *
     * @param array<int,mixed> $rows
     * @return array{rows: array<int,mixed>, total: int, page: int, pages: int}
     */
    public static function slice(array $rows, int $pageNo, int $perPage): array
    {
        $perPage = max(1, $perPage);
        $total   = count($rows);

        return self::of(
            static fn (int $page): array => ['rows' => array_slice($rows, ($page - 1) * $perPage, $perPage), 'total' => $total],
            $pageNo,
            $perPage,
        );
    }
}
