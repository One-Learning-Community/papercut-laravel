<?php

declare(strict_types=1);

namespace Papercut\Laravel\Tests\Feature;

use Papercut\Exception\{InvalidValue, UnknownAttribute};
use Papercut\{ColumnBreak, View};
use Papercut\Laravel\Facades\Papercut;
use Papercut\Laravel\Tests\TestCase;

final class ColumnFlowComponentTest extends TestCase
{
    private function flow(string $blade): View
    {
        $node = $this->nodeFrom($blade);
        self::assertInstanceOf(View::class, $node);

        return $node;
    }

    public function test_column_flow_tag_builds_a_flow(): void
    {
        $spec = $this->flow('<x-doc::column-flow columns="3" column-gap="12pt" rule><x-doc::text>A</x-doc::text></x-doc::column-flow>')->columnSpec();

        self::assertNotNull($spec);
        self::assertSame(3, $spec->count);
        self::assertNull($spec->shares);
        self::assertSame([12.0, 12.0], $spec->gaps);
        self::assertNotNull($spec->rule);
        self::assertSame(0.5, $spec->rule->width);
    }

    public function test_defaults_to_two_columns_with_the_default_gap(): void
    {
        $spec = $this->flow('<x-doc::column-flow><x-doc::text>A</x-doc::text></x-doc::column-flow>')->columnSpec();

        self::assertNotNull($spec);
        self::assertSame(2, $spec->count);
        self::assertSame([36.0], $spec->gaps);
        self::assertNull($spec->rule);
    }

    public function test_widths_attribute_sets_shares(): void
    {
        $spec = $this->flow('<x-doc::column-flow widths="2 1"><x-doc::text>A</x-doc::text></x-doc::column-flow>')->columnSpec();

        self::assertNotNull($spec);
        self::assertSame(2, $spec->count);
        self::assertSame([2.0, 1.0], $spec->shares);
    }

    public function test_widths_must_match_columns(): void
    {
        $this->expectException(InvalidValue::class);
        $this->flow('<x-doc::column-flow columns="3" widths="2 1"><x-doc::text>A</x-doc::text></x-doc::column-flow>');
    }

    public function test_several_gaps_one_per_space(): void
    {
        $spec = $this->flow('<x-doc::column-flow columns="3" column-gap="6pt 18pt"><x-doc::text>A</x-doc::text></x-doc::column-flow>')->columnSpec();

        self::assertNotNull($spec);
        self::assertSame([6.0, 18.0], $spec->gaps);
    }

    public function test_gap_reaches_the_style_and_column_gap_sets_the_column_gap(): void
    {
        $view = $this->flow('<x-doc::column-flow gap="12pt" column-gap="20pt" columns="2"><x-doc::text>A</x-doc::text></x-doc::column-flow>');

        self::assertNotNull($view->style()->gap);
        self::assertSame(12.0, $view->style()->gap->absolute('gap')->points);
        self::assertNotNull($view->columnSpec());
        self::assertSame([20.0], $view->columnSpec()->gaps);
    }

    public function test_rule_with_length_and_colour(): void
    {
        $spec = $this->flow('<x-doc::column-flow rule="2pt #ff0000"><x-doc::text>A</x-doc::text></x-doc::column-flow>')->columnSpec();

        self::assertNotNull($spec?->rule);
        self::assertSame(2.0, $spec->rule->width);
        self::assertSame('#ff0000', strtolower((string) $spec->rule->color));
    }

    public function test_rule_with_a_length_only_and_false_for_none(): void
    {
        $spec = $this->flow('<x-doc::column-flow rule="1.5pt"><x-doc::text>A</x-doc::text></x-doc::column-flow>')->columnSpec();
        self::assertSame(1.5, $spec?->rule?->width);

        $none = $this->flow('<x-doc::column-flow rule="false"><x-doc::text>A</x-doc::text></x-doc::column-flow>')->columnSpec();
        self::assertNull($none?->rule);
    }

    public function test_bad_rule_is_an_invalid_value(): void
    {
        $this->expectException(InvalidValue::class);
        $this->flow('<x-doc::column-flow rule="thick"><x-doc::text>A</x-doc::text></x-doc::column-flow>');
    }

    public function test_negative_gap_and_non_positive_rule_are_invalid_values(): void
    {
        foreach (['column-gap="-10pt"', 'rule="-1pt"', 'rule="0pt"'] as $attr) {
            try {
                $this->flow('<x-doc::column-flow ' . $attr . '><x-doc::text>A</x-doc::text></x-doc::column-flow>');
                self::fail('Expected InvalidValue for ' . $attr);
            } catch (InvalidValue $e) {
                self::assertNotSame('', $e->getMessage());
            }
        }
    }

    public function test_other_attributes_are_styles(): void
    {
        $this->expectException(UnknownAttribute::class);
        $this->flow('<x-doc::column-flow bogus="1"><x-doc::text>A</x-doc::text></x-doc::column-flow>');
    }

    public function test_column_break_tag_rejects_attributes(): void
    {
        self::assertInstanceOf(ColumnBreak::class, $this->nodeFrom('<x-doc::column-break />'));

        $this->expectException(UnknownAttribute::class);
        $this->expectExceptionMessage('on <x-doc::column-break>');
        $this->nodeFrom('<x-doc::column-break gap="1pt" />');
    }

    public function test_blade_flow_renders_two_columns(): void
    {
        $blade = <<<'BLADE'
            <x-doc::document>
              <x-doc::page>
                <x-doc::column-flow>
                  <x-doc::text>LeftColumnWord</x-doc::text>
                  <x-doc::column-break />
                  <x-doc::text>RightColumnWord</x-doc::text>
                </x-doc::column-flow>
              </x-doc::page>
            </x-doc::document>
            BLADE;
        $view = 'column-flow-' . getmypid() . '-' . md5($blade);
        $path = sys_get_temp_dir() . "/$view.blade.php";
        file_put_contents($path, $blade);
        \Illuminate\Support\Facades\View::addLocation(sys_get_temp_dir());
        try {
            $pdf = Papercut::view($view)->pdf()->toString();
        } finally {
            unlink($path);
        }

        self::assertStringStartsWith('%PDF', $pdf);
        $text = $this->pdfText($pdf);
        self::assertStringContainsString('LeftColumnWord', $text);
        self::assertStringContainsString('RightColumnWord', $text);
    }
}
