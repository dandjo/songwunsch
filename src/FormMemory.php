<?php

declare(strict_types=1);

namespace Songwunsch;

/**
 * What the visitor typed and what was wrong with it, carried across the
 * redirect of post/redirect/get.
 *
 * A save that fails validation answers with a redirect back to the form, and
 * the form is then built anew: without this the fields would come back empty
 * and the errors would be gone. Passwords are never put in here -- only
 * which field they failed on.
 */
final class FormMemory
{
    private const KEY = 'input';

    /**
     * @param array<string,mixed>  $values
     * @param array<string,string> $errors
     */
    public function remember(array $values, array $errors): void
    {
        $_SESSION[self::KEY] = ['values' => $values, 'errors' => $errors];
    }

    /**
     * Read and remove: the next build of the form gets it, the one after
     * that shows the stored record again.
     *
     * @return array{values:array<string,mixed>,errors:array<string,string>}|null
     */
    public function take(): ?array
    {
        $kept = $_SESSION[self::KEY] ?? null;
        unset($_SESSION[self::KEY]);

        if (!is_array($kept)) {
            return null;
        }

        return [
            'values' => is_array($kept['values'] ?? null) ? $kept['values'] : [],
            'errors' => is_array($kept['errors'] ?? null) ? array_map('strval', $kept['errors']) : [],
        ];
    }
}
