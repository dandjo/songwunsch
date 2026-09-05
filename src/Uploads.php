<?php

declare(strict_types=1);

namespace Songwunsch;

/**
 * Files the admin uploads -- today the header logos. They live in the
 * `uploads` table, not on disk: the deployment syncs the code with --delete
 * and must not touch them, a shared host needs no writable folder, and a
 * database backup carries them along. A raster image is scaled down to
 * TARGET_HEIGHT and re-encoded as WebP on upload (GD), so any original fits
 * and what is stored is small; an SVG stays as it is. index.php delivers a
 * file under its own address (/logo/<id>); the bytes of an id never change,
 * so the address may be cached for good.
 *
 * Which logo the header shows is a setting (Settings::LOGO_ID) -- one at a
 * time, or none for the word mark.
 */
final class Uploads
{
    private const TABLE = '`' . Schema::UPLOADS . '`';

    public const LOGO = 'logo';

    /** Height a raster logo is scaled to: the header shows 48 CSS px, three times that keeps it sharp on any screen. */
    public const TARGET_HEIGHT = 144;
    /** WebP quality of a stored raster image: high enough to keep the edges of a logo crisp, a third of the PNG's size. */
    public const WEBP_QUALITY = 90;
    /** Pixel count above which an image is refused rather than decoded -- a guard for the memory, not a rule for users. */
    public const MAX_PIXELS = 50_000_000;
    /** Accepted image types (by content, not by file name). */
    public const MIMES = ['image/png', 'image/jpeg', 'image/webp', 'image/gif', 'image/svg+xml'];

    private const INFO = 'SELECT id, kind, mime, width, height, LENGTH(data) AS size, created_at FROM ' . self::TABLE;

    public function __construct(private readonly Database $db)
    {
    }

    /**
     * One page of the uploads of a kind, newest first, plus the total.
     *
     * @return array{rows: array<int,array{id:int,kind:string,mime:string,width:?int,height:?int,size:int,created_at:string}>, total: int}
     */
    public function page(string $kind, int $page, int $perPage): array
    {
        $total  = (int) ($this->db->one('SELECT COUNT(*) AS c FROM ' . self::TABLE . ' WHERE kind = ?', [$kind])['c'] ?? 0);
        $offset = max(0, ($page - 1) * $perPage);
        $rows   = array_map(
            [self::class, 'row'],
            $this->db->all(self::INFO . " WHERE kind = ? ORDER BY created_at DESC, id DESC LIMIT {$perPage} OFFSET {$offset}", [$kind]),
        );

        return ['rows' => $rows, 'total' => $total];
    }

    /** @return array{id:int,kind:string,mime:string,width:?int,height:?int,size:int,created_at:string}|null */
    public function info(int $id): ?array
    {
        $row = $id > 0 ? $this->db->one(self::INFO . ' WHERE id = ?', [$id]) : null;

        return $row === null ? null : self::row($row);
    }

    /** @return array{mime:string,data:string}|null */
    public function load(int $id): ?array
    {
        $row = $id > 0 ? $this->db->one('SELECT mime, data FROM ' . self::TABLE . ' WHERE id = ?', [$id]) : null;

        return $row === null ? null : ['mime' => (string) $row['mime'], 'data' => (string) $row['data']];
    }

    /**
     * @param array{mime:string,data:string,width:?int,height:?int} $file result of check()
     * @return int id of the new file
     */
    public function add(string $kind, array $file): int
    {
        $this->db->exec(
            'INSERT INTO ' . self::TABLE . ' (kind, mime, data, width, height, created_at) VALUES (?, ?, ?, ?, ?, ?)',
            [$kind, $file['mime'], $file['data'], $file['width'], $file['height'], date('Y-m-d H:i:s')],
        );

        return (int) $this->db->pdo()->lastInsertId();
    }

    public function delete(int $id): bool
    {
        return $this->db->exec('DELETE FROM ' . self::TABLE . ' WHERE id = ? LIMIT 1', [$id]) === 1;
    }

    /**
     * Check an entry of $_FILES: upload errors, size, type by content, image
     * dimensions. SVG is accepted as markup without scripting -- it is only
     * ever shown through <img>, where scripts do not run anyway.
     *
     * @param array<string,mixed> $file
     * @return array{errors:array<int,string>,mime:string,data:string,width:?int,height:?int}
     */
    public function check(array $file): array
    {
        $fail = static fn (string $message): array => ['errors' => [$message], 'mime' => '', 'data' => '', 'width' => null, 'height' => null];
        $err  = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($err === UPLOAD_ERR_NO_FILE) {
            return $fail(t('Please choose a file first.'));
        }
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
            return $fail(t('The file is larger than the server accepts ({max}).', ['max' => (string) ini_get('upload_max_filesize')]));
        }
        if ($err !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
            return $fail(t('The upload failed – please try again.'));
        }

