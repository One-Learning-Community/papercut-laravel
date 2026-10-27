<?php

declare(strict_types=1);

namespace Papercut\Laravel\Components;

use Papercut\Barcode\{ErrorCorrection, QrEyes, QrGradient, QrShape};
use Papercut\Exception\{InvalidValue, UnknownAttribute};
use Papercut\Laravel\Exception\InvalidStructure;
use Papercut\Laravel\Tag;
use Papercut\Value\Color;

final class QrCode extends PapercutComponent
{
    protected function ownAttributes(): array
    {
        return ['value', 'level', 'quiet-zone', 'shape', 'eyes', 'eye-color', 'gradient', 'gradient-type'];
    }

    protected function build(array $data): object
    {
        if (trim($this->text($data)) !== '') {
            throw new InvalidStructure(Tag::of('qr-code') . ' must not have content; use the value attribute');
        }
        ['own' => $own, 'style' => $style] = $this->attrs($data);
        // A misspelt value or level would otherwise reach the style check, which only suggests style names.
        foreach (array_keys($style) as $key) {
            foreach ($this->ownAttributes() as $candidate) {
                if (levenshtein((string) $key, $candidate) <= 2) {
                    throw UnknownAttribute::for((string) $key, $candidate)->within('on ' . Tag::of('qr-code'));
                }
            }
        }
        $value = $own['value'] ?? null;
        if (!is_string($value) && !is_int($value)) {
            throw new InvalidStructure(Tag::of('qr-code') . ' requires a value');
        }
        $node = \Papercut\QrCode::make((string) $value, self::level($own['level'] ?? 'M'));
        if (isset($own['quiet-zone'])) {
            $zone = $own['quiet-zone'];
            if (!is_numeric($zone) || (string) (int) $zone !== trim((string) $zone) || (int) $zone < 0) {
                throw InvalidValue::for('quiet-zone', is_scalar($zone) ? (string) $zone : get_debug_type($zone))->because('expected a whole number of modules, 0 or more')->withContext('quiet-zone')->within('on ' . Tag::of('qr-code'));
            }
            $node = $node->quietZone((int) $zone);
        }
        if (isset($own['shape'])) {
            $node = $node->shape(self::choice($own['shape'], 'shape', QrShape::class, 'square, rounded or dots'));
        }
        if (isset($own['eyes'])) {
            $node = $node->eyes(self::choice($own['eyes'], 'eyes', QrEyes::class, 'square, dot or pointy'));
        }
        if (isset($own['eye-color'])) {
            $node = $node->eyeColor(self::color($own['eye-color'], 'eye-color'));
        }
        if (isset($own['gradient'])) {
            $node = $node->gradient(...self::gradient($own['gradient']), type: isset($own['gradient-type'])
                ? self::choice($own['gradient-type'], 'gradient-type', QrGradient::class, 'horizontal, vertical, diagonal, inverse-diagonal or radial')
                : QrGradient::Diagonal);
        } elseif (isset($own['gradient-type'])) {
            throw self::invalid('gradient-type', $own['gradient-type'], 'it needs a gradient, such as gradient="#4f46e5 #0e7490"');
        }

        return $this->styled($node, $data);
    }

    /**
     * @template T of \BackedEnum
     * @param class-string<T> $enum
     * @return T
     */
    private static function choice(mixed $value, string $attribute, string $enum, string $expected): \BackedEnum
    {
        $choice = is_string($value) ? $enum::tryFrom(strtolower(trim($value))) : null;

        return $choice ?? throw self::invalid($attribute, $value, "expected $expected");
    }

    private static function color(mixed $value, string $attribute): Color
    {
        if ($value instanceof Color) {
            return $value;
        }
        if (!is_string($value)) {
            throw self::invalid($attribute, $value, 'expected a color');
        }

        return Color::parse($value);
    }

    /** @return array{Color, Color} two colors separated by a space; a color's own spaces sit inside brackets */
    private static function gradient(mixed $value): array
    {
        $colors = is_string($value) ? preg_split('/\s+(?![^(]*\))/', trim($value)) : false;
        if ($colors === false || count($colors) !== 2) {
            throw self::invalid('gradient', $value, 'expected two colors separated by a space, such as "#4f46e5 #0e7490"');
        }

        return [self::color($colors[0], 'gradient'), self::color($colors[1], 'gradient')];
    }

    private static function invalid(string $attribute, mixed $value, string $because): InvalidValue
    {
        return InvalidValue::for($attribute, is_scalar($value) ? (string) $value : get_debug_type($value))->because($because)->withContext($attribute)->within('on ' . Tag::of('qr-code'));
    }

    private static function level(mixed $level): ErrorCorrection
    {
        if ($level instanceof ErrorCorrection) {
            return $level;
        }
        $key = is_string($level) ? strtolower(trim($level)) : '';

        return match ($key) {
            'l', 'low' => ErrorCorrection::Low,
            'm', 'medium' => ErrorCorrection::Medium,
            'q', 'quartile' => ErrorCorrection::Quartile,
            'h', 'high' => ErrorCorrection::High,
            default => throw InvalidValue::for('level', is_scalar($level) ? (string) $level : get_debug_type($level))->because('expected L, M, Q or H')->withContext('level')->within('on ' . Tag::of('qr-code')),
        };
    }
}
