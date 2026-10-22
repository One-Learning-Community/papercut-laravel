<?php

declare(strict_types=1);

namespace Papercut\Laravel\Components;

final class Ul extends Ol
{
    protected function ownAttributes(): array
    {
        return ['marker'];
    }

    protected function ordered(): bool
    {
        return false;
    }
}
