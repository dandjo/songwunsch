<?php

declare(strict_types=1);

namespace Songwunsch;

use InvalidArgumentException;

/**
 * QR codes for a room's address, made here rather than by a third-party
 * service -- the address stays on this server (GDPR, no CDNs), and the
 * project carries no dependencies. Byte mode, error correction level M,
 * versions 1 to 10 (up to 213 bytes, plenty for an address), the mask with
 * the lowest penalty score as ISO/IEC 18004 asks. Output as SVG (crisp at
 * any size) or, with the gd extension, as PNG for table cards and posters.
 *
 * The structure follows the standard's chapters: encode the text into
 * codewords, add Reed-Solomon error correction per block and interleave,
 * draw the function patterns, place the codewords, choose a mask, write the
 * format (and, from version 7, the version) information.
 */
final class QrCode
{
    /** Error correction codewords per block, level M, versions 1..10. */
    private const ECC_PER_BLOCK = [10, 16, 26, 18, 24, 16, 18, 22, 22, 26];

    /** Number of error correction blocks, level M, versions 1..10. */
    private const BLOCKS = [1, 1, 1, 2, 2, 4, 4, 4, 5, 5];

    private const MAX_VERSION = 10;

    /** Format information bits of level M (the standard's "00"). */
    private const ECL_BITS = 0;

    /** Light modules around the symbol, in modules -- the standard asks for four. */
    public const QUIET_ZONE = 4;

    /**
     * The symbol as rows of modules, true = dark, without the quiet zone.
     *
     * @return array<int,array<int,bool>>
     * @throws InvalidArgumentException when the text does not fit version 10
     */
    public static function matrix(string $text): array
    {
        $bytes   = array_values(unpack('C*', $text) ?: []);
        $version = self::version(count($bytes));
        $size    = $version * 4 + 17;

        $codewords = self::interleave(self::codewords($bytes, $version), $version);

        $modules  = array_fill(0, $size, array_fill(0, $size, false));
        $function = array_fill(0, $size, array_fill(0, $size, false));
        self::drawFunctionPatterns($modules, $function, $version);
        self::drawCodewords($modules, $function, $codewords);

        // Every mask is tried on the finished symbol; the one with the
        // lowest penalty wins and its number goes into the format bits.
        $best    = 0;
        $minimum = PHP_INT_MAX;
        for ($mask = 0; $mask < 8; $mask++) {
            self::applyMask($modules, $function, $mask);
            self::drawFormatBits($modules, $function, $mask);
            $penalty = self::penalty($modules);
            if ($penalty < $minimum) {
                $minimum = $penalty;
                $best    = $mask;
            }
            self::applyMask($modules, $function, $mask); // masks are their own inverse
        }
        self::applyMask($modules, $function, $best);
        self::drawFormatBits($modules, $function, $best);

        return $modules;
    }

