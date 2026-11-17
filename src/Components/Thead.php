<?php

declare(strict_types=1);

namespace Quoin\Laravel\Components;

use Quoin\Laravel\Rendering\NodeRegistry;
use Quoin\Laravel\Tag;
use Quoin\Laravel\Exception\InvalidStructure;
use Quoin\TableCell;
use Quoin\TableRow;

/** @internal Engine detail, not public API. It may change in any release. */
final class Thead extends QuoinComponent
{
    protected function build(array $data): object
    {
        $children = $this->rawChildren($data, [TableCell::class, TableRow::class]);
        $cells = array_filter($children, fn ($c) => $c instanceof TableCell);
        $rows = array_filter($children, fn ($c) => $c instanceof TableRow);
        if ($children === []) {
            throw new InvalidStructure(Tag::of('thead') . ' must not be empty');
        }
        if (count($cells) + count($rows) !== count($children) || ($cells !== [] && $rows !== [])) {
            throw new InvalidStructure(Tag::of('thead') . ' can only contain ' . Tag::of('th') . '/' . Tag::of('td') . ' cells or ' . Tag::of('tr') . ' rows, not both' . NodeRegistry::staleHint(array_values(array_filter($children, fn ($c) => !$c instanceof TableCell && !$c instanceof TableRow))[0] ?? null));
        }
        if ($cells !== []) {
            /** @var list<TableCell> $list */
            $list = array_values($cells);

            return new HeaderRows([TableRow::make(...$list)]);
        }
        /** @var list<TableRow> $list */
        $list = array_values($rows);

        return new HeaderRows($list);
    }
}
