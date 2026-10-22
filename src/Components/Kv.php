<?php

declare(strict_types=1);

namespace Papercut\Laravel\Components;

use Papercut\Decorator\KeyValuePair;
use Papercut\Exception\InvalidValue;
use Papercut\Laravel\Tag;
use Papercut\Text;
use Papercut\View;

final class Kv extends PapercutComponent
{
    /** @return list<string> */
    protected function ownAttributes(): array
    {
        return ['label'];
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

        return KeyValuePair::of($label, $value);
    }
}
