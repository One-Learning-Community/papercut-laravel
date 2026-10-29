<?php

declare(strict_types=1);

namespace Papercut\Laravel\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Illuminate\View\ViewException;
use Illuminate\Support\Facades\Blade;
use Papercut\Laravel\PapercutServiceProvider;
use Papercut\Laravel\Rendering\NodeRegistry;

abstract class TestCase extends Orchestra
{
    /**
     * @param \Illuminate\Foundation\Application $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [PapercutServiceProvider::class];
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('view.paths', [...(array) $app['config']->get('view.paths', []), __DIR__ . '/views']);
    }

    /** @var list<string> */
    private array $tempDirs = [];

    /** A fresh directory in the system temp dir, removed in tearDown. */
    protected function tempDir(string $prefix): string
    {
        $dir = sys_get_temp_dir() . '/' . $prefix . '-' . getmypid() . '-' . bin2hex(random_bytes(3));
        mkdir($dir);
        $this->tempDirs[] = $dir;

        return $dir;
    }

    protected function tearDown(): void
    {
        foreach ($this->tempDirs as $dir) {
            \Illuminate\Support\Facades\File::deleteDirectory($dir);
        }
        $this->tempDirs = [];
        parent::tearDown();
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function nodeFrom(string $blade, array $data = []): mixed
    {
        $registry = $this->app()->make(NodeRegistry::class);

        return $registry->scope(function () use ($registry, $blade, $data): object {
            try {
                $html = Blade::render($blade, $data);
            } catch (ViewException $e) { // @phpstan-ignore catch.neverThrown
                $inner = $e;
                while ($inner instanceof ViewException && $inner->getPrevious() !== null) {
                    $inner = $inner->getPrevious();
                }

                throw $inner;
            }
            $segments = $registry->segments($html);
            $nodes = [];
            foreach ($segments as $seg) {
                if (is_string($seg)) {
                    self::assertSame('', trim($seg), 'Unexpected text outside nodes: ' . $seg);
                } else {
                    $nodes[] = $seg;
                }
            }
            self::assertCount(1, $nodes);

            return $nodes[0];
        });
    }

    protected function app(): \Illuminate\Contracts\Foundation\Application
    {
        self::assertNotNull($this->app);

        return $this->app;
    }

    /** The text of a PDF as poppler's pdftotext reads it; skips the test when pdftotext is not installed. */
    protected function pdfText(string $pdf): string
    {
        $file = tempnam(sys_get_temp_dir(), 'papercut-text-');
        file_put_contents($file, $pdf);
        try {
            exec('pdftotext ' . escapeshellarg($file) . ' - 2>/dev/null', $out, $code);
            if ($code === 127) {
                self::markTestSkipped('pdftotext (poppler) is not installed.');
            }
            self::assertSame(0, $code, 'pdftotext failed');

            return implode("\n", $out);
        } finally {
            unlink($file);
        }
    }
}
