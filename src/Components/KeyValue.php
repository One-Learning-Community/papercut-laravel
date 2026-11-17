<?php

declare(strict_types=1);

namespace Quoin\Laravel\Components;

use Quoin\Laravel\Rendering\NodeRegistry;
use Quoin\Decorator\KeyValueList;
use Quoin\Decorator\KeyValuePair;
use Quoin\Exception\InvalidValue;
use Quoin\Laravel\Exception\InvalidStructure;
use Quoin\Laravel\Tag;
use Quoin\Value\Length;
use Quoin\Value\Percent;

/** @internal Engine detail, not public API. It may change in any release. */
final class KeyValue extends QuoinComponent
{
    /** @return list<string> */
    protected function ownAttributes(): array
    {
        return ['label-width', 'variant'];
    }

    protected function build(array $data): object
    {
        ['own' => $own] = $this->attrs($data);
        $pairs = [];
        foreach ($this->rawChildren($data, [KeyValuePair::class]) as $child) {
            if (!$child instanceof KeyValuePair) {
                throw new InvalidStructure(Tag::of('key-value') . ' can only contain ' . Tag::of('kv') . NodeRegistry::staleHint($child));
            }
            if ($child->total) {
                throw new InvalidStructure('<x-' . Tag::prefix() . '::kv total> is only for ' . Tag::of('totals') . '; rows in ' . Tag::of('key-value') . ' are all plain');
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
