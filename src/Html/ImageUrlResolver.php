<?php

declare(strict_types=1);

namespace Papercut\Laravel\Html;

use Papercut\Image;

/**
 * Names an invokable class for `papercut.html.image_url`. Return a URL for the image,
 * or null to embed it as a data URI. Built by the container, so it can take dependencies.
 */
interface ImageUrlResolver
{
    public function __invoke(Image $image): ?string;
}
