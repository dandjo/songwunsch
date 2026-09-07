<?php

declare(strict_types=1);

namespace Songwunsch\Http;

/**
 * The answer to a request, built up and handed back instead of written out.
 *
 * The application used to end a request wherever it was done -- redirect(),
 * send_json() and the image routes all called exit. Nothing could wrap such
 * an answer, and a controller could not be asked what it would do without
 * doing it. A response object costs one return statement and buys both.
 *
 * send() is called in exactly one place: index.php.
 */
final class Response
{
    /**
     * Headers PHP has already queued that must not go out. session_start()
     * adds "Expires" in the past and "Pragma: no-cache" so that pages are
     * never cached -- right for a page, wrong for an uploaded logo, which
     * browsers would then fetch again on every screen. A response says
     * which of those to drop; the alternative would be a header_remove()
     * scattered through the controllers.
     *
     * @var list<string>
     */
    private array $removeHeaders = [];

    /** @param array<string,string> $headers */
    public function __construct(
        private readonly string $body = '',
        private readonly int $status = 200,
        private array $headers = [],
    ) {
    }

    /** @param array<string,string> $headers */
    public static function html(string $body, int $status = 200, array $headers = []): self
    {
        return new self($body, $status, $headers + ['Content-Type' => 'text/html; charset=utf-8']);
    }

    /**
     * A JSON answer for the browser code: the live-update poll and the
     * verdict on a drag & drop.
     *
     * @param array<string,mixed> $payload
     */
    public static function json(array $payload, int $status = 200): self
    {
        return new self(
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            $status,
            ['Content-Type' => 'application/json; charset=utf-8'],
        );
    }

    /**
     * Post/redirect/get: 303 is the answer that turns a POST into a GET, so
     * a reload does not repeat the action.
     */
    public static function redirect(string $target, int $status = 303): self
    {
        return new self('', $status, ['Location' => $target]);
    }

    /**
     * Bytes from the database that never change for a given address -- an
     * uploaded logo, a QR image. They may be cached for good, must not be
     * sniffed for a content type other than their own, and an SVG opened
     * directly may draw and nothing else.
     *
     * @param array<string,string> $headers
     */
    public static function file(string $bytes, string $mime, array $headers = []): self
    {
        return (new self($bytes, 200, $headers + [
            'Content-Type'            => $mime,
            'Content-Length'          => (string) strlen($bytes),
            'X-Content-Type-Options'  => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'",
        ]))->withoutHeaders('Expires', 'Pragma', 'Set-Cookie');
    }

    /** An answer with a status and no body: 304 for a cache that is still good. */
    public static function empty(int $status): self
    {
        return new self('', $status);
    }

    public function withHeader(string $name, string $value): self
    {
        $clone = clone $this;
        $clone->headers[$name] = $value;

        return $clone;
    }

    /** Drop headers PHP queued before the answer was known; see $removeHeaders. */
    public function withoutHeaders(string ...$names): self
    {
        $clone = clone $this;
        $clone->removeHeaders = array_values(array_unique([...$clone->removeHeaders, ...$names]));

        return $clone;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        return $this->body;
    }

    /** @return array<string,string> */
    public function headers(): array
    {
        return $this->headers;
    }

    public function send(): void
    {
        foreach ($this->removeHeaders as $name) {
            header_remove($name);
        }

        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value, true);
        }

        // A 304 must carry no body, and a HEAD request wants none either.
        if ($this->status !== 304 && $this->body !== '') {
            echo $this->body;
        }
    }
}
