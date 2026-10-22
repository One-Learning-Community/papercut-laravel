<?php

declare(strict_types=1);

namespace Papercut\Laravel\Tests\Support;

use Papercut\Laravel\Html\FontUrlResolver;
use Papercut\Text\FontFace;

final class CdnFontUrl implements FontUrlResolver
{
    public function __invoke(FontFace $face): string
    {
        return 'https://cdn.test/fonts/' . $face->weight . '.ttf';
    }
}
