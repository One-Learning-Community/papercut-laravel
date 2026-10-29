<?php

declare(strict_types=1);

namespace Papercut\Laravel\Components;

use Papercut\Laravel\Rendering\NodeRegistry;
use Papercut\Decorator\ChoiceList;
use Papercut\Decorator\ChoiceOption;
use Papercut\Exception\InvalidValue;
use Papercut\Laravel\Exception\InvalidStructure;
use Papercut\Laravel\Tag;

final class Choices extends PapercutComponent
{
    /** @return list<string> */
    protected function ownAttributes(): array
    {
        return ['type', 'emphasize-selected', 'variant'];
    }

    protected function build(array $data): object
    {
        ['own' => $own] = $this->attrs($data);
        $where = 'on ' . Tag::of('choices');
        $type = isset($own['type']) ? $this->scalarOwn($own['type'], 'type') : 'checkbox';
        $list = match ($type) {
            'radio' => ChoiceList::radio(),
            'checkbox' => ChoiceList::checkbox(),
            default => throw InvalidValue::for('type', $type)->because('expected one of radio, checkbox')->withContext('type')->within($where),
        };
        $options = [];
        foreach ($this->rawChildren($data, [ChoiceOption::class]) as $child) {
            if (!$child instanceof ChoiceOption) {
                throw new InvalidStructure(Tag::of('choices') . ' can only contain ' . Tag::of('choice') . NodeRegistry::staleHint($child));
            }
            $options[] = $child;
        }
        $list = $list->add(...$options);
        if (array_key_exists('emphasize-selected', $own)) {
            $list = $list->emphasizeSelected($this->boolOwn($own['emphasize-selected'], 'emphasize-selected'));
        }

        return $this->styled($list, $data);
    }
}
