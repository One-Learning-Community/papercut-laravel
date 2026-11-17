<?php

declare(strict_types=1);

namespace Quoin\Laravel\Components;

use Quoin\Decorator\KeyValuePair;
use Quoin\Laravel\Exception\InvalidStructure;
use Quoin\Laravel\Rendering\NodeRegistry;
use Quoin\Laravel\Tag;

/**
 * `<x-doc::totals>` holding `<x-doc::kv>` rows; `<x-doc::kv total>` marks an emphasized row.
 *
 * @internal Engine detail, not public API. It may change in any release.
 */
final class Totals extends QuoinComponent
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

        return $this->styled(\Quoin\Decorator\Totals::make(...$pairs), $data);
    }
}
