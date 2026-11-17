<?php

declare(strict_types=1);

namespace Quoin\Laravel\Components;

use Quoin\Laravel\Rendering\NodeRegistry;
use Quoin\Decorator\ChoiceList;
use Quoin\Decorator\ChoiceOption;
use Quoin\Exception\InvalidValue;
use Quoin\Laravel\Exception\InvalidStructure;
use Quoin\Laravel\Tag;

/** @internal Engine detail, not public API. It may change in any release. */
final class Choices extends QuoinComponent
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
