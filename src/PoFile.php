<?php

declare(strict_types=1);

namespace Songwunsch;

use RuntimeException;

/**
 * Minimal reader for GNU gettext .po files -- no gettext extension, no .mo
 * compilation, no system locales required.
 *
 * Supported: header entries, msgctxt, msgid, msgid_plural, msgstr and
 * msgstr[n], multi-line strings, C escapes, "#, fuzzy" entries (skipped),
 * and the Plural-Forms header with its C-style plural expression.
 */
final class PoFile
{
    /** @var array<string,string> */
    public readonly array $headers;

    /**
     * Key is msgid, or "context\x04msgid" for entries with msgctxt.
     * Value is the msgstr, or the list of plural forms for plural entries.
     *
     * @var array<string,string|array<int,string>>
     */
    public readonly array $messages;

    public readonly int $nplurals;

    /** @var callable(int):int index of the plural form for a count */
    private $pluralRule;

    /** @param array<string,string|array<int,string>> $messages */
    private function __construct(array $headers, array $messages)
    {
        $this->headers  = $headers;
        $this->messages = $messages;

        [$this->nplurals, $this->pluralRule] = self::compilePluralForms(
            $headers['Plural-Forms'] ?? 'nplurals=2; plural=(n != 1);',
        );
    }

    public static function load(string $path): self
    {
        $raw = @file_get_contents($path);
        if ($raw === false) {
            throw new RuntimeException('Cannot read translation file: ' . basename($path));
        }

        return self::parse($raw);
    }

    public static function parse(string $raw): self
    {
        $messages = [];
        $headers  = [];

        // State of the entry being assembled.
        $ctxt   = null;
        $id     = null;
        $plural = null;
        $strs   = [];
        $fuzzy  = false;
        $target = null; // which field the next continuation line appends to

        $flush = static function () use (&$messages, &$headers, &$ctxt, &$id, &$plural, &$strs, &$fuzzy, &$target): void {
            if ($id !== null && !$fuzzy) {
                if ($id === '' && $ctxt === null) {
                    // Header block: "Key: value\n" pairs in msgstr.
                    foreach (explode("\n", $strs[0] ?? '') as $line) {
                        if (str_contains($line, ':')) {
                            [$k, $v] = explode(':', $line, 2);
                            $headers[trim($k)] = trim($v);
                        }
                    }
                } else {
                    $key = $ctxt !== null ? $ctxt . "\x04" . $id : $id;
                    if ($plural !== null) {
                        ksort($strs);
                        $messages[$key] = array_values($strs);
                    } elseif (($strs[0] ?? '') !== '') {
                        $messages[$key] = $strs[0];
                    }
                }
            }
            $ctxt = $id = $plural = null;
            $strs = [];
            $fuzzy = false;
            $target = null;
        };

        foreach (preg_split('/\r\n|\r|\n/', $raw) ?: [] as $line) {
            $line = trim($line);

            if ($line === '') {
                $flush();
                continue;
            }

            if ($line[0] === '#') {
                if (str_starts_with($line, '#,') && str_contains($line, 'fuzzy')) {
                    $fuzzy = true;
                }
                continue;
            }

            if (preg_match('/^(msgctxt|msgid_plural|msgid|msgstr(?:\[(\d+)\])?)\s+"(.*)"$/', $line, $m) === 1) {
                $value = self::unescape($m[3]);
                switch ($m[1]) {
                    case 'msgctxt':
                        if ($id !== null) {
                            $flush();
                        }
                        $ctxt   = $value;
                        $target = 'ctxt';
                        break;
                    case 'msgid':
                        if ($id !== null) {
                            $flush();
                        }
                        $id     = $value;
                        $target = 'id';
                        break;
                    case 'msgid_plural':
                        $plural = $value;
                        $target = 'plural';
                        break;
                    default:
                        $index         = $m[2] !== '' ? (int) $m[2] : 0;
                        $strs[$index]  = $value;
                        $target        = $index;
                }
                continue;
            }

            // Continuation line: "..." appended to the previous field.
            if ($line[0] === '"' && preg_match('/^"(.*)"$/', $line, $m) === 1 && $target !== null) {
                $value = self::unescape($m[1]);
                match (true) {
                    $target === 'ctxt'   => $ctxt .= $value,
                    $target === 'id'     => $id .= $value,
                    $target === 'plural' => $plural .= $value,
                    default              => $strs[$target] = ($strs[$target] ?? '') . $value,
                };
            }
        }
        $flush();

        return new self($headers, $messages);
    }

    /** Plural form index for a count, following the Plural-Forms header. */
    public function pluralIndex(int $n): int
    {
        $index = ($this->pluralRule)($n);

        return max(0, min($this->nplurals - 1, $index));
    }

    private static function unescape(string $value): string
    {
        return strtr($value, ['\\n' => "\n", '\\t' => "\t", '\\"' => '"', '\\\\' => '\\']);
    }

