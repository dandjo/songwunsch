<?php

declare(strict_types=1);

namespace Songwunsch;

/**
 * The limits on wishing and suggesting, plus the page size of the lists,
 * set by the admins under Administration -> Limits and kept in the `settings`
 * table as `limits.<name>`. What is not set has its default. The values used
 * to live in config.php ('wish_limits', 'wish_cooldown_sec',
 * 'suggestion_max_open', 'suggestion_cooldown_sec', 'wish_min_form_sec',
 * 'per_page'); they apply to every room alike.
 *
 * WishGuard enforces the four per-minute / per-hour / open-wishes limits and
 * the minimum form time, index.php the session cooldowns, the cap on open
 * suggestions and the page size. How long a message stays and how often the
 * lists poll for changes is the Ui class (Administration -> Interface).
 */
final class Limits extends NumberSettings
{
    public const PREFIX = 'limits.';

    /**
     * Every setting: default, smallest and largest allowed value. 0 switches
     * a limit or a cooldown off; per_page has a floor so a list never shows
     * a handful of rows.
     *
     * @var array<string,array{0:int,1:int,2:int}>
     */
    public const FIELDS = [
        'max_open'                => [200, 0, 100000], // open wishes per room
        'per_minute_total'        => [30, 0, 10000],   // wishes per minute across all visitors
        'per_minute_sender'       => [3, 0, 1000],     // wishes per minute per sender
        'per_hour_sender'         => [20, 0, 10000],   // wishes per hour per sender
        'wish_cooldown_sec'       => [5, 0, 3600],     // gap between two wishes in one session
        'suggestion_max_open'     => [200, 0, 100000], // open suggestions, site-wide
        'suggestion_cooldown_sec' => [10, 0, 3600],    // gap between two suggestions in one session
        'wish_min_form_sec'       => [2, 0, 60],       // seconds between page load and submit below which a form counts as a script's
        'per_page'                => [50, 10, 500],    // rows per page on the paged lists
    ];

    /** The fields WishGuard takes -- the former config.php 'wish_limits'. */
    private const GUARD = ['max_open', 'per_minute_total', 'per_minute_sender', 'per_hour_sender'];

    /**
     * The limits the wish guard enforces.
     *
     * @return array<string,int>
     */
    public function wish(): array
    {
        return array_intersect_key($this->all(), array_flip(self::GUARD));
    }

    /**
     * @param array<string,int> $values  from validate()
     */
    public function save(array $values): void
    {
        parent::save($values);
        // A song may always be wished again now (the wish counts how often);
        // the switch that used to decide that is gone.
        $this->settings->delete(self::PREFIX . 'allow_duplicates');
    }
}
