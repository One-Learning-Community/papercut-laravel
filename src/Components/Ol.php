<?php

declare(strict_types=1);

namespace Quoin\Laravel\Components;

use Quoin\Laravel\Rendering\NodeRegistry;
use Quoin\Exception\InvalidValue;
use Quoin\Laravel\Exception\InvalidStructure;
use Quoin\Laravel\Tag;
use Quoin\ListItem;
use Quoin\ListNode;
use Quoin\Style\ListMarker;

/** @internal Engine detail, not public API. It may change in any release. */
class Ol extends QuoinComponent
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
                throw new InvalidStructure(Tag::of($this->tag()) . ' can only contain ' . Tag::of('li') . NodeRegistry::staleHint($child));
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
