<?php

declare(strict_types=1);

namespace Papercut\Laravel\Components;

use Papercut\Decorator\KeyValueList;
use Papercut\Decorator\KeyValuePair;
use Papercut\Exception\InvalidValue;
use Papercut\Laravel\Exception\InvalidStructure;
use Papercut\Laravel\Tag;
use Papercut\Value\Length;
use Papercut\Value\Percent;

final class KeyValue extends PapercutComponent
{
    /** @return list<string> */
    protected function ownAttributes(): array
    {
        return ['label-width'];
    }

    protected function build(array $data): object
    {
        ['own' => $own] = $this->attrs($data);
        $pairs = [];
        foreach ($this->rawChildren($data, [KeyValuePair::class]) as $child) {
            if (!$child instanceof KeyValuePair) {
                throw new InvalidStructure(Tag::of('key-value') . ' can only contain ' . Tag::of('kv'));
            }
            $pairs[] = $child;
        }
        $list = KeyValueList::make(...$pairs);
        if (isset($own['label-width'])) {
            $raw = $own['label-width'];
            if ($raw instanceof Percent) {
                $width = $raw;
            } elseif ($raw instanceof Length) {
                try {
                    $width = $raw->absolute('label width');
                } catch (InvalidValue $e) {
                    throw $e->withContext('label-width')->within('on ' . Tag::of('key-value'));
                }
            } else {
                $text = trim($this->scalarOwn($raw, 'label-width'));
                try {
                    $width = str_ends_with($text, '%') ? Percent::parse($text) : Length::parse($text);
                } catch (InvalidValue $e) {
                    throw $e->withContext('label-width')->within('on ' . Tag::of('key-value'));
                }
            }
            $list = $list->labelWidth($width);
        }

        return $this->styled($list, $data);
    }
}
