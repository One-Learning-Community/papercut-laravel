<?php

declare(strict_types=1);

namespace Papercut\Laravel;

use Papercut\Exception\{InvalidValue, UnknownAttribute};
use Papercut\Style\{Style, ThemeOverride};
use Papercut\Value\Color;

use function Papercut\pt;

/**
 * Turns a theme array (config, a tag's `:theme`, `->theme()`) into a {@see ThemeOverride}, validating each value.
 *
 * @internal Part of Papercut's engine, not its public API. It may change in any release.
 */
final class ThemeConfig
{
    /** The `$where` of `papercut.theme`, whose messages name the config key rather than a call site. */
    public const string CONFIG = 'in config papercut.theme';

    /** The keys `papercut.theme` accepts; the rest of {@see self::KEYS} are for overrides only. */
    public const array CONFIG_KEYS = ['font_family', 'mono_family', 'font_size', 'color', 'line_height'];

    public const array KEYS = ['font_family', 'mono_family', 'font_size', 'color', 'line_height', 'code_theme', 'styles'];

    /**
     * @param array<mixed> $values
     * @param string $where `on <x-doc::page>`, `in ->theme()` or {@see self::CONFIG}; named in every error
     * @param list<string> $families the bundled and registered font families
     */
    public static function override(array $values, string $where, array $families): ThemeOverride
    {
        $config = $where === self::CONFIG;
        foreach (array_keys($values) as $key) {
            if ($config) {
                $hint = match ($key) {
                    'code_theme' => '; set the code theme with papercut.code.theme',
                    'styles' => '; define named styles in papercut.styles',
                    default => '',
                };
                if ($hint !== '' || !in_array($key, self::KEYS, true)) {
                    throw new \InvalidArgumentException("Unknown key papercut.theme.$key" . ($hint ?: '; allowed keys: ' . implode(', ', self::CONFIG_KEYS)));
                }
            }
            if (!in_array($key, self::KEYS, true)) {
                throw new \InvalidArgumentException('Unknown theme key ' . var_export($key, true) . " $where; allowed keys: " . implode(', ', self::KEYS));
            }
        }

        $label = static fn (string $key): string => $config ? "papercut.theme.$key" : "theme $key $where";

        try {
            $color = isset($values['color']) ? Color::parse(self::string($values['color'], $label('color'), 'a colour')) : null;
        } catch (InvalidValue $e) {
            throw $e->within($config ? self::CONFIG . '.color' : $where);
        }

        $family = null;
        if (isset($values['font_family'])) {
            $family = self::string($values['font_family'], $label('font_family'), 'a font family name');
            if (!$config && !self::known($family, $families)) {
                throw new \InvalidArgumentException('Invalid ' . $label('font_family') . ": '$family' is neither bundled nor registered in papercut.fonts");
            }
        }

        $mono = $values['mono_family'] ?? null;
        if ($mono !== null) {
            if (!is_string($mono)) {
                throw new \InvalidArgumentException('Invalid ' . $label('mono_family') . ': ' . self::shown($mono) . '; expected a font family name');
            }
            if (trim($mono) === '' || strtolower(trim($mono)) === 'monospace') {
                throw new \InvalidArgumentException('Invalid ' . $label('mono_family') . ': ' . var_export($mono, true) . '; the keyword `monospace` stands for it, so name a real font family');
            }
            if (!self::known($mono, $families)) {
                throw new \InvalidArgumentException('Invalid ' . $label('mono_family') . ": '$mono' is neither bundled nor registered in papercut.fonts");
            }
        }

        $code = $values['code_theme'] ?? null;
        if ($code !== null && (!is_string($code) || $code === '')) {
            throw new \InvalidArgumentException('Invalid ' . $label('code_theme') . ': ' . self::shown($code) . '; expected a code theme name');
        }

        $size = self::positive($values, 'font_size', $label);
        $height = self::positive($values, 'line_height', $label);

        return new ThemeOverride(
            fontFamily: $family,
            fontSize: $size === null ? null : pt($size),
            color: $color,
            lineHeight: $height,
            monoFamily: $mono,
            codeTheme: $code,
            styles: $config ? [] : self::styles($values['styles'] ?? [], $where, $label('styles')),
        );
    }

    /**
     * @return array<string, Style>
     */
    private static function styles(mixed $styles, string $where, string $label): array
    {
        if (!is_array($styles) || ($styles !== [] && array_is_list($styles))) {
            throw new \InvalidArgumentException("Invalid $label: " . self::shown($styles) . '; expected a map of style name => attribute array');
        }
        $out = [];
        foreach ($styles as $name => $attrs) {
            if (!is_array($attrs)) {
                throw new \InvalidArgumentException("Invalid $label.$name: " . self::shown($attrs) . '; expected an attribute array');
            }
            try {
                $out[(string) $name] = Style::fromAttributes($attrs);
            } catch (InvalidValue|UnknownAttribute $e) {
                throw $e->within("in theme style $name")->within($where);
            }
        }

        return $out;
    }

    /** @param list<string> $families */
    private static function known(string $family, array $families): bool
    {
        return in_array(strtolower(trim($family)), array_map('strtolower', $families), true);
    }

    private static function string(mixed $v, string $label, string $expected): string
    {
        if (!is_scalar($v)) {
            throw new \InvalidArgumentException("Invalid $label: " . self::shown($v) . "; expected $expected");
        }

        return (string) $v;
    }

    /**
     * @param array<mixed> $values
     * @param \Closure(string): string $label
     */
    private static function positive(array $values, string $key, \Closure $label): ?float
    {
        if (!isset($values[$key])) {
            return null;
        }
        $v = $values[$key];
        if (!is_numeric($v) || (float) $v <= 0) {
            throw new \InvalidArgumentException('Invalid ' . $label($key) . ': ' . self::shown($v) . '; expected a number greater than 0');
        }

        return (float) $v;
    }

    private static function shown(mixed $v): string
    {
        return is_scalar($v) ? var_export($v, true) : get_debug_type($v);
    }
}
