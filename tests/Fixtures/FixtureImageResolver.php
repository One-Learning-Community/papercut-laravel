<?php

declare(strict_types=1);

namespace Quoin\Laravel\Tests\Fixtures;

use Quoin\Laravel\Markup\ImageResolver;

/** Returns a small PNG for 'a.png' and null for anything else. */
final class FixtureImageResolver implements ImageResolver
{
    public function __invoke(string $src): ?string
    {
        if ($src !== 'a.png') {
            return null;
        }
        $im = imagecreatetruecolor(40, 20) ?: throw new \RuntimeException('gd failure');
        imagefill($im, 0, 0, (int) imagecolorallocate($im, 30, 90, 200));
        ob_start();
        imagepng($im);

        return (string) ob_get_clean();
    }
}
