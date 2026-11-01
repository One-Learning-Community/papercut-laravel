<?php

declare(strict_types=1);

namespace Papercut\Laravel\Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Papercut\Document;
use Papercut\Exception\{InvalidValue, UnknownAttribute};
use Papercut\Laravel\Exception\InvalidStructure;
use Papercut\Laravel\PapercutManager;
use Papercut\Laravel\Rendering\ThemeScope;
use Papercut\Laravel\Tests\TestCase;
use Papercut\Style\ThemeOverride;

use function Papercut\pt;

final class ThemeOverrideTest extends TestCase
{
    private function document(string $blade): Document
    {
        $doc = $this->nodeFrom($blade);
        self::assertInstanceOf(Document::class, $doc);

        return $doc;
    }

    private function pdfOf(Document $doc): string
    {
        return $this->app()->make(PapercutManager::class)->make($doc)->pdf()->toString();
    }

    public function test_document_theme_attribute_changes_the_pdf(): void
    {
        $plain = $this->document('<x-doc::document><x-doc::page>Hello</x-doc::page></x-doc::document>');
        $big = $this->document('<x-doc::document :theme="[\'font_size\' => 14, \'color\' => \'#336699\']"><x-doc::page>Hello</x-doc::page></x-doc::document>');

        self::assertNull($plain->themeOverride());
        $override = $big->themeOverride();
        self::assertInstanceOf(ThemeOverride::class, $override);
        self::assertSame(14.0, $override->fontSize?->points);
        self::assertNotNull($override->color);

        $manager = $this->app()->make(PapercutManager::class);
        $a = $manager->make($plain)->pdf()->toString();
        $b = $manager->make($big)->pdf()->toString();
        self::assertStringContainsString('Hello', $this->pdfText($b));
        self::assertNotSame($a, $b);
    }

    public function test_page_theme_attribute_applies_to_that_page(): void
    {
        $doc = $this->document('<x-doc::document><x-doc::page>One</x-doc::page><x-doc::page :theme="[\'line_height\' => 2]">Two</x-doc::page></x-doc::document>');

        self::assertNull($doc->pages()[0]->themeOverride());
        self::assertSame(2.0, $doc->pages()[1]->themeOverride()?->lineHeight);
    }

    public function test_controller_theme_wins_over_the_views_document_theme(): void
    {
        $doc = $this->document('<x-doc::document :theme="[\'font_size\' => 14, \'line_height\' => 2]"><x-doc::page>Hi</x-doc::page></x-doc::document>');
        $pending = $this->app()->make(PapercutManager::class)->make($doc)->theme(['font_size' => 20]);

        $override = $pending->document()->themeOverride();
        self::assertSame(20.0, $override?->fontSize?->points);
        self::assertSame(2.0, $override->lineHeight);
        self::assertSame(14.0, $doc->themeOverride()?->fontSize?->points, 'the original is untouched');
        self::assertStringContainsString('Hi', $this->pdfText($pending->pdf()->toString()));
    }

    public function test_a_style_defined_in_the_page_theme_passes_the_early_check(): void
    {
        $doc = $this->document(<<<'BLADE'
            <x-doc::document>
                <x-doc::page :theme="['styles' => ['stamp' => ['bold' => true, 'color' => '#aa0000']]]">
                    <x-doc::text style="stamp">Stamped</x-doc::text>
                </x-doc::page>
            </x-doc::document>
            BLADE);

        self::assertArrayHasKey('stamp', $doc->pages()[0]->themeOverride()->styles ?? []);
        self::assertStringContainsString('Stamped', $this->pdfText($this->app()->make(PapercutManager::class)->make($doc)->pdf()->toString()));
    }

    public function test_a_style_defined_in_the_document_theme_passes_for_every_page(): void
    {
        $this->document(<<<'BLADE'
            <x-doc::document :theme="['styles' => ['stamp' => ['bold' => true]]]">
                <x-doc::page><x-doc::text style="stamp">A</x-doc::text></x-doc::page>
                <x-doc::page><x-doc::text style="stamp">B</x-doc::text></x-doc::page>
            </x-doc::document>
            BLADE);
        $this->addToAssertionCount(1);
    }

    public function test_a_style_defined_only_in_a_sibling_page_still_fails_early(): void
    {
        $this->expectException(UnknownAttribute::class);
        $this->expectExceptionMessage('stamp');
        $this->document(<<<'BLADE'
            <x-doc::document>
                <x-doc::page :theme="['styles' => ['stamp' => ['bold' => true]]]"><x-doc::text style="stamp">A</x-doc::text></x-doc::page>
                <x-doc::page><x-doc::text style="stamp">B</x-doc::text></x-doc::page>
            </x-doc::document>
            BLADE);
    }

