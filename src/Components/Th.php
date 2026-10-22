<?php

declare(strict_types=1);

namespace Papercut\Laravel\Components;

final class Th extends Td
{
    protected function build(array $data): object
    {
        return $this->cell($data, true);
    }
}
