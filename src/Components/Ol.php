<?php

declare(strict_types=1);

namespace Papercut\Laravel\Components;

use Papercut\Exception\InvalidValue;
use Papercut\Laravel\Exception\InvalidStructure;
use Papercut\Laravel\Tag;
use Papercut\ListItem;
use Papercut\ListNode;
use Papercut\Style\ListMarker;

class Ol extends PapercutComponent
{
    protected function ownAttributes(): array
    {
        return ['start', 'marker'];
    }

    protected function ordered(): bool
    {
        return true;
    }

    protected function build(array $data): object
    {
        ['own' => $own] = $this->attrs($data);
        $items = [];
        foreach ($this->rawChildren($data, [ListItem::class]) as $child) {
            if (!$child instanceof ListItem) {
                throw new InvalidStructure(Tag::of($this->tag()) . ' can only contain ' . Tag::of('li'));
            }
            $items[] = $child;
        }
        $where = 'on ' . Tag::of($this->tag());
        $list = $this->ordered() ? ListNode::ordered(...$items) : ListNode::unordered(...$items);

        if (isset($own['start'])) {
            $raw = $own['start'];
            if (!is_int($raw) && !(is_string($raw) && preg_match('/^-?\d+$/', $raw) === 1)) {
                throw InvalidValue::for('start', is_scalar($raw) ? (string) $raw : get_debug_type($raw))->because('expected an integer')->withContext('start')->within($where);
            }
            $list = $list->start((int) $raw);
        }
        if (isset($own['marker'])) {
            $marker = ListMarker::tryFrom((string) $own['marker'])
                ?? throw InvalidValue::for('marker', (string) $own['marker'])->because('expected one of ' . implode(', ', array_map(fn (ListMarker $m) => $m->value, ListMarker::cases())))->withContext('marker')->within($where);
            $list = $list->marker($marker);
        }

        return $this->styled($list, $data);
    }
}
