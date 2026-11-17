<?php

declare(strict_types=1);

namespace Quoin\Laravel\Components;

/** @internal Engine detail, not public API. It may change in any release. */
final class View extends QuoinComponent
{
    protected function aliases(): array
    {
        return ['align' => 'align-items'];
    }

    protected function build(array $data): object
    {
        return $this->styled(\Quoin\View::make(...$this->children($data)), $data);
    }
}
