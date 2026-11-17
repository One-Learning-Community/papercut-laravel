<?php

declare(strict_types=1);

namespace Quoin\Laravel\Components;

use Quoin\TableRow;

/**
 * Internal value object returned by <x-doc::thead>; only <x-doc::table> accepts it.
 *
 * @internal Engine detail, not public API. It may change in any release.
 */
final readonly class HeaderRows
{
    /** @param list<TableRow> $rows */
    public function __construct(public array $rows)
    {
    }
}
