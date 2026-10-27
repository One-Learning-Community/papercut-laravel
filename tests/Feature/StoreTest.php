<?php

declare(strict_types=1);

namespace Papercut\Laravel\Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Papercut\Laravel\Facades\Papercut;
use Papercut\Laravel\Tests\TestCase;

final class StoreTest extends TestCase
{
    private function pending(): \Papercut\Laravel\PendingDocument
    {
        return Papercut::make(\Papercut\Document::make(\Papercut\Page::a4()->body(\Papercut\Text::make('Stored'))));
    }

    public function test_store_writes_pdf_to_the_default_disk(): void
    {
        Storage::fake();
        $pdf = $this->pending()->pdf();

        $this->assertSame($pdf, $pdf->store('invoices/142.pdf'));
        Storage::assertExists('invoices/142.pdf');
        $this->assertSame($pdf->toString(), Storage::get('invoices/142.pdf'));
        $this->assertStringStartsWith('%PDF-', Storage::get('invoices/142.pdf'));
    }

    public function test_store_writes_to_a_named_disk(): void
    {
        $s3 = Storage::fake('s3');
        $local = Storage::fake('local');

        $this->pending()->pdf()->store('out/doc.pdf', 's3');

        $s3->assertExists('out/doc.pdf');
        $local->assertMissing('out/doc.pdf');
    }

    public function test_store_passes_visibility_to_the_disk(): void
    {
        Storage::fake('s3');

        $this->pending()->pdf()->store('public.pdf', 's3', 'public');

        $this->assertSame('public', Storage::disk('s3')->getVisibility('public.pdf'));
    }

    public function test_store_works_for_html(): void
    {
        $disk = Storage::fake();

        $this->pending()->html()->page()->store('doc.html');

        $this->assertStringContainsString('Stored', (string) $disk->get('doc.html'));
    }

    public function test_a_failed_write_throws(): void
    {
        $disk = \Mockery::mock(\Illuminate\Contracts\Filesystem\Filesystem::class);
        $disk->shouldReceive('put')->once()->andReturn(false);
        Storage::shouldReceive('disk')->with('broken')->andReturn($disk);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Could not write 'x.pdf' to the 'broken' disk");

        $this->pending()->pdf()->store('x.pdf', 'broken');
    }
}
