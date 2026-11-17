<?php

declare(strict_types=1);

namespace Quoin\Laravel\Components;

use Quoin\Laravel\Tag;
use Quoin\Exception\{InvalidValue, UnknownAttribute};
use Quoin\Value\Length;

/** @internal Engine detail, not public API. It may change in any release. */
final class Spacer extends QuoinComponent
{
    protected function ownAttributes(): array
    {
        return ['size'];
    }

    protected function build(array $data): object
    {
        ['own' => $own, 'style' => $style] = $this->attrs($data);
        $location = 'on ' . Tag::of('spacer');
        foreach (array_keys($style) as $key) {
            if ($key !== 'grow') {
                throw UnknownAttribute::for($key)->within($location);
            }
        }

        if (isset($own['size']) && array_key_exists('grow', $style)) {
            throw new \Quoin\Laravel\Exception\InvalidStructure(Tag::of('spacer') . ' takes size or grow, not both');
        }

        if (isset($own['size'])) {
            $size = $own['size'];
            try {
                $length = ($size instanceof Length ? $size : Length::parse((string) $size))->absolute('spacer size');
            } catch (InvalidValue $e) {
                throw $e->withContext('size')->within($location);
            }

            return $this->applyStyle(\Quoin\Spacer::fixed($length), [], $own['style'] ?? null);
        }

        $grow = $style['grow'] ?? 1;
        if (!is_int($grow) && !is_float($grow) && !(is_string($grow) && is_numeric($grow)) || $grow < 0) {
            $shown = is_scalar($grow) ? (string) $grow : get_debug_type($grow);
            throw InvalidValue::for('number', $shown)->withContext('grow')->within($location);
        }

        return $this->applyStyle(\Quoin\Spacer::flex((float) $grow), [], $own['style'] ?? null);
    }
}
