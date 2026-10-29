<?php

declare(strict_types=1);

namespace Papercut\Laravel;

use Papercut\Text\FontStyle;

/** Reads the `papercut.fonts` config into flat, path-resolved face entries. */
final class FontConfig
{
    /** @return list<array{family: string, weight: int, style: FontStyle, path: string}> */
    public static function faces(): array
    {
        $config = config('papercut.fonts', []);
        if (!is_array($config)) {
            throw self::invalid('papercut.fonts', 'expected an array of families');
        }
        $out = [];
        foreach ($config as $family => $weights) {
            $at = 'papercut.fonts.' . $family;
            if (!is_array($weights)) {
                throw self::invalid($at, 'expected an array of weights');
            }
            foreach ($weights as $weight => $styles) {
                $at = 'papercut.fonts.' . $family . '.' . $weight;
                if (!is_int($weight) || $weight < 1 || $weight > 1000) {
                    throw self::invalid($at, 'weight must be an integer from 1 to 1000');
                }
                if (!is_array($styles)) {
                    throw self::invalid($at, 'expected an array keyed by "normal" and/or "italic"');
                }
                foreach ($styles as $style => $path) {
                    $at = 'papercut.fonts.' . $family . '.' . $weight . '.' . $style;
                    if ($style !== 'normal' && $style !== 'italic') {
                        throw self::invalid($at, 'style must be "normal" or "italic"');
                    }
                    if (!is_string($path) || $path === '') {
                        throw self::invalid($at, 'path must be a non-empty string');
                    }
                    $out[] = [
                        'family' => (string) $family,
                        'weight' => $weight,
                        'style' => $style === 'italic' ? FontStyle::Italic : FontStyle::Normal,
                        'path' => self::resolve($path),
                    ];
                }
            }
        }

        return $out;
    }

    /** @return list<string> */
    public static function fallbacks(): array
    {
        $config = config('papercut.fallback_fonts', []);
        if (!is_array($config)) {
            throw self::invalid('papercut.fallback_fonts', 'expected a list of family names');
        }
        $out = [];
        foreach ($config as $i => $family) {
            if (!is_string($family) || $family === '') {
                throw new \InvalidArgumentException(sprintf('Invalid font config papercut.fallback_fonts.%s: family must be a non-empty string', $i));
            }
            $out[] = $family;
        }

        return $out;
    }

    /**
     * The `papercut.font_word_names` map: family => the name Word should use for it.
     *
     * @param list<string> $known families that may be named (bundled and registered), compared case-insensitively
     * @return array<string, string>
     */
    public static function wordNames(array $known): array
    {
        $config = config('papercut.font_word_names', []);
        if (!is_array($config)) {
            throw self::invalid('papercut.font_word_names', 'expected a map of family => Word font name');
        }
        $known = array_map('strtolower', $known);
        $out = [];
        foreach ($config as $family => $name) {
            $at = 'papercut.font_word_names.' . $family;
            if (!is_string($family) || $family === '') {
                throw self::invalid($at, 'family must be a non-empty string');
            }
            if (!is_string($name) || $name === '') {
                throw self::invalid($at, 'Word font name must be a non-empty string');
            }
            if (!in_array(strtolower($family), $known, true)) {
                throw self::invalid($at, 'family is neither bundled nor registered in papercut.fonts');
            }
            $out[$family] = $name;
        }

        return $out;
    }

    private static function invalid(string $at, string $why): \InvalidArgumentException
    {
        return new \InvalidArgumentException(sprintf('Invalid font config %s: %s', $at, $why));
    }

    private static function resolve(string $path): string
    {
        $absolute = str_starts_with($path, '/') || str_starts_with($path, '\\') || preg_match('~^[A-Za-z]:[\\\\/]~', $path) === 1;

        return $absolute ? $path : base_path($path);
    }
}
