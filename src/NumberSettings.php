<?php

declare(strict_types=1);

namespace Songwunsch;

/**
 * A group of whole-number settings the admins set on one page, kept in the
 * `settings` table below one prefix (`limits.<name>`, `ui.<name>`). Every
 * field has a default and a range; what is not set, or not a number within
 * its range, has its default. Loaded lazily and once per request.
 *
 * The subclasses name the prefix and the fields: Limits (the brakes on
 * wishing and suggesting, the page size) and Ui (messages, live updates).
 */
abstract class NumberSettings
{
    /** The prefix of the keys in the `settings` table, with the trailing dot. */
    public const PREFIX = '';

    /**
     * Every setting: default, smallest and largest allowed value.
     *
     * @var array<string,array{0:int,1:int,2:int}>
     */
    public const FIELDS = [];

    /** @var array<string,int>|null */
    private ?array $values = null;

    public function __construct(protected readonly Settings $settings)
    {
    }

    /**
     * Every field with its current value, defaults filled in.
     *
     * @return array<string,int>
     */
    public function all(): array
    {
        if ($this->values === null) {
            $stored       = $this->settings->withPrefix(static::PREFIX) + $this->legacy();
            $this->values = [];
            foreach (static::FIELDS as $name => [$default, $min, $max]) {
                $raw                 = $stored[$name] ?? null;
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

    /**
     * Values from the keys a field lived under before it moved here, name =>
     * raw value; read only while the field has no entry under the prefix.
     * Nothing by default.
     *
     * @return array<string,string>
     */
    protected function legacy(): array
    {
        return [];
    }

    /**
     * Check the form's input. Every number must be a whole number within its
     * range.
     *
     * @param array<string,string> $input  field => what was typed
     * @return array{values: array<string,int>, errors: array<string,string>}
     */
    public function validate(array $input): array
    {
        $values = [];
        $errors = [];

        foreach (static::FIELDS as $name => [$default, $min, $max]) {
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
        foreach (static::FIELDS as $name => $spec) {
            if (isset($values[$name])) {
                $this->settings->set(static::PREFIX . $name, (string) $values[$name]);
            }
        }
        $this->values = null;
    }
}
