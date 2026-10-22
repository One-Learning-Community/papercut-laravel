<?php

declare(strict_types=1);

namespace Papercut\Laravel\Components;

use Papercut\Laravel\Tag;
use Papercut\Exception\InvalidValue;
use Papercut\Style\TextAlign;
use Papercut\TableCell;

class Td extends PapercutComponent
{
    protected function ownAttributes(): array
    {
        return ['colspan', 'align'];
    }

    /** @param array<string, mixed> $data */
    protected function cell(array $data, bool $header): TableCell
    {
        ['own' => $own] = $this->attrs($data);
        $children = $this->children($data);
        $cell = $header ? TableCell::th(...$children) : TableCell::make(...$children);
        $where = 'on ' . Tag::of($this->tag());

        if (isset($own['colspan'])) {
            $raw = $own['colspan'];
            $n = is_int($raw) ? $raw : (is_string($raw) && ctype_digit($raw) ? (int) $raw : 0);
            if ($n < 1) {
                throw InvalidValue::for('colspan', is_scalar($raw) ? (string) $raw : get_debug_type($raw))->because('expected an integer of at least 1')->withContext('colspan')->within($where);
            }
            $cell = $cell->colspan($n);
        }
        if (isset($own['align'])) {
            $align = TextAlign::tryFrom((string) $own['align'])
                ?? throw InvalidValue::for('align', (string) $own['align'])->because('expected left, right, center or justify')->withContext('align')->within($where);
            $cell = $cell->align($align);
        }

        return $this->styled($cell, $data);
    }

    protected function build(array $data): object
    {
        return $this->cell($data, false);
    }
}
