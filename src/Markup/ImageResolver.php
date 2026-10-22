<?php

declare(strict_types=1);

namespace Papercut\Laravel\Markup;

/**
 * Names an invokable class for `papercut.markup.images`. Turns an `<img src>` from markup into
 * image bytes, or returns null when the image is missing. Built by the container, so it can take dependencies.
 */
interface ImageResolver
{
    public function __invoke(string $src): ?string;
}
