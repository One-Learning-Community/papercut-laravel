<?php

declare(strict_types=1);

namespace Quoin\Laravel\Components;

use Quoin\Laravel\Tag;
use Quoin\Laravel\Exception\InvalidStructure;

/** @internal Engine detail, not public API. It may change in any release. */
final class Link extends QuoinComponent
{
    protected function ownAttributes(): array
    {
        return ['href'];
    }

    protected function build(array $data): object
    {
        $href = $this->attrs($data)['own']['href'] ?? null;
        if ($href instanceof \Stringable) {
            $href = (string) $href;
        }
        if (!is_string($href) || $href === '') {
            throw new InvalidStructure(Tag::of('link') . ' href must be a non-empty string');
        }

        return $this->styled(\Quoin\Link::to($href, ...$this->children($data)), $data);
    }
}
