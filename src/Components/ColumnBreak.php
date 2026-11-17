<?php

declare(strict_types=1);

namespace Quoin\Laravel\Components;

use Quoin\Laravel\Tag;
use Quoin\Exception\UnknownAttribute;

/** @internal Engine detail, not public API. It may change in any release. */
final class ColumnBreak extends QuoinComponent
{
    protected function build(array $data): object
    {
        $attrs = $this->attrs($data);
        foreach (array_keys([...$attrs['own'], ...$attrs['style']]) as $key) {
            throw UnknownAttribute::for((string) $key)->within('on ' . Tag::of('column-break'));
        }

        return \Quoin\ColumnBreak::make();
    }
}
