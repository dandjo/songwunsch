<?php

declare(strict_types=1);

namespace Songwunsch;

/**
 * The limits on wishing and suggesting, plus the page size of the lists,
 * set by the admins under Administration -> Limits and kept in the `settings`
 * table as `limits.<name>`. What is not set has its default. The values used
 * to live in config.php ('wish_limits', 'wish_cooldown_sec',
 * 'allow_duplicates', 'suggestion_max_open', 'suggestion_cooldown_sec',
 * 'wish_min_form_sec', 'per_page'); they apply to every room alike.
 *
 * WishGuard enforces the four per-minute / per-hour / open-wishes limits and
 * the minimum form time, index.php the session cooldowns, the duplicate rule,
 * the cap on open suggestions and the page size. Loaded lazily and once per
 * request.
 */
final class Limits
{
    public const PREFIX = 'limits.';

    /**
     * Every setting: default, smallest and largest allowed value. 0 switches
     * a limit or a cooldown off; allow_duplicates is a switch (0/1); per_page
     * has a floor so a list never shows a handful of rows.
     *
     * @var array<string,array{0:int,1:int,2:int}>
     */
    public const FIELDS = [
        'max_open'                => [200, 0, 100000], // open wishes per room
        'per_minute_total'        => [30, 0, 10000],   // wishes per minute across all visitors
        'per_minute_sender'       => [3, 0, 1000],     // wishes per minute per sender
        'per_hour_sender'         => [20, 0, 10000],   // wishes per hour per sender
        'wish_cooldown_sec'       => [5, 0, 3600],     // gap between two wishes in one session
        'allow_duplicates'        => [0, 0, 1],        // may an open song be wished again?
        'suggestion_max_open'     => [200, 0, 100000], // open suggestions, site-wide
        'suggestion_cooldown_sec' => [10, 0, 3600],    // gap between two suggestions in one session
        'wish_min_form_sec'       => [2, 0, 60],       // seconds between page load and submit below which a form counts as a script's
        'per_page'                => [50, 10, 500],    // rows per page on the paged lists
    ];

    /** The fields WishGuard takes -- the former config.php 'wish_limits'. */
    private const GUARD = ['max_open', 'per_minute_total', 'per_minute_sender', 'per_hour_sender'];

    /** @var array<string,int>|null */
    private ?array $values = null;

    public function __construct(private readonly Settings $settings)
    {
    }

    /**
     * Every limit with its current value, defaults filled in.
     *
     * @return array<string,int>
     */
    public function all(): array
    {
        if ($this->values === null) {
            $stored       = $this->settings->withPrefix(self::PREFIX);
            $this->values = [];
            foreach (self::FIELDS as $name => [$default, $min, $max]) {
                $raw                  = $stored[$name] ?? null;
                $this->values[$name] = $raw !== null && preg_match('/^\d{1,9}$/', $raw) === 1
                    ? max($min, min($max, (int) $raw))
                    : $default;
            }
        }

        return $this->values;
    }

    public function get(string $name): int
    {
        return $this->all()[$name] ?? 0;
    }

    public function allowDuplicates(): bool
    {
        return $this->get('allow_duplicates') === 1;
    }

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
     * Check the form's input. Every number must be a whole number within its
     * range; the duplicate switch is a checkbox and cannot be wrong.
     *
     * @param array<string,string> $input  field => what was typed
     * @return array{values: array<string,int>, errors: array<string,string>}
     */
    public function validate(array $input): array
    {
        $values = [];
        $errors = [];

        foreach (self::FIELDS as $name => [$default, $min, $max]) {
            if ($name === 'allow_duplicates') {
                $values[$name] = ($input[$name] ?? '') === '1' ? 1 : 0;
                continue;
            }
            $raw = trim((string) ($input[$name] ?? ''));
            if (preg_match('/^\d{1,9}$/', $raw) !== 1) {
                $errors[$name] = t('Please enter a whole number.');
                continue;
            }
            $n = (int) $raw;
            if ($n < $min || $n > $max) {
                $errors[$name] = t('Please enter a number between {min} and {max}.', ['min' => $min, 'max' => $max]);
                continue;
            }
            $values[$name] = $n;
        }

        return ['values' => $values, 'errors' => $errors];
    }

    /**
     * Store validated values. A value at its default is stored as well --
     * so a later change of the built-in default does not silently change a
     * site whose admins looked at the page and left the number as it was.
     *
     * @param array<string,int> $values  from validate()
     */
    public function save(array $values): void
    {
        foreach (self::FIELDS as $name => $spec) {
            if (isset($values[$name])) {
                $this->settings->set(self::PREFIX . $name, (string) $values[$name]);
            }
        }
        $this->values = null;
    }
}
