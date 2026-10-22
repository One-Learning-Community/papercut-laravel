<?php

declare(strict_types=1);

namespace Papercut\Laravel\Tests\Support;

use Papercut\Image;
use Papercut\Laravel\Html\ImageUrlResolver;

abstract class AbstractImageUrl implements ImageUrlResolver
{
    abstract public function __invoke(Image $image): ?string;
}
