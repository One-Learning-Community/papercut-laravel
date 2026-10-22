<?php

declare(strict_types=1);

namespace Papercut\Laravel\Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Papercut\Export\FontMode;
use Papercut\Html\{FontSource, GoogleFonts, LocalFonts};
use Papercut\Laravel\Facades\Papercut;
use Papercut\Laravel\PapercutManager;
use Papercut\Laravel\Tests\Support\{CdnFontUrl, RegistryFonts};
use Papercut\Laravel\Tests\TestCase;

final class HtmlFontSourcesTest extends TestCase
{
    private const string INTER = __DIR__ . '/../../../core/resources/fonts/Inter';

    private function page(): string
    {
        $this->app()->forgetInstance(PapercutManager::class); // @phpstan-ignore method.notFound

        return Papercut::view('pdf.hello', ['name' => 'Ann'])->html()->page()->toString();
    }

    /** @param array<string, mixed> $html */
    private function html(array $html): void
    {
        foreach ($html as $key => $value) {
            config(["papercut.html.$key" => $value]);
        }
    }

    private function acme(): void
    {
        config(['papercut.fonts' => ['Acme' => [400 => ['normal' => self::INTER . '/Inter-Regular.ttf']]], 'papercut.theme' => ['font_family' => 'Acme'], 'papercut.fallback_fonts' => ['Inter']]);
    }

    public function test_sources_with_defaults_links_google(): void
    {
        $this->html(['fonts' => 'sources']);

        $this->assertStringContainsString('fonts.googleapis.com/css2?family=Inter', $this->page());
    }

    public function test_fragment_imports_first_and_has_no_link(): void
    {
        $this->html(['fonts' => 'sources']);
        $this->app()->forgetInstance(PapercutManager::class); // @phpstan-ignore method.notFound

        $out = (string) Papercut::view('pdf.hello', ['name' => 'Ann'])->html();

        $this->assertStringContainsString('<style>@import url("https://fonts.googleapis.com/css2?family=Inter', $out);
        $this->assertStringNotContainsString('<link', $out);
    }

    public function test_bunny_host_from_config(): void
    {
        $this->html(['fonts' => 'sources', 'google_fonts' => ['host' => 'https://fonts.bunny.net']]);

        $out = $this->page();
        $this->assertStringContainsString('fonts.bunny.net/css2?family=Inter', $out);
        $this->assertStringNotContainsString('fonts.googleapis.com', $out);
    }

    public function test_local_then_google(): void
    {
        $this->acme();
        $this->html(['fonts' => 'sources', 'font_sources' => [LocalFonts::class, GoogleFonts::class], 'local_fonts' => ['families' => ['Acme']]]);

        $out = $this->page();
        $this->assertStringContainsString("font-family:'Acme'", str_replace('"', "'", $out));
        $this->assertStringContainsString('data:font/', $out);
        $this->assertStringContainsString('fonts.googleapis.com/css2?family=Inter', $out);
        $this->assertStringNotContainsString('family=Acme', $out);
    }

    public function test_local_link_uses_font_url(): void
    {
        $this->acme();
        $this->html(['fonts' => 'sources', 'font_sources' => [LocalFonts::class], 'font_url' => CdnFontUrl::class, 'local_fonts' => ['mode' => 'link']]);

        $out = $this->page();
        $this->assertStringContainsString('https://cdn.test/fonts/400.ttf', $out);
        $this->assertStringNotContainsString('data:font/', $out);
    }

    public function test_custom_source_class(): void
    {
        $this->acme();
        $this->html(['fonts' => 'sources', 'font_sources' => [RegistryFonts::class, GoogleFonts::class]]);

        $out = $this->page();
        $this->assertStringContainsString('https://fonts.example.test/Acme.css', $out);
        $this->assertStringContainsString('fonts.googleapis.com/css2?family=Inter', $out);
    }

