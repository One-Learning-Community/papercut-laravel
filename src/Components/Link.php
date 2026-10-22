<?php

declare(strict_types=1);

namespace Papercut\Laravel\Components;

use Papercut\Laravel\Tag;
use Papercut\Laravel\Exception\InvalidStructure;

final class Link extends PapercutComponent
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

        return $this->styled(\Papercut\Link::to($href, ...$this->children($data)), $data);
    }
}
