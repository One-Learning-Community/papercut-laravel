<?php

declare(strict_types=1);

namespace Papercut\Laravel\Rendering;

use Papercut\Exception\ImageUnreadable;
use Papercut\PapercutException;

/** Where an image, SVG or PDF src points, for the <image>, <svg> and <pdf-page> tags and page templates. */
final class ImagePath
{
    private function __construct()
    {
    }

    /**
     * Relative paths must stay inside the image base (textual containment: symlinks inside the base, e.g. public/storage, are followed). Absolute paths (including stream wrappers and
     * Windows drive paths) are trusted: never feed untrusted input to src.
     */
    /**
     * @param string $what names the file in errors
     * @param class-string<PapercutException> $error the exception to throw
     */
    public static function resolve(string $src, string $what = 'Image', string $error = ImageUnreadable::class): string
    {
        if (str_contains($src, "\0")) {
            throw new $error("$what path contains a NUL byte");
        }
        $absolute = $src !== '' && ($src[0] === '/' || $src[0] === '\\'
            || preg_match('#^[A-Za-z][A-Za-z0-9+.-]*://#', $src) === 1
            || preg_match('#^[A-Za-z]:[\\\\/]#', $src) === 1);
        if ($absolute) {
            $path = $src;
        } else {
            $base = config('papercut.image_path');
            $base = is_string($base) && $base !== '' ? $base : public_path();
            $stack = [];
            foreach (preg_split('#[/\\\\]#', $src) ?: [] as $segment) {
                if ($segment === '' || $segment === '.') {
                    continue;
                }
                if ($segment === '..') {
                    if ($stack === []) {
                        throw new $error("$what path escapes the image directory: $src");
                    }
                    array_pop($stack);
                } else {
                    $stack[] = $segment;
                }
            }
            $path = rtrim($base, '/') . ($stack === [] ? '' : '/' . implode('/', $stack));
        }
        if (!is_file($path)) {
            throw new $error("$what not found: $path");
        }

        return $path;
    }
}