    /** @return iterable<string, array{array<string, mixed>, non-empty-string}> */
    public static function errors(): iterable
    {
        $s = ['fonts' => 'sources'];
        yield 'fonts' => [['fonts' => 'bogus'], "Invalid papercut.html.fonts: 'bogus'; expected none, embed, link or sources"];
        yield 'font_sources scalar' => [$s + ['font_sources' => 'x'], "Invalid papercut.html.font_sources: 'x'; expected a list of class names implementing Papercut\\Html\\FontSource"];
        yield 'font_sources map' => [$s + ['font_sources' => ['a' => GoogleFonts::class]], 'Invalid papercut.html.font_sources: array; expected a list of class names implementing Papercut\\Html\\FontSource'];
        yield 'font_sources empty' => [$s + ['font_sources' => []], 'papercut.html.fonts is sources, which needs at least one class in papercut.html.font_sources'];
        yield 'entry not a source' => [$s + ['font_sources' => [GoogleFonts::class, \stdClass::class]], "Invalid papercut.html.font_sources.1: 'stdClass'; expected an invokable class name implementing Papercut\\Html\\FontSource"];
        yield 'entry int' => [$s + ['font_sources' => [5]], "Invalid papercut.html.font_sources.0: 5; expected an invokable class name implementing Papercut\\Html\\FontSource"];
        yield 'entry abstract' => [$s + ['font_sources' => [FontSource::class]], "Invalid papercut.html.font_sources.0: 'Papercut\\\\Html\\\\FontSource'; expected an invokable class name implementing Papercut\\Html\\FontSource"];
        yield 'entry unbuildable' => [$s + ['font_sources' => [\Papercut\Laravel\Tests\Support\NeedsScalarSource::class]], "Cannot build papercut.html.font_sources.0 resolver 'Papercut\\\\Laravel\\\\Tests\\\\Support\\\\NeedsScalarSource': "];
        yield 'host' => [$s + ['google_fonts' => ['host' => 'ftp://x']], "Invalid papercut.html.google_fonts.host: 'ftp://x'; expected an http or https URL with no query or fragment"];
        yield 'host trailing space' => [$s + ['google_fonts' => ['host' => 'https://fonts.bunny.net ']], "Invalid papercut.html.google_fonts.host: 'https://fonts.bunny.net '; expected an http or https URL with no query or fragment"];
        yield 'host array' => [$s + ['google_fonts' => ['host' => []]], 'Invalid papercut.html.google_fonts.host: array; expected an http or https URL with no query or fragment'];
        yield 'display' => [$s + ['google_fonts' => ['display' => 'fast']], "Invalid papercut.html.google_fonts.display: 'fast'; expected auto, block, swap, fallback or optional"];
        yield 'google families' => [$s + ['google_fonts' => ['families' => 'Inter']], "Invalid papercut.html.google_fonts.families: 'Inter'; expected null or a list of family names"];
        yield 'google families item' => [$s + ['google_fonts' => ['families' => ['Inter', 5]]], 'Invalid papercut.html.google_fonts.families: array; expected null or a list of family names'];
        yield 'local families' => [$s + ['font_sources' => [LocalFonts::class], 'local_fonts' => ['families' => 'x']], "Invalid papercut.html.local_fonts.families: 'x'; expected null or a list of family names"];
        yield 'local mode' => [$s + ['font_sources' => [LocalFonts::class], 'local_fonts' => ['mode' => 'x']], "Invalid papercut.html.local_fonts.mode: 'x'; expected embed or link"];
        yield 'local link needs font_url' => [$s + ['font_sources' => [LocalFonts::class], 'local_fonts' => ['mode' => 'link']], 'papercut.html.local_fonts.mode is link, which needs papercut.html.font_url'];
    }

    /** @param array<string, mixed> $html
     * @param non-empty-string $message
     */
    #[DataProvider('errors')]
    public function test_errors_name_their_keys(array $html, string $message): void
    {
        $this->html($html);

        try {
            $this->page();
            $this->fail('Expected an InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringStartsWith($message, $e->getMessage());
        }
    }

    public function test_errors_do_not_happen_at_boot(): void
    {
        $this->html(['fonts' => 'sources', 'google_fonts' => ['display' => 'fast']]);

        $this->assertStringStartsWith('%PDF', Papercut::view('pdf.hello', ['name' => 'Ann'])->pdf()->toString());
    }

    public function test_none_mode_ignores_source_config(): void
    {
        $this->html(['fonts' => 'none', 'font_sources' => 'x', 'google_fonts' => ['display' => 'fast'], 'local_fonts' => ['mode' => 'x']]);

        $this->assertStringContainsString('Hello Ann', $this->page());
    }

    public function test_unused_blocks_are_not_checked(): void
    {
        $this->html(['fonts' => 'sources', 'local_fonts' => ['mode' => 'x'], 'google_fonts' => ['display' => 'swap']]);

        $this->assertStringContainsString('fonts.googleapis.com', $this->page());
    }

    public function test_closure_entry_rejected_with_index(): void
    {
        $this->html(['fonts' => 'sources', 'font_sources' => [GoogleFonts::class, fn () => null]]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid papercut.html.font_sources.1: Closure; expected an invokable class name implementing Papercut\Html\FontSource');
        $this->page();
    }

    public function test_config_with_class_names_survives_var_export(): void
    {
        $this->html(['fonts' => 'sources', 'font_sources' => [RegistryFonts::class, GoogleFonts::class], 'google_fonts' => ['host' => 'https://fonts.bunny.net']]);
        /** @var array<string, mixed> $html */
        $html = (array) config('papercut.html');
        $restored = eval('return ' . var_export($html, true) . ';');
        config(['papercut.html' => $restored]);

        $this->assertSame(FontMode::Sources, $this->app()->make(PapercutManager::class)->htmlOptions()->fontMode());
        $this->assertStringContainsString('fonts.bunny.net', $this->page());
    }
}
