<?php

declare(strict_types=1);

namespace Papercut\Laravel\Components;

use Papercut\Laravel\Tag;
use Papercut\Exception\UnknownAttribute;

/** @internal Part of Papercut's engine, not its public API. It may change in any release. */
final class PageBreak extends PapercutComponent
{
    protected function build(array $data): object
    {
        $attrs = $this->attrs($data);
        foreach (array_keys([...$attrs['own'], ...$attrs['style']]) as $key) {
            throw UnknownAttribute::for((string) $key)->within('on ' . Tag::of('page-break'));
        }

        return \Papercut\PageBreak::make();
    }
}
