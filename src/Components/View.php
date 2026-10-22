<?php

declare(strict_types=1);

namespace Papercut\Laravel\Components;

final class View extends PapercutComponent
{
    protected function aliases(): array
    {
        return ['align' => 'align-items'];
    }

    protected function build(array $data): object
    {
        return $this->styled(\Papercut\View::make(...$this->children($data)), $data);
    }
}
