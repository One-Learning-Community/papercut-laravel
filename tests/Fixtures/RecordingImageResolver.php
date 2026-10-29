<?php

declare(strict_types=1);

namespace Papercut\Laravel\Tests\Fixtures;

use Papercut\Laravel\Markup\ImageResolver;

/** Base for resolvers that record how often they are built and which sources they are asked for. */
abstract class RecordingImageResolver implements ImageResolver
{
    public static int $built = 0;

    /** @var list<string> "Class:src" for every call */
    public static array $calls = [];

    public function __construct()
    {
        static::$built++;
    }

    public static function reset(): void
    {
        self::$built = 0;
        self::$calls = [];
    }

    public function __invoke(string $src): ?string
    {
        self::$calls[] = static::class . ':' . $src;

        $im = imagecreatetruecolor(40, 20) ?: throw new \RuntimeException('gd failure');
        ob_start();
        imagepng($im);

        return (string) ob_get_clean();
    }
}
