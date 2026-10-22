<?php

declare(strict_types=1);

namespace Papercut\Laravel\Components;

use Papercut\TableRow;

/** Internal value object returned by <x-doc::thead>; only <x-doc::table> accepts it. */
final readonly class HeaderRows
{
    /** @param list<TableRow> $rows */
    public function __construct(public array $rows)
    {
    }
}
