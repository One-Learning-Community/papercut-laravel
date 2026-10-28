<?php

declare(strict_types=1);

namespace Papercut\Laravel\Tests\Feature;

use Orchestra\Testbench\Attributes\DefineEnvironment;
use Papercut\{Column, Document, Page, Text};
use Papercut\Exception\{MissingExporter, UnsupportedFeature};
use Papercut\Laravel\Facades\Papercut;
use Papercut\Laravel\PapercutManager;
use Papercut\Laravel\Preview\PreviewRegistry;
use Papercut\Laravel\Rendering\NodeRegistry;
use Papercut\Laravel\Tests\TestCase;

use function Papercut\pt;

final class DocxTest extends TestCase
{
    private const string MIME = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

    public function testDownloadsAWordFile(): void
    {
        $response = Papercut::view('pdf.hello', ['name' => 'World'])->docx()->download('invoice.docx');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(self::MIME, $response->headers->get('Content-Type'));
        $this->assertSame('attachment; filename=invoice.docx', $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('PK', (string) $response->getContent());
    }

    public function testDefaultFilename(): void
    {
        $response = Papercut::view('pdf.hello', ['name' => 'World'])->docx()->download();

        $this->assertSame('attachment; filename=document.docx', $response->headers->get('Content-Disposition'));
    }

    public function testMissingPackageSaysHowToInstallIt(): void
    {
        $manager = new class (app(NodeRegistry::class), app(PreviewRegistry::class)) extends PapercutManager {
            protected static function docxExporterClass(): string
            {
                return 'Missing\\Exporter';
            }
        };

        $this->expectException(MissingExporter::class);
        $this->expectExceptionMessage('DOCX export needs papercut/docx: run "composer require papercut/docx".');
        $manager->make(Document::make(Page::a4()->body(Text::make('x'))))->docx()->toString();
    }

    /** @param \Illuminate\Foundation\Application $app */
    protected function strict($app): void
    {
        $app['config']->set('papercut.strict', true);
    }

    #[DefineEnvironment('strict')]
    public function testStrictModeAppliesToDocx(): void
    {
        $this->expectException(UnsupportedFeature::class);
        Papercut::make(Document::make(Page::a4()->body(Column::make(Text::make('x'))->padding(pt(4))->radius(pt(4)))))->docx()->toString();
    }

    /** @param \Illuminate\Foundation\Application $app */
    protected function previewLocal($app): void
    {
        $app['config']->set('papercut.preview.enabled', true);
        $app['env'] = 'local';
    }

    #[DefineEnvironment('previewLocal')]
    public function testPreviewsAsDocx(): void
    {
        Papercut::preview('pdf.hello', ['name' => 'World']);

        $response = $this->get('/_papercut/pdf.hello?format=docx');

        $response->assertOk();
        $response->assertHeader('Content-Type', self::MIME);
        $this->assertStringStartsWith('inline;', (string) $response->headers->get('Content-Disposition'));
        $this->get('/_papercut/pdf.hello?format=odt')->assertNotFound();
    }

    #[DefineEnvironment('previewLocal')]
    public function testDocxPreviewWithoutThePackageIsNotFound(): void
    {
        app()->instance(PapercutManager::class, new class (app(NodeRegistry::class), app(PreviewRegistry::class)) extends PapercutManager {
            protected static function docxExporterClass(): string
            {
                return 'Missing\\Exporter';
            }
        });
        Papercut::preview('pdf.hello', ['name' => 'World']);

        $response = $this->get('/_papercut/pdf.hello?format=docx');

        $response->assertNotFound();
        $response->assertSee('composer require papercut/docx');
    }
}