    public function test_the_scope_is_empty_after_a_render_that_threw(): void
    {
        try {
            $this->document('<x-doc::document><x-doc::page :theme="[\'font_size\' => 14]"><x-doc::text style="nope">A</x-doc::text></x-doc::page></x-doc::document>');
            self::fail('expected the unknown style to throw');
        } catch (UnknownAttribute) {
        }

        $scope = $this->app()->make(ThemeScope::class);
        self::assertSame($this->app()->make(PapercutManager::class)->pdfOptions()->theme(), $scope->current());
    }

    public function test_bad_theme_values_name_their_source(): void
    {
        try {
            $this->document('<x-doc::document><x-doc::page :theme="[\'color\' => \'not-a-color\']">A</x-doc::page></x-doc::document>');
            self::fail('accepted a bad colour');
        } catch (InvalidValue $e) {
            self::assertStringContainsString('<x-doc::page>', $e->getMessage());
        }

        try {
            $this->document('<x-doc::document :theme="[\'styles\' => [\'x\' => [\'no-such\' => 1]]]"><x-doc::page>A</x-doc::page></x-doc::document>');
            self::fail('accepted a bad style attribute');
        } catch (UnknownAttribute $e) {
            self::assertStringContainsString('<x-doc::document>', $e->getMessage());
        }

        $pending = $this->app()->make(PapercutManager::class)->make($this->document('<x-doc::document><x-doc::page>A</x-doc::page></x-doc::document>'));
        try {
            $pending->theme(['font_family' => 'Nonesuch']);
            self::fail('accepted an unregistered family');
        } catch (\InvalidArgumentException $e) {
            self::assertStringContainsString('->theme()', $e->getMessage());
            self::assertStringContainsString('Nonesuch', $e->getMessage());
        }
    }

    public function test_unknown_keys_name_the_key_and_the_allowed_ones(): void
    {
        $pending = $this->app()->make(PapercutManager::class)->make($this->document('<x-doc::document><x-doc::page>A</x-doc::page></x-doc::document>'));
        try {
            $pending->theme(['font_sise' => 12]);
            self::fail('accepted an unknown key');
        } catch (\InvalidArgumentException $e) {
            self::assertStringContainsString('font_sise', $e->getMessage());
            self::assertStringContainsString('font_size', $e->getMessage());
            self::assertStringContainsString('->theme()', $e->getMessage());
        }
    }

    public function test_an_empty_theme_changes_nothing(): void
    {
        $manager = $this->app()->make(PapercutManager::class);
        $plain = $manager->make($this->document('<x-doc::document><x-doc::page>Same</x-doc::page></x-doc::document>'));
        $empty = $manager->make($this->document('<x-doc::document :theme="[]"><x-doc::page :theme="[]">Same</x-doc::page></x-doc::document>'))->theme([]);

        self::assertSame($plain->pdf()->toString(), $empty->pdf()->toString());
    }

    public function test_config_theme_still_validates_as_before(): void
    {
        config(['papercut.theme' => ['font_size' => -1]]);
        $this->expectExceptionMessage('Invalid papercut.theme.font_size:');
        $this->app()->make(PapercutManager::class)->pdfOptions();
    }

