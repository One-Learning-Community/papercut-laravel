<?php

declare(strict_types=1);

namespace Papercut\Laravel\Components;

/** @internal Part of Papercut's engine, not its public API. It may change in any release. */
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
