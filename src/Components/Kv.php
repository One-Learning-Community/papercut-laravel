<?php

declare(strict_types=1);

namespace Quoin\Laravel\Components;

use Quoin\Decorator\KeyValuePair;
use Quoin\Exception\InvalidValue;
use Quoin\Laravel\Tag;
use Quoin\Text;
use Quoin\View;

/** @internal Engine detail, not public API. It may change in any release. */
final class Kv extends QuoinComponent
{
    /** @return list<string> */
    protected function ownAttributes(): array
    {
        return ['label', 'total'];
    }

    protected function build(array $data): object
    {
        $this->rejectStyleAttributes($data);
        $label = $this->slotOrAttribute($data, 'label');
        if (is_string($label) && trim($label) === '') {
            $label = null;
        }
        $label ??= throw InvalidValue::for('label', '')->because('expected a label attribute or a label slot')->withContext('label')->within('on ' . Tag::of('kv'));
        $nodes = $this->children($data);
        $value = match (count($nodes)) {
            0 => Text::make(''),
            1 => $nodes[0],
            default => View::make(...$nodes),
        };

        ['own' => $own] = $this->attrs($data);
        $total = false;
        if (isset($own['total'])) {
            $total = $this->boolOwn($own['total'], 'total');
        }

        return KeyValuePair::of($label, $value, $total);
    }
}