    /**
     * The symbol as an SVG document: one path for the dark modules on a
     * white ground, quiet zone included, sized by the viewBox alone so it
     * scales to whatever the page or the printer asks.
     */
    public static function svg(string $text): string
    {
        $modules = self::matrix($text);
        $size    = count($modules);
        $total   = $size + 2 * self::QUIET_ZONE;

        $path = '';
        foreach ($modules as $y => $row) {
            $run = 0;
            foreach ($row as $x => $dark) {
                if ($dark) {
                    $run++;
                    $last = $x === $size - 1;
                } else {
                    $last = false;
                }
                // Runs of dark modules become one rectangle each.
                if ($run > 0 && (!$dark || $last)) {
                    $end  = $dark ? $x : $x - 1;
                    $from = $end - $run + 1;
                    $path .= 'M' . ($from + self::QUIET_ZONE) . ' ' . ($y + self::QUIET_ZONE) . 'h' . $run . 'v1h-' . $run . 'z';
                    $run  = 0;
                }
            }
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $total . ' ' . $total . '" shape-rendering="crispEdges" role="img">'
            . '<rect width="' . $total . '" height="' . $total . '" fill="#fff"/>'
            . '<path d="' . $path . '" fill="#000"/>'
            . '</svg>';
    }

    /**
     * The symbol as a PNG, $scale pixels per module, quiet zone included --
     * needs the gd extension; null without it.
     */
    public static function png(string $text, int $scale = 12): ?string
    {
        if (!function_exists('imagecreate')) {
            return null;
        }
        $modules = self::matrix($text);
        $size    = count($modules);
        $total   = ($size + 2 * self::QUIET_ZONE) * $scale;

        $image = imagecreate($total, $total);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        imagefill($image, 0, 0, $white);
        foreach ($modules as $y => $row) {
            foreach ($row as $x => $dark) {
                if ($dark) {
                    $px = ($x + self::QUIET_ZONE) * $scale;
                    $py = ($y + self::QUIET_ZONE) * $scale;
                    imagefilledrectangle($image, $px, $py, $px + $scale - 1, $py + $scale - 1, $black);
                }
            }
        }
        ob_start();
        imagepng($image);
        imagedestroy($image);

        return (string) ob_get_clean();
    }

    // ---- Encoding -----------------------------------------------------------

    /** The smallest version whose data capacity holds the text. */
    private static function version(int $bytes): int
    {
        for ($version = 1; $version <= self::MAX_VERSION; $version++) {
            $needed = 4 + ($version <= 9 ? 8 : 16) + 8 * $bytes;
            if ($needed <= self::dataCodewords($version) * 8) {
                return $version;
            }
        }

        throw new InvalidArgumentException('The text is too long for a QR code of version ' . self::MAX_VERSION . '.');
    }

    /** Modules available for codewords once the function patterns are placed. */
    private static function rawDataModules(int $version): int
    {
        $result = (16 * $version + 128) * $version + 64;
        if ($version >= 2) {
            $numAlign = intdiv($version, 7) + 2;
            $result  -= (25 * $numAlign - 10) * $numAlign - 55;
            if ($version >= 7) {
                $result -= 36;
            }
        }

        return $result;
    }

    private static function dataCodewords(int $version): int
    {
        return intdiv(self::rawDataModules($version), 8)
            - self::ECC_PER_BLOCK[$version - 1] * self::BLOCKS[$version - 1];
    }

    /**
     * Mode indicator, character count, the bytes, terminator and padding --
     * the data codewords before error correction.
     *
     * @param  array<int,int> $bytes
     * @return array<int,int>
     */
    private static function codewords(array $bytes, int $version): array
    {
        $bits = [];
        $push = static function (int $value, int $length) use (&$bits): void {
            for ($i = $length - 1; $i >= 0; $i--) {
                $bits[] = ($value >> $i) & 1;
            }
        };

        $push(0b0100, 4); // byte mode
        $push(count($bytes), $version <= 9 ? 8 : 16);
        foreach ($bytes as $byte) {
            $push($byte, 8);
        }

        $capacity = self::dataCodewords($version) * 8;
        $push(0, min(4, $capacity - count($bits)));           // terminator
        $push(0, (8 - count($bits) % 8) % 8);                 // to the byte boundary
        for ($pad = 0xEC; count($bits) < $capacity; $pad ^= 0xEC ^ 0x11) {
            $push($pad, 8);                                   // alternating pad bytes
        }

        $result = [];
        foreach (array_chunk($bits, 8) as $chunk) {
            $result[] = (int) bindec(implode('', $chunk));
        }

        return $result;
    }

    /**
     * Split the data codewords into blocks, add the Reed-Solomon codewords
     * of each, interleave both -- the final sequence for the symbol.
     *
     * @param  array<int,int> $data
     * @return array<int,int>
     */
    private static function interleave(array $data, int $version): array
    {
        $numBlocks   = self::BLOCKS[$version - 1];
        $eccLen      = self::ECC_PER_BLOCK[$version - 1];
        $rawCw       = intdiv(self::rawDataModules($version), 8);
        $numShort    = $numBlocks - $rawCw % $numBlocks;
        $shortLen    = intdiv($rawCw, $numBlocks);
        $divisor     = self::rsDivisor($eccLen);

        $blocks = [];
        $k      = 0;
        for ($i = 0; $i < $numBlocks; $i++) {
            $datLen = $shortLen - $eccLen + ($i < $numShort ? 0 : 1);
            $dat    = array_slice($data, $k, $datLen);
            $k     += $datLen;
            $ecc    = self::rsRemainder($dat, $divisor);
            if ($i < $numShort) {
                $dat[] = -1; // placeholder so every block has the same length
            }
            $blocks[] = array_merge($dat, $ecc);
        }

        $result = [];
        $len    = count($blocks[0]);
        for ($i = 0; $i < $len; $i++) {
            foreach ($blocks as $j => $block) {
                // The placeholder of the short blocks is skipped.
                if ($i !== $shortLen - $eccLen || $j >= $numShort) {
                    $result[] = $block[$i];
                }
            }
        }

        return $result;
    }

    /** @return array<int,int> generator polynomial coefficients of the given degree */
    private static function rsDivisor(int $degree): array
    {
        $result   = array_fill(0, $degree - 1, 0);
        $result[] = 1;
        $root     = 1;
        for ($i = 0; $i < $degree; $i++) {
            for ($j = 0; $j < $degree; $j++) {
                $result[$j] = self::gfMultiply($result[$j], $root) ^ ($j + 1 < $degree ? $result[$j + 1] : 0);
            }
            $root = self::gfMultiply($root, 2);
        }

        return $result;
    }

    /**
     * @param  array<int,int> $data
     * @param  array<int,int> $divisor
     * @return array<int,int>
     */
    private static function rsRemainder(array $data, array $divisor): array
    {
        $result = array_fill(0, count($divisor), 0);
        foreach ($data as $byte) {
            $factor   = $byte ^ array_shift($result);
            $result[] = 0;
            foreach ($divisor as $j => $coefficient) {
                $result[$j] ^= self::gfMultiply($coefficient, $factor);
            }
        }

        return $result;
    }

    /** Multiplication in GF(2^8) with the standard's polynomial x^8 + x^4 + x^3 + x^2 + 1. */
    private static function gfMultiply(int $x, int $y): int
    {
        $z = 0;
        for ($i = 7; $i >= 0; $i--) {
            $z = ($z << 1) ^ (($z >> 7) * 0x11D);
            $z ^= (($y >> $i) & 1) * $x;
        }

        return $z & 0xFF;
    }

    // ---- The symbol -----------------------------------------------------------

    /**
     * @param array<int,array<int,bool>> $modules  rows of modules, by [y][x]
     * @param array<int,array<int,bool>> $function which modules are function patterns
     */
    private static function drawFunctionPatterns(array &$modules, array &$function, int $version): void
    {
        $size = count($modules);

        // Timing patterns.
        for ($i = 0; $i < $size; $i++) {
            self::set($modules, $function, 6, $i, $i % 2 === 0);
            self::set($modules, $function, $i, 6, $i % 2 === 0);
        }

        // Finder patterns with their separators.
        foreach ([[3, 3], [$size - 4, 3], [3, $size - 4]] as [$cx, $cy]) {
            for ($dy = -4; $dy <= 4; $dy++) {
                for ($dx = -4; $dx <= 4; $dx++) {
                    $x = $cx + $dx;
                    $y = $cy + $dy;
                    if ($x >= 0 && $x < $size && $y >= 0 && $y < $size) {
                        $dist = max(abs($dx), abs($dy));
                        self::set($modules, $function, $x, $y, $dist !== 2 && $dist !== 4);
                    }
                }
            }
        }

        // Alignment patterns -- not where a finder pattern sits.
        $positions = self::alignmentPositions($version);
        $count     = count($positions);
        for ($i = 0; $i < $count; $i++) {
            for ($j = 0; $j < $count; $j++) {
                if (($i === 0 && $j === 0) || ($i === 0 && $j === $count - 1) || ($i === $count - 1 && $j === 0)) {
                    continue;
                }
                for ($dy = -2; $dy <= 2; $dy++) {
                    for ($dx = -2; $dx <= 2; $dx++) {
                        self::set($modules, $function, $positions[$i] + $dx, $positions[$j] + $dy, max(abs($dx), abs($dy)) !== 1);
                    }
                }
            }
        }

        // Reserve the format areas (written for real once the mask is chosen).
        self::drawFormatBits($modules, $function, 0);
        self::drawVersion($modules, $function, $version);
    }

    /** @return array<int,int> centre coordinates of the alignment patterns */
    private static function alignmentPositions(int $version): array
    {
        if ($version === 1) {
            return [];
        }
        $numAlign = intdiv($version, 7) + 2;
        $step     = intdiv($version * 4 + $numAlign * 2 + 1, $numAlign * 2 - 2) * 2;
        $result   = [6];
        for ($pos = $version * 4 + 10; count($result) < $numAlign; $pos -= $step) {
            array_splice($result, 1, 0, [$pos]);
        }

        return $result;
    }

    /**
     * @param array<int,array<int,bool>> $modules
     * @param array<int,array<int,bool>> $function
     */
    private static function drawFormatBits(array &$modules, array &$function, int $mask): void
    {
        $size = count($modules);
        $data = (self::ECL_BITS << 3) | $mask;
        $rem  = $data;
        for ($i = 0; $i < 10; $i++) {
            $rem = ($rem << 1) ^ (($rem >> 9) * 0x537);
        }
        $bits = (($data << 10) | $rem) ^ 0x5412;

        // First copy, around the top-left finder pattern.
        for ($i = 0; $i <= 5; $i++) {
            self::set($modules, $function, 8, $i, self::bit($bits, $i));
        }
        self::set($modules, $function, 8, 7, self::bit($bits, 6));
        self::set($modules, $function, 8, 8, self::bit($bits, 7));
        self::set($modules, $function, 7, 8, self::bit($bits, 8));
        for ($i = 9; $i < 15; $i++) {
            self::set($modules, $function, 14 - $i, 8, self::bit($bits, $i));
        }

        // Second copy, beside the other two finder patterns.
        for ($i = 0; $i < 8; $i++) {
            self::set($modules, $function, $size - 1 - $i, 8, self::bit($bits, $i));
        }
        for ($i = 8; $i < 15; $i++) {
            self::set($modules, $function, 8, $size - 15 + $i, self::bit($bits, $i));
        }
        self::set($modules, $function, 8, $size - 8, true); // always dark
    }

    /**
     * @param array<int,array<int,bool>> $modules
     * @param array<int,array<int,bool>> $function
     */
    private static function drawVersion(array &$modules, array &$function, int $version): void
    {
        if ($version < 7) {
            return;
        }
        $size = count($modules);
        $rem  = $version;
        for ($i = 0; $i < 12; $i++) {
            $rem = ($rem << 1) ^ (($rem >> 11) * 0x1F25);
        }
        $bits = ($version << 12) | $rem;
        for ($i = 0; $i < 18; $i++) {
            $bit = self::bit($bits, $i);
            $a   = $size - 11 + $i % 3;
            $b   = intdiv($i, 3);
            self::set($modules, $function, $a, $b, $bit);
            self::set($modules, $function, $b, $a, $bit);
        }
    }

    /**
     * Place the codewords in the zigzag the standard prescribes: two columns
     * at a time from the right, upwards and downwards in turn, skipping the
     * function modules and the timing column.
     *
     * @param array<int,array<int,bool>> $modules
     * @param array<int,array<int,bool>> $function
     * @param array<int,int>             $codewords
     */
    private static function drawCodewords(array &$modules, array $function, array $codewords): void
    {
        $size  = count($modules);
        $total = count($codewords) * 8;
        $i     = 0;
        for ($right = $size - 1; $right >= 1; $right -= 2) {
            if ($right === 6) {
                $right = 5;
            }
            for ($vert = 0; $vert < $size; $vert++) {
                for ($j = 0; $j < 2; $j++) {
                    $x      = $right - $j;
                    $upward = (($right + 1) & 2) === 0;
                    $y      = $upward ? $size - 1 - $vert : $vert;
                    if (!$function[$y][$x] && $i < $total) {
                        $modules[$y][$x] = self::bit($codewords[$i >> 3], 7 - ($i & 7));
                        $i++;
                    }
                }
            }
        }
    }

    /**
     * XOR one of the eight mask patterns onto the data modules.
     *
     * @param array<int,array<int,bool>> $modules
     * @param array<int,array<int,bool>> $function
     */
    private static function applyMask(array &$modules, array $function, int $mask): void
    {
        $size = count($modules);
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                if ($function[$y][$x]) {
                    continue;
                }
                $invert = match ($mask) {
                    0 => ($x + $y) % 2 === 0,
                    1 => $y % 2 === 0,
                    2 => $x % 3 === 0,
                    3 => ($x + $y) % 3 === 0,
                    4 => (intdiv($x, 3) + intdiv($y, 2)) % 2 === 0,
                    5 => $x * $y % 2 + $x * $y % 3 === 0,
                    6 => ($x * $y % 2 + $x * $y % 3) % 2 === 0,
                    default => (($x + $y) % 2 + $x * $y % 3) % 2 === 0,
                };
                if ($invert) {
                    $modules[$y][$x] = !$modules[$y][$x];
                }
            }
        }
    }

    /**
     * The standard's penalty score of a masked symbol: runs of one colour,
     * 2x2 blocks, finder-like patterns, the balance of dark and light.
     *
     * @param array<int,array<int,bool>> $modules
     */
    private static function penalty(array $modules): int
    {
        $size   = count($modules);
        $result = 0;

        // Rows and columns alike: runs of five or more, and finder-like
        // patterns (dark-light-dark-dark-dark-light-dark with four light
        // modules on either side).
        foreach ([false, true] as $columns) {
            for ($a = 0; $a < $size; $a++) {
                $line = [];
                for ($b = 0; $b < $size; $b++) {
                    $line[] = $columns ? $modules[$b][$a] : $modules[$a][$b];
                }
                $run = 1;
                for ($b = 1; $b <= $size; $b++) {
                    if ($b < $size && $line[$b] === $line[$b - 1]) {
                        $run++;
                        continue;
                    }
                    if ($run >= 5) {
                        $result += 3 + ($run - 5);
                    }
                    $run = 1;
                }
                $text = implode('', array_map(static fn (bool $d): string => $d ? '1' : '0', $line));
                $result += 40 * (preg_match_all('/(?=00001011101)/', $text) + preg_match_all('/(?=10111010000)/', $text));
            }
        }

        // 2x2 blocks of one colour.
        for ($y = 0; $y < $size - 1; $y++) {
            for ($x = 0; $x < $size - 1; $x++) {
                $c = $modules[$y][$x];
                if ($c === $modules[$y][$x + 1] && $c === $modules[$y + 1][$x] && $c === $modules[$y + 1][$x + 1]) {
                    $result += 3;
                }
            }
        }

        // Proportion of dark modules, in steps of five percent away from half.
        $dark = 0;
        foreach ($modules as $row) {
            $dark += count(array_filter($row));
        }
        $total   = $size * $size;
        $k       = intdiv(abs($dark * 20 - $total * 10), $total) - 1;
        $result += max(0, $k) * 10;

        return $result;
    }

    /**
     * @param array<int,array<int,bool>> $modules
     * @param array<int,array<int,bool>> $function
     */
    private static function set(array &$modules, array &$function, int $x, int $y, bool $dark): void
    {
        $modules[$y][$x]  = $dark;
        $function[$y][$x] = true;
    }

    private static function bit(int $value, int $i): bool
    {
        return (($value >> $i) & 1) === 1;
    }
}
