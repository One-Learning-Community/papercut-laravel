<?php

declare(strict_types=1);

namespace Quoin\Laravel\Components;

use Quoin\Laravel\Tag;
use Quoin\Exception\InvalidValue;
use Quoin\Laravel\Exception\InvalidStructure;

/** @internal Engine detail, not public API. It may change in any release. */
final class PageNumber extends QuoinComponent
{
    protected function ownAttributes(): array
    {
        return ['format', 'align', 'size'];
    }

    protected function build(array $data): object
    {
        if (trim($this->text($data)) !== '') {
            throw new InvalidStructure(Tag::of('page-number') . ' must not have content; use the format attribute');
        }
        $format = $this->attrs($data)['own']['format'] ?? '{page}';
        if (!is_string($format)) {
            throw InvalidValue::for('format', is_scalar($format) ? (string) $format : get_debug_type($format))->withContext('format')->within('on ' . Tag::of('page-number'));
        }

        return $this->textStyled(\Quoin\PageNumber::make($format), $data);
    }
}
