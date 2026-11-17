<?php

declare(strict_types=1);

namespace Quoin\Laravel\Components;

/** @internal Engine detail, not public API. It may change in any release. */
final class Th extends Td
{
    protected function build(array $data): object
    {
        return $this->cell($data, true);
    }
}
