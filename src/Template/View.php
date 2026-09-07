<?php

declare(strict_types=1);

namespace Songwunsch\Template;

/**
 * What a controller hands back when it has a page to show: which template,
 * what it is called, and the values it needs.
 *
 * The values are the template's own -- the rows of a list, a form's fields
 * and errors. Everything the page shell shows around them (the header, the
 * tab counters, the room switcher, the footer) is added by ShellContext, so
 * a controller neither knows nor repeats any of it. A value with the same
 * name as a shell value wins over it: that is how the wish list reports the
 * count it has just read instead of making the shell ask again.
 */
final class View
{
    /** @param array<string,mixed> $vars */
    public function __construct(
        public readonly string $template,
        public readonly string $title,
        public readonly array $vars = [],
        public readonly bool $editor = false,
    ) {
    }
}
