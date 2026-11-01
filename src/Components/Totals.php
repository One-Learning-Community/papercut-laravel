<?php

declare(strict_types=1);

namespace Papercut\Laravel\Components;

use Papercut\Decorator\KeyValuePair;
use Papercut\Laravel\Exception\InvalidStructure;
use Papercut\Laravel\Rendering\NodeRegistry;
use Papercut\Laravel\Tag;

/** `<x-doc::totals>` holding `<x-doc::kv>` rows; `<x-doc::kv total>` marks an emphasised row. */
final class Totals extends PapercutComponent
{
    /** @return list<string> */
    protected function ownAttributes(): array
    {
        return ['variant'];
    }

    protected function build(array $data): object
    {
        $pairs = [];
        foreach ($this->rawChildren($data, [KeyValuePair::class]) as $child) {
            if (!$child instanceof KeyValuePair) {
                throw new InvalidStructure(Tag::of('totals') . ' can only contain ' . Tag::of('kv') . NodeRegistry::staleHint($child));
            }
            $pairs[] = $child;
        }

        return $this->styled(\Papercut\Decorator\Totals::make(...$pairs), $data);
    }
}
