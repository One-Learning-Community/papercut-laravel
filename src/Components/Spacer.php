<?php

declare(strict_types=1);

namespace Papercut\Laravel\Components;

use Papercut\Laravel\Tag;
use Papercut\Exception\{InvalidValue, UnknownAttribute};
use Papercut\Value\Length;

final class Spacer extends PapercutComponent
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
            throw new \Papercut\Laravel\Exception\InvalidStructure(Tag::of('spacer') . ' takes size or grow, not both');
        }

        if (isset($own['size'])) {
            $size = $own['size'];
            try {
                $length = ($size instanceof Length ? $size : Length::parse((string) $size))->absolute('spacer size');
            } catch (InvalidValue $e) {
                throw $e->withContext('size')->within($location);
            }

            return $this->applyStyle(\Papercut\Spacer::size($length), [], $own['style'] ?? null);
        }

        $grow = $style['grow'] ?? 1;
        if (!is_int($grow) && !is_float($grow) && !(is_string($grow) && is_numeric($grow)) || $grow < 0) {
            $shown = is_scalar($grow) ? (string) $grow : get_debug_type($grow);
            throw InvalidValue::for('number', $shown)->withContext('grow')->within($location);
        }

        return $this->applyStyle(\Papercut\Spacer::flex((float) $grow), [], $own['style'] ?? null);
    }
}
