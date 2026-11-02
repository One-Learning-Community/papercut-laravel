<?php

declare(strict_types=1);

namespace Papercut\Laravel\Components;

/** @internal Part of Papercut's engine, not its public API. It may change in any release. */
final class Th extends Td
{
    protected function build(array $data): object
    {
        return $this->cell($data, true);
    }
}