    /** @return iterable<string, array{string, string}> */
    public static function badConfigKeys(): iterable
    {
        yield 'unknown' => ['font_sise', "Unknown key papercut.theme.font_sise; allowed keys: font_family, mono_family, font_size, color, line_height"];
        yield 'code_theme' => ['code_theme', 'Unknown key papercut.theme.code_theme; set the code theme with papercut.code.theme'];
        yield 'styles' => ['styles', 'Unknown key papercut.theme.styles; define named styles in papercut.styles'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('badConfigKeys')]
    public function test_config_theme_rejects_keys_it_does_not_use(string $key, string $message): void
    {
        config(['papercut.theme' => [$key => 'x']]);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($message);
        $this->app()->make(PapercutManager::class)->pdfOptions();
    }

    public function test_a_page_theme_styles_that_pages_text_in_the_pdf(): void
    {
        $manager = $this->app()->make(PapercutManager::class);
        $with = $manager->make($this->document(<<<'BLADE'
            <x-doc::document>
                <x-doc::page :theme="['styles' => ['stamp' => ['font-size' => '30pt', 'bold' => true]]]"><x-doc::text style="stamp">Stamped</x-doc::text></x-doc::page>
            </x-doc::document>
            BLADE))->pdf()->toString();
        $plain = $manager->make($this->document('<x-doc::document><x-doc::page><x-doc::text>Stamped</x-doc::text></x-doc::page></x-doc::document>'))->pdf()->toString();

        self::assertStringContainsString('Stamped', $this->pdfText($with));
        self::assertNotSame($plain, $with);
    }

    public function test_a_style_attribute_error_from_theme_names_the_call(): void
    {
        $pending = $this->app()->make(PapercutManager::class)->make($this->document('<x-doc::document><x-doc::page>A</x-doc::page></x-doc::document>'));
        try {
            $pending->theme(['styles' => ['x' => ['no-such' => 1]]]);
            self::fail('accepted a bad style attribute');
        } catch (UnknownAttribute $e) {
            self::assertStringContainsString('->theme()', $e->getMessage());
            self::assertStringContainsString('x', $e->getMessage());
        }
    }

    public function test_a_theme_attribute_must_be_an_array(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('<x-doc::page>');
        $this->document('<x-doc::document><x-doc::page theme="loud">A</x-doc::page></x-doc::document>');
    }

    public function test_a_variant_can_come_from_a_page_theme_style(): void
    {
        $doc = $this->document(<<<'BLADE'
            <x-doc::document>
                <x-doc::page :theme="['styles' => ['card.loud' => ['bold' => true]]]"><x-doc::card variant="loud">A</x-doc::card></x-doc::page>
            </x-doc::document>
            BLADE);
        self::assertNotNull($doc->pages()[0]->themeOverride());

        $this->expectException(InvalidStructure::class);
        $this->document('<x-doc::document><x-doc::page><x-doc::card variant="loud">A</x-doc::card></x-doc::page></x-doc::document>');
    }

    public function test_a_page_theme_reaches_content_passed_through_a_wrapper_component(): void
    {
        $this->wrapper('wrap', '<x-doc::page :theme="[\'styles\' => [\'stamp\' => [\'bold\' => true]]]">{{ $slot }}</x-doc::page>');
        $doc = $this->document('<x-doc::document><x-wrap><x-doc::text style="stamp">A</x-doc::text></x-wrap></x-doc::document>');

        self::assertInstanceOf(Document::class, $doc);
        $plain = $this->document('<x-doc::document><x-doc::page><x-doc::text>A</x-doc::text></x-doc::page></x-doc::document>');
        self::assertNotNull($doc->pages()[0]->themeOverride());
        self::assertArrayHasKey('stamp', $doc->pages()[0]->themeOverride()->styles);
        self::assertNotSame($this->pdfOf($plain), $this->pdfOf($doc));
    }

    public function test_a_document_theme_reaches_content_passed_through_a_layout_component(): void
    {
        $this->wrapper('layout', '<x-doc::document :theme="[\'styles\' => [\'stamp\' => [\'bold\' => true]]]">{{ $slot }}</x-doc::document>');
        $doc = $this->document('<x-layout><x-doc::page><x-doc::text style="stamp">A</x-doc::text></x-doc::page></x-layout>');

        self::assertInstanceOf(Document::class, $doc);
        $plain = $this->document('<x-doc::document><x-doc::page><x-doc::text>A</x-doc::text></x-doc::page></x-doc::document>');
        self::assertArrayHasKey('stamp', $doc->themeOverride()->styles ?? []);
        self::assertNotSame($this->pdfOf($plain), $this->pdfOf($doc));
    }

    public function test_an_unknown_style_inside_a_wrapper_still_fails_naming_the_child(): void
    {
        $this->wrapper('wrap', '<x-doc::page :theme="[\'styles\' => [\'stamp\' => [\'bold\' => true]]]">{{ $slot }}</x-doc::page>');
        try {
            $this->document('<x-doc::document><x-wrap><x-doc::text style="nope">A</x-doc::text></x-wrap></x-doc::document>');
            self::fail('accepted an unknown style');
        } catch (UnknownAttribute $e) {
            self::assertStringContainsString('nope', $e->getMessage());
            self::assertStringContainsString('<x-doc::text>', $e->getMessage());
        }
    }

    public function test_a_failed_render_leaves_no_override_for_the_next_one(): void
    {
        try {
            Blade::render('<x-doc::page :theme="[\'styles\' => [\'leak\' => [\'bold\' => true]]]"><x-doc::text>A</x-doc::text></x-doc::page>');
        } catch (\Throwable) {
            // rendered outside Papercut::view(), so the page never closes
        }

        $this->expectException(UnknownAttribute::class);
        $this->document('<x-doc::document><x-doc::page><x-doc::text style="leak">B</x-doc::text></x-doc::page></x-doc::document>');
    }

    private function wrapper(string $name, string $blade): void
    {
        $dir = $this->tempDir('papercut-components');
        mkdir("$dir/components");
        file_put_contents("$dir/components/$name.blade.php", $blade);
        $this->app()->make('view')->addLocation($dir);
    }
}
