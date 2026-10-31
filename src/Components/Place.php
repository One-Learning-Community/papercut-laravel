<?php

declare(strict_types=1);

namespace Papercut\Laravel\Components;

use Papercut\Exception\{InvalidValue, UnknownAttribute};
use Papercut\Laravel\Exception\InvalidStructure;
use Papercut\Laravel\Tag;
use Papercut\Style\Anchor;

/** <x-doc::place>: content at a point on the page, for the overlay slot of <x-doc::page>. */
final class Place extends PapercutComponent
{
    protected function ownAttributes(): array
    {
        return ['x', 'y', 'anchor', 'width'];
    }

    protected function build(array $data): object
    {
        ['own' => $own, 'style' => $style] = $this->attrs($data);
        $location = 'on ' . Tag::of('place');
        foreach (array_keys($style) as $key) {
            throw UnknownAttribute::for($key)->within($location);
        }
        if (!isset($own['x'], $own['y'])) {
            throw new InvalidStructure(Tag::of('place') . ' requires x and y, as lengths or percentages of the page');
        }

        $value = static function (string $key) use ($own, $location): string {
            $v = $own[$key];
            if (!is_string($v) && !is_int($v) && !is_float($v)) {
                throw InvalidValue::for($key, get_debug_type($v))->withContext($key)->within($location);
            }
            return (string) $v;
        };

        try {
            $place = \Papercut\Place::at($value('x'), $value('y'), ...$this->children($data));
        } catch (InvalidValue $e) {
            throw $e->within($location);
        }
        if (isset($own['width'])) {
            try {
                $place = $place->width($value('width'));
            } catch (InvalidValue $e) {
                throw $e->withContext('width')->within($location);
            }
        }
        if (isset($own['anchor'])) {
            try {
                $place = $place->anchor($value('anchor'));
            } catch (InvalidValue $e) {
                throw $e->because('expected one of ' . implode(', ', array_map(static fn (Anchor $a): string => $a->value, Anchor::cases())))
                    ->withContext('anchor')->within($location);
            }
        }

        return $place;
    }
}
