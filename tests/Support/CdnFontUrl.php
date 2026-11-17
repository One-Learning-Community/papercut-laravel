<?php

declare(strict_types=1);

namespace Quoin\Laravel\Tests\Support;

use Quoin\Laravel\Html\FontUrlResolver;
use Quoin\Text\FontFace;

final class CdnFontUrl implements FontUrlResolver
{
    public function __invoke(FontFace $face): string
    {
        return 'https://cdn.test/fonts/' . $face->weight . '.ttf';
    }
}
