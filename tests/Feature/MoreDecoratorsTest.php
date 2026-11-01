<?php

declare(strict_types=1);

namespace Papercut\Laravel\Tests\Feature;

use Papercut\Decorator\{KeyValueList, Signature, Stat, Totals};
use Papercut\Exception\InvalidValue;
use Papercut\Laravel\Exception\InvalidStructure;
use Papercut\Laravel\Facades\Papercut;
use Papercut\Laravel\Tests\TestCase;
use Papercut\{Node, Table, Text, View};

/** The stat, totals and signature tags, and the built-in variants. */
final class MoreDecoratorsTest extends TestCase
{
    public function test_stat_value_label_and_note(): void
    {
        $stat = $this->nodeFrom('<x-doc::stat label="Revenue" note="Up 12%" grow="1">$48,200</x-doc::stat>');
        self::assertInstanceOf(Stat::class, $stat);
        self::assertSame(1.0, $stat->style()->grow);
        self::assertSame(['Revenue', '$48,200', 'Up 12%'], self::texts($stat->render()));
    }

    public function test_stat_label_and_note_slots(): void
    {
        $stat = $this->nodeFrom("<x-doc::stat>\n<x-slot:label>Rich <b>label</b></x-slot:label>\n<x-slot:note>Note</x-slot:note>\n42\n</x-doc::stat>");
        self::assertInstanceOf(Stat::class, $stat);
        self::assertSame(['Rich label', '42', 'Note'], self::texts($stat->render()));
    }

    public function test_totals_rows_and_total_rows(): void
    {
        $totals = $this->nodeFrom('<x-doc::totals width="100%"><x-doc::kv label="Subtotal">$1,000.00</x-doc::kv><x-doc::kv label="Total due" total>$1,082.50</x-doc::kv><x-doc::kv label="Paid" :total="false">$0</x-doc::kv></x-doc::totals>');
        self::assertInstanceOf(Totals::class, $totals);
        self::assertSame([false, true, false], array_map(fn ($p) => $p->total, $totals->pairs()));
        self::assertInstanceOf(Table::class, $totals->render());
    }

    public function test_total_row_in_key_value_is_an_error(): void
    {
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('<x-doc::kv total> is only for <x-doc::totals>');
        $this->nodeFrom('<x-doc::key-value><x-doc::kv label="T" total>1</x-doc::kv></x-doc::key-value>');
    }

    public function test_bad_total_value(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessage('on <x-doc::kv>');
        $this->nodeFrom('<x-doc::totals><x-doc::kv label="T" total="maybe">1</x-doc::kv></x-doc::totals>');
    }

    public function test_totals_rejects_other_children(): void
    {
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('<x-doc::totals> can only contain <x-doc::kv>');
        $this->nodeFrom('<x-doc::totals><x-doc::text>x</x-doc::text></x-doc::totals>');
    }

    public function test_kv_inside_totals_is_allowed_and_still_allowed_in_key_value(): void
    {
        self::assertInstanceOf(KeyValueList::class, $this->nodeFrom('<x-doc::key-value><x-doc::kv label="A">1</x-doc::kv></x-doc::key-value>'));
    }

    public function test_signature_captions_and_content(): void
    {
        $sig = $this->nodeFrom('<x-doc::signature name="Jane Smith" role="Director" date="12 March 2026"><x-doc::text italic>Jane Smith</x-doc::text></x-doc::signature>');
        self::assertInstanceOf(Signature::class, $sig);
        self::assertSame(['Jane Smith', 'Jane Smith', 'Director', '12 March 2026'], self::texts($sig->render()));
    }

    public function test_blank_signature(): void
    {
        $sig = $this->nodeFrom('<x-doc::signature />');
        self::assertInstanceOf(Signature::class, $sig);
        self::assertCount(1, $sig->render()->children());
    }

    public function test_built_in_variants_need_no_theme_entry(): void
    {
        foreach ([
            '<x-doc::field variant="line" label="Name">x</x-doc::field>',
            '<x-doc::badge type="warning" variant="outline">x</x-doc::badge>',
            '<x-doc::card variant="flat" title="T">x</x-doc::card>',
            '<x-doc::section variant="large" title="T">x</x-doc::section>',
        ] as $blade) {
            self::assertNotNull($this->nodeFrom($blade), $blade);
        }
    }

    public function test_everything_renders_to_pdf_html_and_word(): void
    {
        $pending = Papercut::view('pdf.more-decorators');
        self::assertStringStartsWith('%PDF', $pending->pdf()->toString());
        $html = $pending->html()->page()->toString();
        foreach (['Revenue', 'Total due', 'Jane Smith', 'Director'] as $text) {
            self::assertStringContainsString($text, $html);
        }
        self::assertStringStartsWith('PK', $pending->docx()->toString());
    }

    /** @return list<string> */
    private static function texts(\Papercut\Component|Node $node): array
    {
        if ($node instanceof Text) {
            $s = '';
            foreach ($node->spanList() as $span) {
                $s .= $span->text;
            }
            return [$s];
        }
        $out = [];
        if ($node instanceof View) {
            foreach ($node->children() as $child) {
                $out = [...$out, ...self::texts($child)];
            }
        }
        return $out;
    }
}
