<?php

declare(strict_types=1);

namespace Papercut\Laravel\Tests\Feature;

use Illuminate\Support\Facades\Route;
use Papercut\Laravel\Exception\InvalidStructure;
use Papercut\Laravel\Facades\Papercut;
use Papercut\Laravel\Tests\TestCase;

final class RenderingTest extends TestCase
{
    public function test_renders_view_to_pdf(): void
    {
        $pdf = Papercut::view('pdf.hello', ['name' => 'Ada'])->pdf()->toString();
        self::assertStringStartsWith('%PDF-', $pdf);
        $file = tempnam(sys_get_temp_dir(), 'pc');
        file_put_contents($file, $pdf);
        exec('pdftotext ' . escapeshellarg($file) . ' -', $out);
        unlink($file);
        self::assertStringContainsString('Ada', implode("\n", $out));
    }

    public function test_download_and_inline_responses(): void
    {
        Route::get('/dl', fn () => Papercut::view('pdf.hello', ['name' => 'A'])->pdf()->download('report.pdf'));
        Route::get('/in', fn () => Papercut::view('pdf.hello', ['name' => 'A'])->pdf());
        $this->get('/dl')->assertOk()->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'attachment; filename=report.pdf');
        $this->get('/in')->assertOk()->assertHeader('Content-Disposition', 'inline; filename=document.pdf');
    }

    public function test_root_validation(): void
    {
        foreach (['pdf.two-roots', 'pdf.no-root'] as $view) {
            try {
                Papercut::view($view)->pdf()->toString();
                self::fail("$view accepted");
            } catch (\Throwable $e) {
                $root = $e;
                while ($root->getPrevious() !== null && !$root instanceof InvalidStructure) { $root = $root->getPrevious(); }
                self::assertInstanceOf(InvalidStructure::class, $root);
                self::assertStringContainsString('exactly one <x-doc::document>', $root->getMessage());
            }
        }
    }

    public function test_attribute_error_names_blade_file(): void
    {
        try {
            Papercut::view('pdf.bad-attr')->pdf()->toString();
            self::fail('accepted');
        } catch (\Throwable $e) {
            self::assertStringContainsString('bad-attr.blade.php', $e->getMessage());
            self::assertStringContainsString('Invalid edges "20 mm" for margin on <x-doc::page>', $e->getMessage());
        }
    }

    public function test_implicit_page_and_two_renders_are_isolated(): void
    {
        $a = Papercut::view('pdf.implicit-page')->pdf()->toString();
        $b = Papercut::view('pdf.hello', ['name' => 'B'])->pdf()->toString();
        self::assertStringStartsWith('%PDF-', $a);
        self::assertStringStartsWith('%PDF-', $b);
    }

    public function test_named_styles_and_theme_from_config(): void
    {
        config(['papercut.styles' => ['card' => ['padding' => '12pt', 'background' => 'slate-50']], 'papercut.theme' => ['font_size' => 11.0]]);
        app()->forgetInstance(\Papercut\Laravel\PapercutManager::class);
        $theme = Papercut::pdfOptions()->theme();
        self::assertSame(11.0, $theme->fontSize);
        self::assertSame(12.0, $theme->named('card')->padding?->top->points);
    }
}
