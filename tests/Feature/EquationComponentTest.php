<?php

declare(strict_types=1);

namespace Papercut\Laravel\Tests\Feature;

use Papercut\Laravel\Exception\InvalidStructure;
use Papercut\Laravel\Facades\Papercut;
use Papercut\Laravel\PapercutManager;
use Papercut\Laravel\Tests\TestCase;
use Papercut\Math\Math;
use Papercut\{Equation, Text};

final class EquationComponentTest extends TestCase
{
    private const string INTER = __DIR__ . '/../../../core/resources/fonts/Inter/Inter-Regular.ttf';

    public function test_block_component_renders_equation(): void
    {
        $node = Math::frac('a', 'b');
        $n = $this->nodeFrom('<x-doc::equation :math="$m" text-align="center" />', ['m' => $node]);

        self::assertInstanceOf(Equation::class, $n);
        self::assertSame($node, $n->math());
        self::assertEquals(Equation::of('x = 1')->math(), $this->nodeFrom('<x-doc::equation math="x = 1" />')->math());
    }

    public function test_inline_component_in_paragraph_is_span_math(): void
    {
        $t = $this->nodeFrom('<x-doc::p>Area is <x-doc::math math="x²" /> here.</x-doc::p>');

        self::assertInstanceOf(Text::class, $t);
        $spans = $t->spanList();
        self::assertCount(3, $spans);
        self::assertEquals(Math::row('x²'), $spans[1]->math);
        self::assertSame('Area is ', $spans[0]->text);
    }

    public function test_math_outside_text_is_a_block_equation(): void
    {
        self::assertInstanceOf(Equation::class, $this->nodeFrom('<x-doc::math :math="$m" />', ['m' => Math::frac('a', 'b')]));
    }

    public function test_content_is_rejected(): void
    {
        $this->expectException(InvalidStructure::class);
        $this->nodeFrom('<x-doc::equation math="x">y</x-doc::equation>');
    }

    public function test_math_is_required(): void
    {
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('requires a math');
        $this->nodeFrom('<x-doc::equation />');
    }

    private function fresh(): void
    {
        app()->forgetInstance(PapercutManager::class);
        Papercut::clearResolvedInstances();
    }

    public function test_math_font_config_applied(): void
    {
        self::assertFalse(Papercut::pdfOptions()->fonts()->hasCustomMathFont());

        $path = sys_get_temp_dir() . '/papercut-math-font-' . uniqid() . '.ttf';
        copy(\Papercut\Math\Math::stixTwoMathPath(), $path);
        try {
            config(['papercut.math_font' => $path]);
            $this->fresh();

            $fonts = Papercut::pdfOptions()->fonts();
            self::assertTrue($fonts->hasCustomMathFont());
            self::assertSame($path, $fonts->mathFontPath());
        } finally {
            unlink($path);
        }
    }

    public function test_bad_math_font_config_message(): void
    {
        config(['papercut.math_font' => self::INTER]);
        $this->fresh();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/papercut\.math_font.*has no MATH table/');
        Papercut::pdfOptions();
    }

    public function test_missing_math_font_config_message(): void
    {
        config(['papercut.math_font' => '/no/such/font.ttf']);
        $this->fresh();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('papercut.math_font');
        Papercut::pdfOptions();
    }

    public function test_block_equation_inside_text_is_rejected(): void
    {
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('can only contain text and inline tags');
        $this->nodeFrom('<x-doc::p>a <x-doc::equation math="x" /></x-doc::p>');
    }

    public function test_inline_math_with_style_attributes_is_rejected(): void
    {
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('<x-doc::math> inside');
        $this->nodeFrom('<x-doc::p>a <x-doc::math math="x" color="red-500" /></x-doc::p>');
    }

    public function test_garbage_math_font_file_message(): void
    {
        $file = sys_get_temp_dir() . '/papercut-garbage-' . uniqid() . '.ttf';
        file_put_contents($file, 'this is not a font..');
        try {
            config(['papercut.math_font' => $file]);
            $this->fresh();
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage('papercut.math_font');
            Papercut::pdfOptions();
        } finally {
            unlink($file);
        }
    }

    public function test_inline_math_inside_inline_html_names_math_in_the_error(): void
    {
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('<x-doc::math> can\'t sit inside inline HTML tags');
        $this->nodeFrom('<x-doc::p>Area is <b>about <x-doc::math math="x" /></b> here.</x-doc::p>');
    }

    public function test_inline_math_inside_a_link_is_rejected_the_same_way(): void
    {
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('<x-doc::math> can\'t sit inside inline HTML tags');
        $this->nodeFrom('<x-doc::p><a href="https://example.test"><x-doc::math math="x" /></a></x-doc::p>');
    }

    public function test_an_unclosed_tag_with_no_math_keeps_its_own_message(): void
    {
        $this->expectException(\Papercut\Exception\InvalidValue::class);
        $this->expectExceptionMessage('is not closed');
        $this->nodeFrom('<x-doc::p>Area <b>is here.</x-doc::p>');
    }

    public function test_math_attribute_with_specials_builds_the_same_node_as_equation_of(): void
    {
        self::assertEquals(Equation::of('a < b & c')->math(), $this->nodeFrom('<x-doc::equation math="a < b & c" />')->math());
        self::assertEquals(Equation::of('a < b & c')->math(), $this->nodeFrom('<x-doc::equation :math="\'a < b & c\'" />')->math());
    }

    public function test_relative_math_font_resolves_against_base_path(): void
    {
        $dir = base_path('papercut-math-font-test-' . uniqid());
        mkdir($dir);
        copy(\Papercut\Math\Math::stixTwoMathPath(), $dir . '/m.ttf');
        try {
            config(['papercut.math_font' => basename($dir) . '/m.ttf']);
            $this->fresh();
            self::assertSame($dir . '/m.ttf', Papercut::pdfOptions()->fonts()->mathFontPath());
        } finally {
            unlink($dir . '/m.ttf');
            rmdir($dir);
        }
    }
}
