<?php

declare(strict_types=1);

namespace Papercut\Laravel\Components;

use Papercut\Exception\InvalidValue;
use Papercut\Laravel\Tag;
use Papercut\ListItem;

final class Li extends PapercutComponent
{
    protected function ownAttributes(): array
    {
        return ['value'];
    }

    protected function build(array $data): object
    {
        ['own' => $own] = $this->attrs($data);
        /** @var list<\Papercut\Node> $children */
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
