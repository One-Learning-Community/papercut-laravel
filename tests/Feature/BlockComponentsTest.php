<?php

declare(strict_types=1);

namespace Papercut\Laravel\Tests\Feature;

use Papercut\{Column, Link, PageBreak, Spacer, Text, View};
use Papercut\Exception\{InvalidValue, UnknownAttribute};
use Papercut\Laravel\Tests\TestCase;
use Papercut\Style\{Align, Direction};

final class BlockComponentsTest extends TestCase
{
    public function test_row_with_attributes_and_children(): void
    {
        $node = $this->nodeFrom('<x-doc::row gap="8pt" align="center" padding="4pt"><x-doc::column>A</x-doc::column> B </x-doc::row>');
        self::assertInstanceOf(View::class, $node);
        self::assertSame(Direction::Row, $node->style()->direction);
        self::assertSame(Align::Center, $node->style()->alignItems);
        self::assertSame(8.0, $node->style()->gap?->points);
        self::assertCount(2, $node->children());
        self::assertInstanceOf(Text::class, $node->children()[1]);
    }

    public function test_whitespace_between_blocks_is_dropped(): void
    {
        $node = $this->nodeFrom("<x-doc::column>\n  <x-doc::spacer size=\"4pt\" />\n  <x-doc::page-break />\n</x-doc::column>");
        self::assertCount(2, $node->children());
        self::assertInstanceOf(Spacer::class, $node->children()[0]);
        self::assertInstanceOf(PageBreak::class, $node->children()[1]);
    }

    public function test_bound_values_pass_through_typed(): void
    {
        $node = $this->nodeFrom('<x-doc::view :padding="$p" />', ['p' => \Papercut\pt(3)]);
        self::assertSame(3.0, $node->style()->padding?->top->points);
    }

    public function test_unknown_attribute_names_the_tag(): void
    {
        $this->expectException(UnknownAttribute::class);
        $this->expectExceptionMessage('Unknown attribute "paddng" on <x-doc::column>. Did you mean "padding"?');
        $this->nodeFrom('<x-doc::column paddng="4pt" />');
    }

    public function test_invalid_value_names_attribute_and_tag(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessage('Invalid edges "20 mm" for margin on <x-doc::view>. Did you mean "20mm"?');
        $this->nodeFrom('<x-doc::view margin="20 mm" />');
    }

    public function test_link_requires_href(): void
    {
        self::assertSame('https://x.test', $this->nodeFrom('<x-doc::link href="https://x.test">go</x-doc::link>')->uri());
        $this->expectException(\Papercut\Laravel\Exception\InvalidStructure::class);
        $this->nodeFrom('<x-doc::link>go</x-doc::link>');
    }

    public function test_app_component_with_extra_attributes_nests_cleanly(): void
    {
        $node = $this->nodeFrom('<x-doc::column><x-student-card class="mb-4" :name="$n" /></x-doc::column>', ['n' => 'Ada']);
        $card = $node->children()[0];
        self::assertInstanceOf(View::class, $card);
    }

    public function test_spacer_grow_is_numeric_and_non_negative(): void
    {
        self::assertSame(2.0, $this->nodeFrom('<x-doc::spacer grow="2" />')->style()->grow);
        self::assertSame(0.0, $this->nodeFrom('<x-doc::spacer grow="0" />')->style()->grow);
        self::assertSame(1.0, $this->nodeFrom('<x-doc::spacer />')->style()->grow);
    }

    /** @return iterable<string, array{string}> */
    public static function badGrow(): iterable
    {
        yield 'text' => ['<x-doc::spacer grow="abc" />'];
        yield 'presence' => ['<x-doc::spacer grow />'];
        yield 'negative' => ['<x-doc::spacer grow="-1" />'];
        yield 'bound array' => ['<x-doc::spacer :grow="[1]" />'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('badGrow')]
    public function test_spacer_rejects_invalid_grow(string $blade): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessage('for grow on <x-doc::spacer>');
        $this->nodeFrom($blade);
    }

    public function test_alias_error_reports_the_users_key(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessage('for align on <x-doc::row>');
        $this->nodeFrom('<x-doc::row align="bogus" />');
    }

    public function test_align_alias_is_scoped_to_containers(): void
    {
        $this->expectException(UnknownAttribute::class);
        $this->expectExceptionMessage('Unknown attribute "align" on <x-doc::link>');
        $this->nodeFrom('<x-doc::link href="x" align="center">go</x-doc::link>');
    }

    public function test_named_style_and_style_object(): void
    {
        config(['papercut.styles' => ['card' => ['padding' => '12pt']]]);
        self::assertSame('card', $this->nodeFrom('<x-doc::view style="card" />')->styleName());
        $node = $this->nodeFrom('<x-doc::view :style="$s" />', ['s' => \Papercut\Style\Style::fromAttributes(['padding' => '4pt'])]);
        self::assertSame(4.0, $node->style()->padding?->top->points);
    }

    public function test_non_string_style_is_invalid(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessage('Invalid style "5" for style on <x-doc::view>');
        $this->nodeFrom('<x-doc::view :style="5" />');
    }

    public function test_nested_file_component_errors_are_unwrapped(): void
    {
        $this->expectException(UnknownAttribute::class);
        $this->expectExceptionMessage('Unknown attribute "paddng" on <x-doc::column>');
        $this->nodeFrom('<x-doc::column><x-bad-card /></x-doc::column>');
    }
}
