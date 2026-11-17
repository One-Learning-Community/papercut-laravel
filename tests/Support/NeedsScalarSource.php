<?php

declare(strict_types=1);

namespace Quoin\Laravel\Tests\Support;

use Quoin\Html\{FontAnswer, FontRequest, FontSource};

/** A source the container cannot build: an unresolvable scalar constructor parameter. */
final class NeedsScalarSource implements FontSource
{
    public function __construct(public readonly string $token)
    {
    }

    public function __invoke(FontRequest $request): ?FontAnswer
    {
        return null;
    }
}
