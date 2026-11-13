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

    public function testTocPageNumbersConfig(): void
    {
        $doc = Document::make(Page::a4()->body(\Papercut\Toc::make(), Text::h1('Alpha')));
        $passes = [self::layoutPasses()];
        Papercut::make($doc)->docx()->toString();
        $passes[] = self::layoutPasses();

        config(['papercut.docx.toc_page_numbers' => false]);
        app()->forgetInstance(PapercutManager::class);
        Papercut::clearResolvedInstances();
        Papercut::make($doc)->docx()->toString();
        $passes[] = self::layoutPasses();
        $this->assertSame([$passes[0], $passes[0] + 1, $passes[0] + 1], $passes);
    }

    private static function layoutPasses(): int
    {
        return \Papercut\Docx\DocxExporter::$layoutPasses;
    }

    public function testBadTocPageNumbersConfig(): void
    {
        config(['papercut.docx.toc_page_numbers' => 'no']);
        app()->forgetInstance(PapercutManager::class);
        Papercut::clearResolvedInstances();
        $this->expectExceptionMessage("Invalid papercut.docx.toc_page_numbers: 'no'; expected true or false");
        Papercut::make(Document::make(Page::a4()->body(Text::make('x'))))->docx()->toString();
    }

    public function testRsvgConvertConfigReachesTheOptions(): void
    {
        config(['papercut.docx.rsvg_convert' => '/usr/local/bin/rsvg-convert']);
        app()->forgetInstance(PapercutManager::class);
        $options = app(PapercutManager::class)->docxOptions();
        $this->assertInstanceOf(\Papercut\Docx\DocxOptions::class, $options);
        $this->assertSame('/usr/local/bin/rsvg-convert', $options->rsvgConvert);

        config(['papercut.docx.rsvg_convert' => '']);
        app()->forgetInstance(PapercutManager::class);
        $options = app(PapercutManager::class)->docxOptions();
        $this->assertInstanceOf(\Papercut\Docx\DocxOptions::class, $options);
        $this->assertNull($options->rsvgConvert);
    }

    public function testBadRsvgConvertConfig(): void
    {
        config(['papercut.docx.rsvg_convert' => true]);
        app()->forgetInstance(PapercutManager::class);
        Papercut::clearResolvedInstances();
        $this->expectExceptionMessage('Invalid papercut.docx.rsvg_convert: true; expected null or the path to rsvg-convert');
        Papercut::make(Document::make(Page::a4()->body(Text::make('x'))))->docx()->toString();
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
    public function testTextTagIndentationCollapsesInWord(): void
    {
        $node = $this->nodeFrom("<x-doc::text>\n    plain\n    <b>bold</b>\n    end\n</x-doc::text>");
        $this->assertInstanceOf(Text::class, $node);
        $this->assertSame('plain bold end', self::wordText($node));

        $blade = "<x-doc::text>\n    <x-doc::badge>New</x-doc::badge>\n    @if (\$show)\n        <span>tail</span>\n    @endif\n</x-doc::text>";
        $node = $this->nodeFrom($blade, ['show' => true]);
        $this->assertInstanceOf(Text::class, $node);
        $this->assertSame("\u{00A0}\u{00A0}New\u{00A0}\u{00A0} tail", self::wordText($node));
    }

    /** The paragraph text of a one-node document's Word export. */
    private static function wordText(\Papercut\Node $node): string
    {
        $path = tempnam(sys_get_temp_dir(), 'docx');
        file_put_contents($path, Papercut::make(Document::make(Page::a4()->body($node)))->docx()->toString());
        $zip = new \ZipArchive();
        $zip->open($path);
        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();
        unlink($path);
        preg_match_all('~<w:t(?: [^>]*)?>([^<]*)</w:t>~', $xml, $m);

        return html_entity_decode(implode('', $m[1]), ENT_XML1);
    }
}
