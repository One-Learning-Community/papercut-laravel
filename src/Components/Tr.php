<?php

declare(strict_types=1);

namespace Quoin\Laravel\Components;

use Quoin\Laravel\Rendering\NodeRegistry;
use Quoin\Laravel\Tag;
use Quoin\Laravel\Exception\InvalidStructure;
use Quoin\TableCell;
use Quoin\TableRow;

/** @internal Engine detail, not public API. It may change in any release. */
final class Tr extends QuoinComponent
{
    protected function build(array $data): object
    {
        $cells = [];
        foreach ($this->rawChildren($data, [TableCell::class]) as $child) {
            if (!$child instanceof TableCell) {
                throw new InvalidStructure(Tag::of('tr') . ' can only contain ' . Tag::of('td') . ' or ' . Tag::of('th') . NodeRegistry::staleHint($child));
            }
            $cells[] = $child;
        }

        return $this->styled(TableRow::make(...$cells), $data);
    }
}
