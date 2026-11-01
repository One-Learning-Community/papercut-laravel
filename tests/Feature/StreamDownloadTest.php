<?php

declare(strict_types=1);

namespace Papercut\Laravel\Tests\Feature;

use Illuminate\Support\Facades\Route;
use Papercut\Export\Output;
use Papercut\Laravel\Facades\Papercut;
use Papercut\Laravel\PendingOutput;
use Papercut\Laravel\Tests\TestCase;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class StreamDownloadTest extends TestCase
{
    public function test_stream_download_is_a_streamed_attachment_with_the_document(): void
    {
        $res = Papercut::view('pdf.hello', ['name' => 'A'])->pdf()->streamDownload('hello.pdf');

        self::assertInstanceOf(StreamedResponse::class, $res);
        self::assertSame('application/pdf', $res->headers->get('Content-Type'));
        self::assertStringContainsString('attachment; filename=hello.pdf', (string) $res->headers->get('Content-Disposition'));
        self::assertStringStartsWith('%PDF', self::streamed($res));
    }

    public function test_the_document_renders_only_when_the_response_is_sent(): void
    {
        $renders = 0;
        $pending = new PendingOutput(function () use (&$renders): Output {
            $renders++;

            return new Output('x', 'text/plain', 'txt');
        }, 'note', 'txt', 'text/plain');

        $res = $pending->streamDownload();
        self::assertSame(0, $renders);
        self::assertStringContainsString('attachment; filename=note.txt', (string) $res->headers->get('Content-Disposition'));
        self::assertSame('text/plain', $res->headers->get('Content-Type'));
        self::assertSame('x', self::streamed($res));
        self::assertSame(1, $renders);
    }

    public function test_names_are_cleaned_and_given_an_ascii_fallback_like_download(): void
    {
        $pending = Papercut::view('pdf.hello', ['name' => 'A'])->docx();
        $res = $pending->streamDownload('a/b Übersicht.docx');

        self::assertSame(
            $pending->download('a/b Übersicht.docx')->headers->get('Content-Disposition'),
            $res->headers->get('Content-Disposition'),
        );
        self::assertSame('application/vnd.openxmlformats-officedocument.wordprocessingml.document', $res->headers->get('Content-Type'));
    }

    public function test_html_pages_stream_too(): void
    {
        $res = Papercut::view('pdf.hello', ['name' => 'A'])->html()->page()->streamDownload();

        self::assertStringContainsString('filename=document.html', (string) $res->headers->get('Content-Disposition'));
        self::assertStringContainsString('<html', self::streamed($res));
    }

    public function test_a_route_can_return_it(): void
    {
        Route::get('/stream', fn () => Papercut::view('pdf.hello', ['name' => 'A'])->pdf()->streamDownload('report.pdf'));

        $this->get('/stream')->assertOk()->assertDownload('report.pdf');
    }

    private static function streamed(StreamedResponse $res): string
    {
        ob_start();
        $res->sendContent();

        return (string) ob_get_clean();
    }
}
