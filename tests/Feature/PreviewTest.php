<?php

declare(strict_types=1);

namespace Quoin\Laravel\Tests\Feature;

use Orchestra\Testbench\Attributes\DefineEnvironment;
use Quoin\Laravel\Facades\Quoin;
use Quoin\Laravel\Tests\TestCase;

final class PreviewTest extends TestCase
{
    /** @param \Illuminate\Foundation\Application $app */
    protected function previewLocal($app): void
    {
        $app['config']->set('quoin.preview.enabled', true);
        $app['env'] = 'local';
    }

    /** @param \Illuminate\Foundation\Application $app */
    protected function previewTesting($app): void
    {
        $app['config']->set('quoin.preview.enabled', true);
    }

    /** @param \Illuminate\Foundation\Application $app */
    protected function previewLocalDisabled($app): void
    {
        $app['config']->set('quoin.preview.enabled', false);
        $app['env'] = 'local';
    }

    #[DefineEnvironment('previewLocal')]
    public function testIndexListsRegisteredViewsEscaped(): void
    {
        Quoin::preview('pdf.hello', ['name' => 'A']);
        Quoin::preview('pdf.<b>x</b>');

        $response = $this->get('/_quoin');

        $response->assertOk();
        $html = (string) $response->getContent();
        $this->assertStringContainsString('href="/_quoin/pdf.hello"', $html);
        $this->assertStringContainsString('pdf.hello', $html);
        $this->assertStringNotContainsString('<b>x</b>', $html);
        $this->assertStringContainsString('&lt;b&gt;x&lt;/b&gt;', $html);
    }

    #[DefineEnvironment('previewLocal')]
    public function testRendersRegisteredViewAsPdf(): void
    {
        Quoin::preview('pdf.hello', ['name' => 'World']);

        $response = $this->get('/_quoin/pdf.hello');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', (string) $response->baseResponse->getContent());
    }

    #[DefineEnvironment('previewLocal')]
    public function testDataClosureIsCalledPerRequest(): void
    {
        $calls = 0;
        Quoin::preview('pdf.hello', function () use (&$calls): array {
            $calls++;

            return ['name' => 'N' . $calls];
        });

        $this->get('/_quoin/pdf.hello')->assertOk();
        $this->get('/_quoin/pdf.hello')->assertOk();

        $this->assertSame(2, $calls);
    }

    #[DefineEnvironment('previewLocal')]
    public function testUnregisteredViewIs404(): void
    {
        $this->get('/_quoin/pdf.hello')->assertNotFound();
    }

    #[DefineEnvironment('previewTesting')]
    public function testNoRoutesOutsideLocal(): void
    {
        Quoin::preview('pdf.hello', ['name' => 'A']);

        $this->get('/_quoin')->assertNotFound();
        $this->get('/_quoin/pdf.hello')->assertNotFound();
    }

    #[DefineEnvironment('previewLocalDisabled')]
    public function testNoRoutesWhenDisabled(): void
    {
        Quoin::preview('pdf.hello', ['name' => 'A']);

        $this->get('/_quoin')->assertNotFound();
    }
}