        $data = (string) file_get_contents((string) $file['tmp_name']);
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->buffer($data);

        // finfo sees an SVG as XML or text; the <svg> root decides. Vector
        // graphics are stored as they are -- they scale by themselves.
        if (in_array($mime, ['image/svg+xml', 'image/svg', 'text/xml', 'application/xml', 'text/plain', 'text/html'], true)
            && preg_match('/<svg[\s>]/i', $data) === 1) {
            if (preg_match('/<script|javascript:|\son[a-z]+\s*=/i', $data) === 1) {
                return $fail(t('The SVG contains scripting and was rejected.'));
            }

            return ['errors' => [], 'mime' => 'image/svg+xml', 'data' => $data, 'width' => null, 'height' => null];
        }
        if (!in_array($mime, self::MIMES, true)) {
            return $fail(t('Only PNG, JPEG, WebP, GIF or SVG images are accepted.'));
        }

        $dims = @getimagesizefromstring($data);
        if ($dims === false) {
            return $fail(t('This file is not a readable image.'));
        }
        [$width, $height] = [(int) $dims[0], (int) $dims[1]];
        if ($width * $height > self::MAX_PIXELS) {
            return $fail(t('The image has too many pixels to be processed ({w} × {h}).', ['w' => $width, 'h' => $height]));
        }

        // A WebP that is small enough already is stored as it is -- encoding
        // it again would only lose a little more. Everything else goes
        // through GD: scaled down if need be, and out as WebP.
        $keep = $mime === 'image/webp' && $height <= self::TARGET_HEIGHT;
        if (!$keep && function_exists('imagecreatefromstring')) {
            $converted = self::convert($data, $mime, $width, $height);
            if ($converted === null) {
                return $fail(t('The image could not be processed – try a PNG.'));
            }
            [$mime, $data, $width, $height] = $converted;
        }

        return ['errors' => [], 'mime' => $mime, 'data' => $data, 'width' => $width, 'height' => $height];
    }

    /**
     * Scale a raster image down to TARGET_HEIGHT if it is taller, keeping
     * the aspect ratio and the transparency, and encode it as WebP
     * (WEBP_QUALITY) -- a third of a PNG's size, alpha included. Without
     * WebP support in GD the old rule applies: JPEG stays JPEG (it has no
     * alpha anyway), everything else comes out as PNG. An animated GIF
     * loses its animation, which a header logo does not need.
     *
     * @return array{0:string,1:string,2:int,3:int}|null mime, data, width, height
     */
    private static function convert(string $data, string $mime, int $width, int $height): ?array
    {
        $src = @imagecreatefromstring($data);
        if ($src === false) {
            return null;
        }
        $newHeight = min($height, self::TARGET_HEIGHT);
        $newWidth  = $newHeight === $height ? $width : max(1, (int) round($width * $newHeight / $height));

        $dst = imagecreatetruecolor($newWidth, $newHeight);
        if ($dst === false) {
            return null;
        }
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, (int) imagecolorallocatealpha($dst, 0, 0, 0, 127));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        ob_start();
        if (function_exists('imagewebp')) {
            $mime = 'image/webp';
            imagewebp($dst, null, self::WEBP_QUALITY);
        } elseif ($mime === 'image/jpeg') {
            imagejpeg($dst, null, 90);
        } else {
            $mime = 'image/png';
            imagepng($dst, null, 9);
        }
        $out = (string) ob_get_clean();
        imagedestroy($src);
        imagedestroy($dst);

        return $out === '' ? null : [$mime, $out, $newWidth, $newHeight];
    }

    /**
     * @param array<string,mixed> $row
     * @return array{id:int,kind:string,mime:string,width:?int,height:?int,size:int,created_at:string}
     */
    private static function row(array $row): array
    {
        return [
            'id'         => (int) $row['id'],
            'kind'       => (string) $row['kind'],
            'mime'       => (string) $row['mime'],
            'width'      => $row['width'] === null ? null : (int) $row['width'],
            'height'     => $row['height'] === null ? null : (int) $row['height'],
            'size'       => (int) $row['size'],
            'created_at' => (string) $row['created_at'],
        ];
    }
}
