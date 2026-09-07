<?php

declare(strict_types=1);

namespace Songwunsch\Template;

use Songwunsch\Http\Response;

/**
 * Renders a template into a response.
 *
 * The one thing worth knowing about this class is capture(): a template is
 * included by a method whose only local variables are named __file and
 * __vars. The front controller used to extract() its view array in its own
 * scope, where a controller local with the same name as a view value quietly
 * won over it -- a trap that had bitten the footer and the room-songs page
 * more than once. There is no scope here to collide with any more.
 */
final class Renderer
{
    public function __construct(
        private readonly string $directory,
        private readonly ShellContext $shell,
    ) {
    }

    public function render(View $view, int $status = 200): Response
    {
        return Response::html(
            $this->capture($this->directory . '/layout.php', $this->shell->build($view)),
            $status,
        );
    }

    /**
     * A page without layout, database or session: the 404, and the
     * emergency exit for a request that fails before there is a
     * configuration to render one with.
     */
    public function bare(int $status, string $title, string $html, string $styleUrl): Response
    {
        $e = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

        return Response::html(
            '<!doctype html><html lang="en"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . $e($title) . '</title>'
            . '<link rel="stylesheet" href="' . $e($styleUrl) . '"></head>'
            . '<body class="is-fatal"><main class="fatal"><h1>' . $e($title) . '</h1>'
            . '<p>' . $html . '</p></main></body></html>',
            $status,
        );
    }

    /**
     * @param array<string,mixed> $__vars
     * @see the class comment on why the parameters are named like this
     */
    private function capture(string $__file, array $__vars): string
    {
        extract($__vars, EXTR_SKIP);

        ob_start();
        require $__file;

        return (string) ob_get_clean();
    }
}
