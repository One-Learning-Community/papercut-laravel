<?php

declare(strict_types=1);

namespace Papercut\Laravel\Tests\Feature;

use Illuminate\Support\Facades\Log;
use Papercut\Export\{Fidelity, FidelityLevel, FidelityReport};
use Papercut\Laravel\Facades\Papercut;
use Papercut\Laravel\PapercutManager;
use Papercut\Laravel\Tests\TestCase;
use Papercut\OverflowMode;
use Papercut\Text\FontStyle;

final class OptionsFromConfigTest extends TestCase
{
    private const string INTER = __DIR__ . '/../../../core/resources/fonts/Inter';

    private function fresh(): PapercutManager
    {
        app()->forgetInstance(PapercutManager::class);
        Papercut::clearResolvedInstances();

        return app()->make(PapercutManager::class);
    }

    public function test_overflow_null_is_throw_in_testing_and_clip_in_production(): void
    {
        config(['papercut.overflow' => null]);
        self::assertSame(OverflowMode::Throw, $this->fresh()->pdfOptions()->overflow());

        app()->instance('env', 'production');
        self::assertSame(OverflowMode::Clip, $this->fresh()->pdfOptions()->overflow());
    }

    public function test_cache_dir_false_and_string_pass_through(): void
    {
        config(['papercut.cache_dir' => false]);
        self::assertFalse($this->fresh()->pdfOptions()->cacheDir());

        config(['papercut.cache_dir' => '/tmp/pc-x']);
        self::assertSame('/tmp/pc-x', $this->fresh()->pdfOptions()->cacheDir());

        config(['papercut.cache_dir' => 5]);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid papercut.cache_dir');
        $this->fresh()->pdfOptions();
    }

    public function test_strict_config_reaches_options_and_rejects_non_bool(): void
    {
        self::assertFalse($this->fresh()->pdfOptions()->strict());

        config(['papercut.strict' => true]);
        self::assertTrue($this->fresh()->pdfOptions()->strict());

        config(['papercut.strict' => 'yes']);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid papercut.strict: 'yes'; expected true or false");
        $this->fresh()->pdfOptions();
    }

    public function test_fonts_and_fallbacks_from_config_land_in_font_set(): void
    {
        config([
            'papercut.fonts' => ['Brand' => [700 => ['italic' => self::INTER . '/Inter-BoldItalic.ttf']]],
            'papercut.fallback_fonts' => ['Brand'],
        ]);

        $fonts = $this->fresh()->pdfOptions()->fonts();

        self::assertCount(1, $fonts->faces);
        self::assertSame('Brand', $fonts->faces[0]->family);
        self::assertSame(700, $fonts->faces[0]->weight);
        self::assertSame(FontStyle::Italic, $fonts->faces[0]->style);
        self::assertSame(['Brand'], $fonts->fallbacks);
    }

    public function test_options_are_memoized(): void
    {
        $manager = $this->fresh();

        self::assertSame($manager->pdfOptions(), $manager->pdfOptions());
    }

    public function test_warnings_log_approximations_at_debug_and_the_rest_as_warnings(): void
    {
        $spy = Log::spy();
        $cb = $this->fresh()->pdfOptions()->onWarning();
        self::assertNotNull($cb);

        $cb('plain');
        $cb('close', new FidelityReport(FidelityLevel::Approximated, 'pdf', 'x', 'p'));
        $cb('lost', new FidelityReport(FidelityLevel::Dropped, 'pdf', 'x', 'p'));

        $spy->shouldHaveReceived('warning')->with('[papercut] plain')->once();
        $spy->shouldHaveReceived('debug')->with('[papercut] close')->once();
        $spy->shouldHaveReceived('warning')->with('[papercut] lost')->once();
    }

    public function test_markup_highlight_and_code_theme_reach_the_options(): void
    {
        self::assertSame($this->fresh()->markupOptions()->code, app(PapercutManager::class)->codeHighlighting());

        config(['papercut.markup.highlight' => false, 'papercut.code.theme' => 'github-dark']);
        $m = $this->fresh();
        self::assertFalse($m->markupOptions()->code);
        self::assertSame('github-dark', $m->codeHighlighting()->theme);

        config(['papercut.markup.highlight' => 'no']);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid papercut.markup.highlight: 'no'");
        $this->fresh()->markupOptions();
    }
}
