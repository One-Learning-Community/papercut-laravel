<?php

declare(strict_types=1);

namespace Papercut\Laravel\Components;

use Papercut\Laravel\Tag;
use Papercut\Exception\InvalidValue;
use Papercut\Laravel\Exception\InvalidStructure;

final class PageNumber extends PapercutComponent
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

        return $this->textStyled(\Papercut\PageNumber::make($format), $data);
    }
}
