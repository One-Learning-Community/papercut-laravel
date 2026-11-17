<?php

declare(strict_types=1);

namespace Quoin\Laravel\Components;

use Quoin\Exception\InvalidValue;
use Quoin\Laravel\Tag;
use Quoin\ListItem;

/** @internal Engine detail, not public API. It may change in any release. */
final class Li extends QuoinComponent
{
    protected function ownAttributes(): array
    {
        return ['value'];
    }

    protected function build(array $data): object
    {
        ['own' => $own] = $this->attrs($data);
        /** @var list<\Quoin\Node> $children */
        $children = $this->children($data);
        $item = ListItem::make(...$children);

        if (isset($own['value'])) {
            $raw = $own['value'];
            if (!is_int($raw) && !(is_string($raw) && preg_match('/^-?\d+$/', $raw) === 1)) {
                throw InvalidValue::for('value', is_scalar($raw) ? (string) $raw : get_debug_type($raw))->because('expected an integer')->withContext('value')->within('on ' . Tag::of('li'));
            }
            $item = $item->withValue((int) $raw);
        }

        return $this->styled($item, $data);
    }
}
