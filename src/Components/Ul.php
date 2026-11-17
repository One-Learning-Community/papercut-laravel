<?php

declare(strict_types=1);

namespace Quoin\Laravel\Components;

/** @internal Engine detail, not public API. It may change in any release. */
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
