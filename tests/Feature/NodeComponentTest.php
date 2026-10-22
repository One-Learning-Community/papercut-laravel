<?php

declare(strict_types=1);

namespace Papercut\Laravel\Tests\Feature;

use Papercut\Export\FidelityLevel;
use Papercut\Exception\InvalidValue;
use Papercut\Image;
use Papercut\Laravel\Tests\TestCase;
use Papercut\Markup\{Markup, Note};
use Papercut\Text;

final class NodeComponentTest extends TestCase
{
    public function test_node_tag_embeds_a_php_node(): void
    {
        $img = Image::bytes('x');
        $col = $this->nodeFrom('<x-doc::column><x-doc::node :node="$n" /></x-doc::column>', ['n' => $img]);
        self::assertCount(1, $col->children());
        self::assertSame($img, $col->children()[0]);
    }

    public function test_text_node_joins_a_line_and_keeps_notes(): void
    {
        $label = Text::make('Agree')->withNotes(new Note(FidelityLevel::Approximated, 'f'));
        $t = $this->nodeFrom('<x-doc::text bold>× <x-doc::node :node="$l" /></x-doc::text>', ['l' => $label]);
        self::assertSame(['× ', 'Agree'], array_map(fn ($s) => $s->text, $t->spanList()));
        self::assertCount(1, $t->notes());
    }

    public function test_block_view_in_a_text_tag_is_flattened(): void
    {
        $t = $this->nodeFrom('<x-doc::text><x-doc::node :node="$v" /></x-doc::text>', ['v' => Markup::html('<p>a</p><p>b</p>')]);
        self::assertSame(['a', '', 'b'], array_map(fn ($s) => $s->text, $t->spanList()));
        self::assertSame([false, true, false], array_map(fn ($s) => $s->lineBreak, $t->spanList()));
        self::assertSame('markup block content (flattened into a line)', $t->notes()[0]->feature);
    }

    public function test_bad_node_value(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageMatches('/on <x-doc::node>/');
        $this->nodeFrom('<x-doc::node :node="$s" />', ['s' => 'string']);
    }

    public function test_whitespace_trims_only_at_the_outer_edges(): void
    {
        $t = $this->nodeFrom("<x-doc::p>\n  a <x-doc::node :node=\"\$l\" /> b\n</x-doc::p>", ['l' => Text::make('X')]);
        self::assertSame(['a ', 'X', ' b'], array_map(fn ($s) => $s->text, $t->spanList()));
    }
}
