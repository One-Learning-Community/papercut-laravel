<?php

declare(strict_types=1);

namespace Papercut\Laravel\Tests\Feature;

use Papercut\Decorator\Badge;
use Papercut\Exception\UnknownAttribute;
use Papercut\Laravel\Exception\InvalidStructure;
use Papercut\Laravel\Tests\TestCase;
use Papercut\{Span, Text};

final class InlineBadgeTest extends TestCase
{
    public function test_badge_in_paragraph_is_an_inline_span(): void
    {
        $t = $this->nodeFrom('<x-doc::p>Returns <x-doc::badge type="tip">201</x-doc::badge> when it works.</x-doc::p>');
        self::assertInstanceOf(Text::class, $t);
        $spans = $t->spanList();
        self::assertSame(['Returns ', '201', ' when it works.'], array_map(static fn ($s) => $s->text, $spans));
        self::assertSame(Span::badge('201', 'tip')->styleName, $spans[1]->styleName);
    }

    public function test_variant_attribute(): void
    {
        $t = $this->nodeFrom('<x-doc::p><x-doc::badge type="tip" variant="outline">v2</x-doc::badge></x-doc::p>');
        self::assertEquals([Span::badge('v2', 'tip', 'outline')], $t->spanList());
    }

    public function test_default_tone(): void
    {
        $t = $this->nodeFrom('<x-doc::p>a <x-doc::badge>b</x-doc::badge></x-doc::p>');
        self::assertEquals(Span::badge('b'), $t->spanList()[1]);
    }

    public function test_entities_round_trip(): void
    {
        $t = $this->nodeFrom('<x-doc::p><x-doc::badge>{{ $c }}</x-doc::badge></x-doc::p>', ['c' => 'a & <b>']);
        self::assertSame('a & <b>', $t->spanList()[0]->text);
    }

    public function test_outside_text_it_stays_the_block_badge(): void
    {
        self::assertInstanceOf(Badge::class, $this->nodeFrom('<x-doc::badge type="tip">x</x-doc::badge>'));
    }

    public function test_nested_markup_is_rejected(): void
    {
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('can only contain plain text');
        $this->nodeFrom('<x-doc::p>a <x-doc::badge>x <b>y</b></x-doc::badge></x-doc::p>');
    }

    public function test_empty_badge_in_text_is_rejected(): void
    {
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('needs a label');
        $this->nodeFrom('<x-doc::p>a <x-doc::badge></x-doc::badge></x-doc::p>');
    }

    public function test_unknown_type_names_the_style(): void
    {
        try {
            $this->nodeFrom('<x-doc::p>a <x-doc::badge type="zzz">x</x-doc::badge></x-doc::p>');
            self::fail('expected UnknownAttribute');
        } catch (UnknownAttribute $e) {
            self::assertStringContainsString('badge.zzz', $e->getMessage());
        }
    }
}
