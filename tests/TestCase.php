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
}
