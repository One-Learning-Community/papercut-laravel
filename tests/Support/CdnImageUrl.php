<?php

declare(strict_types=1);

namespace Papercut\Laravel\Tests\Support;

use Papercut\Image;
use Papercut\Laravel\Html\ImageUrlResolver;

final class CdnImageUrl implements ImageUrlResolver
{
    public function __construct(private readonly CdnHost $host)
    {
    }

    public function __invoke(Image $image): string
    {
        return $this->host->base . '/x.png';
    }
}
