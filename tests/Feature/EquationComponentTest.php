<?php

declare(strict_types=1);

namespace Quoin\Laravel\Tests\Feature;

use Quoin\Laravel\Exception\InvalidStructure;
use Quoin\Laravel\Facades\Quoin;
use Quoin\Laravel\QuoinManager;
use Quoin\Laravel\Tests\TestCase;
use Quoin\Math\Math;
use Quoin\{Equation, Text};

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
        app()->forgetInstance(QuoinManager::class);
        Quoin::clearResolvedInstances();
    }

    public function test_math_font_config_applied(): void
    {
        self::assertFalse(Quoin::pdfOptions()->fonts()->hasCustomMathFont());

        $path = sys_get_temp_dir() . '/quoin-math-font-' . uniqid() . '.ttf';
        copy(\Quoin\Math\Math::stixTwoMathPath(), $path);
        try {
            config(['quoin.math_font' => $path]);
            $this->fresh();

            $fonts = Quoin::pdfOptions()->fonts();
            self::assertTrue($fonts->hasCustomMathFont());
            self::assertSame($path, $fonts->mathFontPath());
        } finally {
            unlink($path);
        }
    }

    public function test_cff_math_font_config_applied(): void
    {
        $fira = __DIR__ . '/../../vendor/quoin/math/tests/fixtures/FiraMath-Regular.otf';
        if (!is_file($fira)) {
            self::markTestSkipped('the math package test fixtures are not installed');
        }
        config(['quoin.math_font' => $fira]);
        $this->fresh();

        self::assertSame($fira, Quoin::pdfOptions()->fonts()->mathFontPath());
        $pdf = \Quoin\Document::make(\Quoin\Page::a4()->body(\Quoin\Equation::of(\Quoin\Math\Math::tex('\\sqrt{x^2 + 1}'))))->toPdf(Quoin::pdfOptions())->bytes();
        self::assertStringContainsString('/FontFile3', $pdf); // the CFF font program
    }

    public function test_bad_math_font_config_message(): void
    {
        config(['quoin.math_font' => self::INTER]);
        $this->fresh();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/quoin\.math_font.*has no MATH table/');
        Quoin::pdfOptions();
    }

    public function test_missing_math_font_config_message(): void
    {
        config(['quoin.math_font' => '/no/such/font.ttf']);
        $this->fresh();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('quoin.math_font');
        Quoin::pdfOptions();
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
        $file = sys_get_temp_dir() . '/quoin-garbage-' . uniqid() . '.ttf';
        file_put_contents($file, 'this is not a font..');
        try {
            config(['quoin.math_font' => $file]);
            $this->fresh();
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage('quoin.math_font');
            Quoin::pdfOptions();
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
        $this->expectException(\Quoin\Exception\InvalidValue::class);
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
        $dir = base_path('quoin-math-font-test-' . uniqid());
        mkdir($dir);
        copy(\Quoin\Math\Math::stixTwoMathPath(), $dir . '/m.ttf');
        try {
            config(['quoin.math_font' => basename($dir) . '/m.ttf']);
            $this->fresh();
            self::assertSame($dir . '/m.ttf', Quoin::pdfOptions()->fonts()->mathFontPath());
        } finally {
            unlink($dir . '/m.ttf');
            rmdir($dir);
        }
    }

    private const string MML_FRAC = '<math><mfrac><mi>a</mi><mi>b</mi></mfrac></math>';

    public function test_equation_with_mathml_string(): void
    {
        $n = $this->nodeFrom('<x-doc::equation :math="$m" />', ['m' => self::MML_FRAC]);

        self::assertInstanceOf(Equation::class, $n);
        self::assertTrue($this->containsFraction($n->math()));
        self::assertEquals(Math::mathml(self::MML_FRAC), $n->math());
    }

    public function test_inline_math_with_mathml_string_stays_inline_even_with_display_block(): void
    {
        $mml = '<math display="block"><mfrac><mi>a</mi><mi>b</mi></mfrac></math>';
        $t = $this->nodeFrom('<x-doc::p>Ratio <x-doc::math :math="$m" /> here.</x-doc::p>', ['m' => $mml]);

        self::assertInstanceOf(Text::class, $t);
        $spans = $t->spanList();
        self::assertCount(3, $spans);
        self::assertNotNull($spans[1]->math);
        self::assertTrue($this->containsFraction($spans[1]->math));
    }

    public function test_safexml_string(): void
    {
        $safe = '«math»«mfrac»«mi»a«/mi»«mi»b«/mi»«/mfrac»«/math»';
        $n = $this->nodeFrom('<x-doc::equation :math="$m" />', ['m' => $safe]);

        self::assertTrue($this->containsFraction($n->math()));
    }

    public function test_prefixed_mathml_string(): void
    {
        $mml = '<mml:math xmlns:mml="http://www.w3.org/1998/Math/MathML"><mml:mfrac><mml:mi>a</mml:mi><mml:mi>b</mml:mi></mml:mfrac></mml:math>';
        $n = $this->nodeFrom('<x-doc::equation :math="$m" />', ['m' => "  \n" . $mml]);

        self::assertTrue($this->containsFraction($n->math()));
    }

    public function test_plain_string_still_tokenized(): void
    {
        self::assertEquals(Equation::of('x² + 1')->math(), $this->nodeFrom('<x-doc::equation math="x² + 1" />')->math());
        self::assertEquals(Equation::of('math is fun')->math(), $this->nodeFrom('<x-doc::equation math="math is fun" />')->math());
    }

    public function test_mtable_note_reported_once(): void
    {
        $mml = '<math><mtable rowlines="solid"><mtr><mtd><mi>a</mi></mtd></mtr></mtable></math>';
        $n = $this->nodeFrom('<x-doc::equation :math="$m" />', ['m' => $mml]);
        $warnings = [];
        $options = new \Quoin\Export\PdfOptions(onWarning: static function (string $msg) use (&$warnings): void {
            $warnings[] = $msg;
        });
        \Quoin\Document::make(\Quoin\Page::named('a4')->body($n))->toPdf($options);

        self::assertCount(1, $warnings);
    }

    public function test_mtable_note_reported_once_through_a_view_export(): void
    {
        $spy = \Illuminate\Support\Facades\Log::spy();
        $mml = '<math><mtable rowlines="solid"><mtr><mtd><mi>a</mi></mtd></mtr></mtable></math>';
        $pdf = Quoin::view('pdf.mathml', ['m' => $mml])->pdf()->toString();

        self::assertStringStartsWith('%PDF-', $pdf);
        $spy->shouldHaveReceived('debug')->withArgs(fn (string $m): bool => str_contains($m, 'math <mtable>'))->once();
    }

    public function test_literal_mathml_attribute_keeps_its_entities(): void
    {
        $n = $this->nodeFrom('<x-doc::equation math="<math><mtext>x &lt;b&gt; &amp; y</mtext></math>" />');

        self::assertEquals(Math::mathml('<math><mtext>x &lt;b&gt; &amp; y</mtext></math>'), $n->math());
        self::assertSame('x <b> & y', \Quoin\Math\Render\TextWriter::write($n->math()));
        self::assertSame([], Math::notes($n->math()));
    }

    public function test_literal_plain_attribute_still_decodes_entities(): void
    {
        self::assertEquals(Equation::of('a < b & c')->math(), $this->nodeFrom('<x-doc::equation math="a &lt; b &amp; c" />')->math());
    }

    public function test_tex_attribute_matches_the_node_built_by_hand(): void
    {
        $n = $this->nodeFrom('<x-doc::equation tex="\frac{a}{b}" />');

        self::assertInstanceOf(Equation::class, $n);
        self::assertTrue($this->containsFraction($n->math()));
        self::assertEquals(Math::tex('\frac{a}{b}'), $n->math());
        self::assertSame(
            \Quoin\Math\Render\TextWriter::write(Math::frac('a', 'b')),
            \Quoin\Math\Render\TextWriter::write($n->math()),
        );
    }

    public function test_tex_attribute_passes_ampersands_through_to_the_reader(): void
    {
        $n = $this->nodeFrom('<x-doc::equation tex="\begin{aligned}x&=1\end{aligned}" />');

        self::assertEquals(Math::tex('\begin{aligned}x&=1\end{aligned}'), $n->math());
        self::assertTrue($this->containsGrid($n->math()));
    }

    public function test_tex_attribute_on_inline_math(): void
    {
        $t = $this->nodeFrom('<x-doc::p>Ratio <x-doc::math tex="\frac{a}{b}" /> here.</x-doc::p>');

        self::assertInstanceOf(Text::class, $t);
        $math = $t->spanList()[1]->math;
        self::assertNotNull($math);
        self::assertTrue($this->containsFraction($math));
    }

    public function test_bound_tex_attribute_decodes_blade_escaping_of_its_ampersand(): void
    {
        $tex = '\begin{aligned}x&=1\end{aligned}';
        $n = $this->nodeFrom('<x-doc::equation :tex="$t" />', ['t' => $tex]);

        self::assertEquals(Math::tex($tex), $n->math());
    }

    public function test_double_braces_need_a_bound_tex_attribute(): void
    {
        $n = $this->nodeFrom('<x-doc::equation :tex="\'\\\\frac{{a}}{b}\'" />');

        self::assertEquals(Math::tex('\frac{{a}}{b}'), $n->math());
    }

    public function test_tex_and_math_together_are_rejected(): void
    {
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('takes either math or tex, not both');
        $this->nodeFrom('<x-doc::equation math="x" tex="x" />');
    }

    public function test_neither_math_nor_tex_is_rejected_naming_both(): void
    {
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('or a tex attribute');
        $this->nodeFrom('<x-doc::equation />');
    }

    public function test_a_bound_tex_attribute_that_is_not_a_string_is_rejected(): void
    {
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('needs the tex attribute to be a string of TeX');
        $this->nodeFrom('<x-doc::equation :tex="$t" />', ['t' => 5]);
    }

    public function test_static_tex_attribute_decodes_html_entities(): void
    {
        $n = $this->nodeFrom('<x-doc::equation tex="a &lt; b &amp; c" />');

        self::assertEquals(Math::tex('a < b & c'), $n->math());
    }

    private function containsGrid(\Quoin\Math\Node\Node $node): bool
    {
        if ($node instanceof \Quoin\Math\Node\Grid) {
            return true;
        }
        foreach ($node->children() as $c) {
            if ($this->containsGrid($c)) {
                return true;
            }
        }

        return false;
    }

    private function containsFraction(\Quoin\Math\Node\Node $node): bool
    {
        if ($node instanceof \Quoin\Math\Node\Fraction) {
            return true;
        }
        foreach ($node->children() as $c) {
            if ($this->containsFraction($c)) {
                return true;
            }
        }

        return false;
    }
}
