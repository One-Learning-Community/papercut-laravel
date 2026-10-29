<?php

declare(strict_types=1);

namespace Papercut\Laravel\Components;

use Papercut\Laravel\Rendering\NodeRegistry;
use Papercut\Laravel\Tag;
use Papercut\Laravel\Exception\InvalidStructure;
use Papercut\TableCell;
use Papercut\TableRow;

final class Tr extends PapercutComponent
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
