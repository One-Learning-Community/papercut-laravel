<?php

declare(strict_types=1);

namespace Quoin\Laravel\Tests\Support;

use Quoin\Image;
use Quoin\Laravel\Html\ImageUrlResolver;

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