    /**
     * Turn "nplurals=2; plural=(n != 1);" into a count and a rule callable.
     * The expression is evaluated by a small recursive-descent interpreter
     * for the C subset gettext uses -- nothing is passed to eval().
     *
     * @return array{0:int,1:callable(int):int}
     */
    private static function compilePluralForms(string $header): array
    {
        $nplurals = 2;
        $expr     = 'n != 1';

        if (preg_match('/nplurals\s*=\s*(\d+)/', $header, $m) === 1) {
            $nplurals = max(1, (int) $m[1]);
        }
        if (preg_match('/plural\s*=\s*([^;]+)/', $header, $m) === 1) {
            $expr = trim($m[1]);
        }

        $tokens = self::tokenize($expr);

        return [$nplurals, static function (int $n) use ($tokens): int {
            $pos = 0;

            return (int) self::evalTernary($tokens, $pos, $n);
        }];
    }

    /** @return array<int,string> */
    private static function tokenize(string $expr): array
    {
        preg_match_all('/\d+|n|&&|\|\||==|!=|<=|>=|[<>?:%()!+\-*\/]/', $expr, $m);

        return $m[0];
    }

    // ---- Expression interpreter: precedence climbing, C semantics --------

    /** @param array<int,string> $t */
    private static function evalTernary(array $t, int &$p, int $n): int
    {
        $cond = self::evalOr($t, $p, $n);
        if (($t[$p] ?? null) === '?') {
            $p++;
            $a = self::evalTernary($t, $p, $n);
            if (($t[$p] ?? null) === ':') {
                $p++;
            }
            $b = self::evalTernary($t, $p, $n);

            return $cond !== 0 ? $a : $b;
        }

        return $cond;
    }

    /** @param array<int,string> $t */
    private static function evalOr(array $t, int &$p, int $n): int
    {
        $v = self::evalAnd($t, $p, $n);
        while (($t[$p] ?? null) === '||') {
            $p++;
            $r = self::evalAnd($t, $p, $n);
            $v = ($v !== 0 || $r !== 0) ? 1 : 0;
        }

        return $v;
    }

    /** @param array<int,string> $t */
    private static function evalAnd(array $t, int &$p, int $n): int
    {
        $v = self::evalCompare($t, $p, $n);
        while (($t[$p] ?? null) === '&&') {
            $p++;
            $r = self::evalCompare($t, $p, $n);
            $v = ($v !== 0 && $r !== 0) ? 1 : 0;
        }

        return $v;
    }

    /** @param array<int,string> $t */
    private static function evalCompare(array $t, int &$p, int $n): int
    {
        $v = self::evalArith($t, $p, $n);
        while (in_array($t[$p] ?? null, ['==', '!=', '<', '>', '<=', '>='], true)) {
            $op = $t[$p++];
            $r  = self::evalArith($t, $p, $n);
            $v  = (int) match ($op) {
                '==' => $v === $r,
                '!=' => $v !== $r,
                '<'  => $v < $r,
                '>'  => $v > $r,
                '<=' => $v <= $r,
                '>=' => $v >= $r,
            };
        }

        return $v;
    }

    /** @param array<int,string> $t */
    private static function evalArith(array $t, int &$p, int $n): int
    {
        $v = self::evalTerm($t, $p, $n);
        while (in_array($t[$p] ?? null, ['+', '-'], true)) {
            $op = $t[$p++];
            $r  = self::evalTerm($t, $p, $n);
            $v  = $op === '+' ? $v + $r : $v - $r;
        }

        return $v;
    }

    /** @param array<int,string> $t */
    private static function evalTerm(array $t, int &$p, int $n): int
    {
        $v = self::evalUnary($t, $p, $n);
        while (in_array($t[$p] ?? null, ['*', '/', '%'], true)) {
            $op = $t[$p++];
            $r  = self::evalUnary($t, $p, $n);
            $v  = match ($op) {
                '*' => $v * $r,
                '/' => $r === 0 ? 0 : intdiv($v, $r),
                '%' => $r === 0 ? 0 : $v % $r,
            };
        }

        return $v;
    }

    /** @param array<int,string> $t */
    private static function evalUnary(array $t, int &$p, int $n): int
    {
        $tok = $t[$p] ?? null;

        if ($tok === '!') {
            $p++;

            return self::evalUnary($t, $p, $n) === 0 ? 1 : 0;
        }
        if ($tok === '(') {
            $p++;
            $v = self::evalTernary($t, $p, $n);
            if (($t[$p] ?? null) === ')') {
                $p++;
            }

            return $v;
        }
        if ($tok === 'n') {
            $p++;

            return $n;
        }
        if ($tok !== null && ctype_digit($tok)) {
            $p++;

            return (int) $tok;
        }

        // Malformed expression: fall back to "one form".
        return 0;
    }
}
