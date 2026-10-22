<?php

declare(strict_types=1);

namespace Papercut\Laravel\Tests\Feature;

use Papercut\Exception\InvalidValue;
use Papercut\Laravel\Exception\InvalidStructure;
use Papercut\Laravel\Tests\TestCase;
use Papercut\ListNode;
use Papercut\Style\ListMarker;

final class ListComponentsTest extends TestCase
{
    public function test_nested_lists(): void
    {
        $list = $this->nodeFrom('<x-doc::ol start="2"><x-doc::li>One</x-doc::li><x-doc::li>Two<x-doc::ul><x-doc::li>a</x-doc::li></x-doc::ul></x-doc::li></x-doc::ol>');
        self::assertInstanceOf(ListNode::class, $list);
        self::assertTrue($list->isOrdered());
        self::assertSame(2, $list->startNumber());
        self::assertInstanceOf(ListNode::class, $list->items()[1]->children()[1]);
        self::assertFalse($list->items()[1]->children()[1]->isOrdered());
    }

    public function test_marker_and_value(): void
    {
        $list = $this->nodeFrom('<x-doc::ol marker="upper-roman"><x-doc::li value="7">x</x-doc::li></x-doc::ol>');
        self::assertInstanceOf(ListNode::class, $list);
        self::assertSame(ListMarker::UpperRoman, $list->markerStyle());
        self::assertSame(7, $list->items()[0]->valueOverride());

        $ul = $this->nodeFrom('<x-doc::ul marker="square"><x-doc::li>x</x-doc::li></x-doc::ul>');
        self::assertInstanceOf(ListNode::class, $ul);
        self::assertSame(ListMarker::Square, $ul->markerStyle());
    }

    public function test_style_attributes(): void
    {
        $list = $this->nodeFrom('<x-doc::ul color="red-500"><x-doc::li color="blue-500">x</x-doc::li></x-doc::ul>');
        self::assertInstanceOf(ListNode::class, $list);
        self::assertNotNull($list->style()->color);
    }

    /** @return iterable<string, array{string, string}> */
    public static function misplaced(): iterable
    {
        yield 'li in column' => ['<x-doc::column><x-doc::li>x</x-doc::li></x-doc::column>', '<x-doc::li> must be inside <x-doc::ol> or <x-doc::ul>'];
        yield 'text in ol' => ['<x-doc::ol>text</x-doc::ol>', '<x-doc::ol> can only contain <x-doc::li>'];
        yield 'p in ul' => ['<x-doc::ul><x-doc::p>x</x-doc::p></x-doc::ul>', '<x-doc::ul> can only contain <x-doc::li>'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('misplaced')]
    public function test_structure_errors(string $blade, string $message): void
    {
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage($message);
        $this->nodeFrom($blade);
    }

    public function test_bad_marker(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessage('marker');
        $this->nodeFrom('<x-doc::ol marker="squiggle"><x-doc::li>x</x-doc::li></x-doc::ol>');
    }

    public function test_bad_marker_location(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessage('on <x-doc::ol>');
        $this->nodeFrom('<x-doc::ol marker="squiggle"><x-doc::li>x</x-doc::li></x-doc::ol>');
    }
}
