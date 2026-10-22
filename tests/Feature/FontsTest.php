<?php

declare(strict_types=1);

namespace Papercut\Laravel\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Papercut\Laravel\Facades\Papercut;
use Papercut\Laravel\Tests\TestCase;

final class FontsTest extends TestCase
{
    private const string INTER = __DIR__ . '/../../../core/resources/fonts/Inter';

    /**
     * @param array<string, mixed> $fonts
     * @param list<string> $fallback
     */
    private function configure(array $fonts, array $fallback = []): void
    {
        config(['papercut.fonts' => $fonts, 'papercut.fallback_fonts' => $fallback]);
    }

    public function testCommandListsInterAndConfiguredFamilies(): void
    {
        $this->configure(['Brand' => [400 => ['normal' => self::INTER . '/Inter-Regular.ttf', 'italic' => self::INTER . '/Inter-Italic.ttf'], 700 => ['normal' => self::INTER . '/Inter-Bold.ttf']]]);

        $code = Artisan::call('papercut:fonts');
        $out = Artisan::output();

        $this->assertSame(0, $code);
        $this->assertSame(4, substr_count($out, 'Inter '), $out);
        $this->assertSame(3, substr_count($out, 'Brand'), $out);
        $this->assertStringNotContainsString('missing', $out);
        $this->assertStringNotContainsString('invalid', $out);
        $this->assertStringContainsString('ok', $out);
    }

    public function testMissingPathFailsWithExitOne(): void
    {
        $this->configure(['Brand' => [400 => ['normal' => '/nope/Brand.ttf']]]);

        $code = Artisan::call('papercut:fonts');

        $this->assertSame(1, $code);
        $this->assertStringContainsString('missing', Artisan::output());
    }

    public function testInvalidFontFileFailsWithExitOne(): void
    {
        $bad = tempnam(sys_get_temp_dir(), 'pcfont');
        $this->assertIsString($bad);
        file_put_contents($bad, 'not a font');
        $this->configure(['Brand' => [400 => ['normal' => $bad]]]);

        $code = Artisan::call('papercut:fonts');
        unlink($bad);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('invalid', Artisan::output());
    }

    public function testRelativePathsResolveAgainstBasePath(): void
    {
        copy(self::INTER . '/Inter-Regular.ttf', base_path('pc-relative.ttf'));
        $this->configure(['Brand' => [400 => ['normal' => 'pc-relative.ttf']]]);

        try {
            $this->assertSame(0, Artisan::call('papercut:fonts'), Artisan::output());
        } finally {
            unlink(base_path('pc-relative.ttf'));
        }
    }

    public function testOptionsRegisterConfiguredFonts(): void
    {
        $this->configure(['Brand' => [400 => ['normal' => self::INTER . '/Inter-Regular.ttf']]]);

        $options = Papercut::pdfOptions();

        $this->assertTrue(\Papercut\Text\FontRegistry::fromSet($options->fonts(), $options->backend())->has('Brand'));
    }

    public function testOptionsForwardFallbackFonts(): void
    {
        $this->configure([], ['Brand']);

        $this->assertSame(['Brand'], Papercut::pdfOptions()->fonts()->fallbacks);
    }

    /** @return iterable<string, array{array<mixed>, string}> */
    public static function badFonts(): iterable
    {
        $p = '/p.ttf';
        yield 'non-numeric weight' => [['Brand' => ['bold' => ['normal' => $p]]], 'papercut.fonts.Brand.bold: weight'];
        yield 'weight out of range' => [['Brand' => [0 => ['normal' => $p]]], 'papercut.fonts.Brand.0: weight'];
        yield 'bad style key' => [['Brand' => [700 => ['bold' => $p]]], 'papercut.fonts.Brand.700.bold: style must be "normal" or "italic"'];
        yield 'flat path' => [['Brand' => [400 => $p]], 'papercut.fonts.Brand.400: expected'];
        yield 'non-string path' => [['Brand' => [400 => ['normal' => 12]]], 'papercut.fonts.Brand.400.normal: path'];
        yield 'empty path' => [['Brand' => [400 => ['normal' => '']]], 'papercut.fonts.Brand.400.normal: path'];
        yield 'family not array' => [['Brand' => $p], 'papercut.fonts.Brand: expected'];
    }

    /** @param array<mixed> $fonts */
    #[\PHPUnit\Framework\Attributes\DataProvider('badFonts')]
    public function testMalformedFontConfigThrows(array $fonts, string $message): void
    {
        $this->configure($fonts);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid font config ' . $message);

        Papercut::pdfOptions();
    }

    public function testBadFallbackEntryThrows(): void
    {
        config(['papercut.fallback_fonts' => ['Brand', '']]);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('papercut.fallback_fonts.1');

        Papercut::pdfOptions();
    }

    public function testCommandWarnsAboutUnregisteredFallback(): void
    {
        $this->configure([], ['Ghost']);

        Artisan::call('papercut:fonts');

        $out = Artisan::output();
        $this->assertStringContainsString('Ghost', $out);
        $this->assertStringContainsString('not registered', $out);
    }
}
