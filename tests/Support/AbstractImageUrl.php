<?php

declare(strict_types=1);

namespace Quoin\Laravel\Tests\Support;

use Quoin\Image;
use Quoin\Laravel\Html\ImageUrlResolver;

abstract class AbstractImageUrl implements ImageUrlResolver
{
    abstract public function __invoke(Image $image): ?string;
}
