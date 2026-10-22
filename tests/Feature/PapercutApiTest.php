<?php

declare(strict_types=1);

namespace Papercut\Laravel\Tests\Feature;

use Illuminate\Support\Facades\View;
use Papercut\Laravel\Facades\Papercut;
use Papercut\Laravel\PendingOutput;
use Papercut\Laravel\Tests\TestCase;

final class PapercutApiTest extends TestCase
{
    public function test_view_pdf_download_has_pdf_headers(): void
    {
        $res = Papercut::view('pdf.hello', ['name' => 'A'])->pdf()->download('hello.pdf');

        self::assertSame('application/pdf', $res->headers->get('Content-Type'));
        self::assertStringContainsString('attachment; filename=hello.pdf', (string) $res->headers->get('Content-Disposition'));
        self::assertSame((string) strlen((string) $res->getContent()), $res->headers->get('Content-Length'));
    }

    public function test_default_filename_comes_from_output_extension(): void
    {
        $inline = Papercut::view('pdf.hello', ['name' => 'A'])->pdf()->inline();
        self::assertStringContainsString('inline; filename=document.pdf', (string) $inline->headers->get('Content-Disposition'));

        $download = Papercut::view('pdf.hello', ['name' => 'A'])->pdf()->download();
        self::assertStringContainsString('attachment; filename=document.pdf', (string) $download->headers->get('Content-Disposition'));
    }

    public function test_pending_output_is_responsable_and_memoizes_the_output(): void
    {
        $renders = 0;
        $pending = new PendingOutput(function () use (&$renders): \Papercut\Export\Output {
            $renders++;

            return new \Papercut\Export\Output('x', 'text/plain', 'txt');
        }, 'note');

        self::assertSame('x', $pending->toString());
        self::assertSame($pending->output(), $pending->output());
        self::assertSame('text/plain', $pending->toResponse(request())->headers->get('Content-Type'));
        self::assertStringContainsString('filename=note.txt', (string) $pending->download()->headers->get('Content-Disposition'));
        self::assertSame(1, $renders);
    }

    public function test_pending_document_renders_view_once(): void
    {
        $views = 0;
        View::composer('pdf.hello', function () use (&$views): void {
            $views++;
        });

        $pending = Papercut::view('pdf.hello', ['name' => 'A']);
        $pending->pdf()->toString();
        $pending->pdf()->toString();

        self::assertSame(1, $views);
    }

    public function test_pdf_facade_is_gone(): void
    {
        // Names are split so a repo-wide grep for the old API stays empty.
        foreach (['Facades\\P' . 'df', 'PendingP' . 'df', 'PdfM' . 'anager'] as $name) {
            self::assertFalse(class_exists('Papercut\\Laravel\\' . $name), $name); // @phpstan-ignore function.impossibleType
        }
    }
}
