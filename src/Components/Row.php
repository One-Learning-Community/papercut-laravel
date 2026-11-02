<?php

declare(strict_types=1);

namespace Papercut\Laravel\Components;

/** @internal Part of Papercut's engine, not its public API. It may change in any release. */
final class Row extends PapercutComponent
{
    protected function aliases(): array
    {
        return ['align' => 'align-items'];
    }

    protected function build(array $data): object
    {
        return $this->styled(\Papercut\Row::make(...$this->children($data)), $data);
    }
}
