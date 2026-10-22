<?php

declare(strict_types=1);

namespace Papercut\Laravel\Tests\Fixtures;

use Papercut\Laravel\Markup\ImageResolver;

/** A resolver the container cannot build: an unresolvable scalar constructor parameter. */
final class UnbuildableImageResolver implements ImageResolver
{
    public function __construct(public readonly string $token)
    {
    }

    public function __invoke(string $src): ?string
    {
        return null;
    }
}
