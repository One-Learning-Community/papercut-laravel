<?php

declare(strict_types=1);

namespace Papercut\Laravel;

use InvalidArgumentException;

/** Builds the Blade tag names (`<x-doc::page>`) shown in error messages, from `papercut.prefix`. */
final class Tag
{
    /** Last validated raw config value and its prefix, so `of()` does not run a regex per component. */
    private static mixed $raw = null;
    private static ?string $valid = null;

    public static function prefix(): string
    {
        $prefix = config('papercut.prefix', 'doc');
        if (self::$valid !== null && self::$raw === $prefix) {
            return self::$valid;
        }

        if (!is_string($prefix) || preg_match('/^[a-z][a-z0-9-]*$/', $prefix) !== 1) {
            throw new InvalidArgumentException(
                'Invalid papercut.prefix: ' . (is_string($prefix) ? $prefix : get_debug_type($prefix)) . '; expected lowercase letters, digits and dashes',
            );
        }

        self::$raw = $prefix;

        return self::$valid = $prefix;
    }

    public static function of(string $tag): string
    {
        return '<x-' . self::prefix() . '::' . $tag . '>';
    }

    public static function any(): string
    {
        return self::of('*');
    }
}
