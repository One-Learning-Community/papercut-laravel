<?php

declare(strict_types=1);

namespace Papercut\Laravel\Tests\Feature;

use Papercut\Decorator\{Badge, Blockquote, Callout, Card, ChoiceList, Field, KeyValueList, Section};
use Papercut\Laravel\Exception\InvalidStructure;
use Papercut\Value\{Length, Percent};
use Papercut\Exception\{InvalidValue, UnknownAttribute};
use Papercut\Laravel\Facades\Papercut;
use Papercut\Laravel\Tests\TestCase;
use Papercut\{Node, Text, View};

final class DecoratorComponentsTest extends TestCase
{
    public function test_each_tag_builds_its_decorator(): void
    {
        self::assertInstanceOf(Card::class, $this->nodeFrom('<x-doc::card title="T">x</x-doc::card>'));
        self::assertInstanceOf(Callout::class, $this->nodeFrom('<x-doc::callout type="tip">x</x-doc::callout>'));
        self::assertInstanceOf(Blockquote::class, $this->nodeFrom('<x-doc::blockquote cite="A">x</x-doc::blockquote>'));
        self::assertInstanceOf(Section::class, $this->nodeFrom('<x-doc::section title="S">x</x-doc::section>'));
        self::assertInstanceOf(Field::class, $this->nodeFrom('<x-doc::field label="L">x</x-doc::field>'));
        self::assertInstanceOf(Badge::class, $this->nodeFrom('<x-doc::badge type="tip">x</x-doc::badge>'));
    }

    public function test_card_style_name_and_attributes(): void
    {
        $card = $this->nodeFrom('<x-doc::card padding="12pt" style="badge">x</x-doc::card>', []);
        self::assertInstanceOf(Card::class, $card);
        self::assertSame('badge', $card->styleName());
        self::assertSame(12.0, $card->style()->padding?->top->points ?? null);
        self::assertSame('card badge', $card->render()->styleName());
    }

    public function test_title_slot_becomes_the_title_text(): void
    {
        $card = $this->nodeFrom(
            '<x-doc::card title="Attr"><x-slot:title><x-doc::markup :text="$t" /></x-slot:title> Body</x-doc::card>',
            ['t' => 'From slot'],
        );
        self::assertInstanceOf(Card::class, $card);
        $texts = self::texts($card->render());
        self::assertContains('From slot', $texts);
        self::assertNotContains('Attr', $texts);
    }

    public function test_title_slot_with_inline_tags(): void
    {
        $callout = $this->nodeFrom('<x-doc::callout><x-slot:title>Mind <b>this</b></x-slot:title> Body</x-doc::callout>');
        self::assertInstanceOf(Callout::class, $callout);
        self::assertContains('Mind this', self::texts($callout->render()));
    }

    public function test_blockquote_cite_and_field_label_slots(): void
    {
        $q = $this->nodeFrom('<x-doc::blockquote><x-slot:cite>Ann</x-slot:cite> Hi</x-doc::blockquote>');
        self::assertInstanceOf(Blockquote::class, $q);
        self::assertStringContainsString("\u{2014} Ann", implode("", self::texts($q->render())));

        $f = $this->nodeFrom('<x-doc::field label="Attr"><x-slot:label>Slot</x-slot:label> v</x-doc::field>');
        self::assertInstanceOf(Field::class, $f);
        self::assertContains('Slot', self::texts($f->render()));
        self::assertNotContains('Attr', self::texts($f->render()));
    }

    public function test_callout_type(): void
    {
        $c = $this->nodeFrom('<x-doc::callout type="warning">x</x-doc::callout>');
        self::assertInstanceOf(Callout::class, $c);
        self::assertSame('warning', $c->toneName());
        $default = $this->nodeFrom('<x-doc::callout>x</x-doc::callout>');
        self::assertInstanceOf(Callout::class, $default);
        self::assertSame('note', $default->toneName());
    }

    public function test_unknown_callout_type_suggests_and_names_the_tag(): void
    {
        try {
            $this->nodeFrom('<x-doc::callout type="warnin">x</x-doc::callout>');
            self::fail('expected UnknownAttribute');
        } catch (UnknownAttribute $e) {
            self::assertStringContainsString('warning', $e->getMessage());
            self::assertStringContainsString('on <x-doc::callout>', $e->getMessage());
        }
    }

    public function test_empty_type_says_tone_name_expected(): void
    {
        foreach (['callout', 'badge'] as $tag) {
            try {
                $this->nodeFrom('<x-doc::' . $tag . ' type="">x</x-doc::' . $tag . '>');
                self::fail('expected InvalidValue');
            } catch (InvalidValue $e) {
                self::assertStringContainsString('expected a tone name', $e->getMessage());
                self::assertStringNotContainsString('single word', $e->getMessage());
            }
        }
    }

    public function test_custom_tone_without_title_style_renders_untitled_callout(): void
    {
        config(['papercut.styles' => ['callout.hint' => ['padding' => '4pt']]]);
        $c = $this->nodeFrom('<x-doc::callout type="hint">x</x-doc::callout>');
        self::assertInstanceOf(Callout::class, $c);
        self::assertSame('hint', $c->toneName());
    }

    public function test_titled_custom_tone_still_requires_title_style(): void
    {
        config(['papercut.styles' => ['callout.hint' => ['padding' => '4pt']]]);
        $this->expectException(UnknownAttribute::class);
        $this->nodeFrom('<x-doc::callout type="hint" title="T">x</x-doc::callout>');
    }

    public function test_unknown_badge_type(): void
    {
        $this->expectException(UnknownAttribute::class);
        $this->expectExceptionMessage('on <x-doc::badge>');
        $this->nodeFrom('<x-doc::badge type="tp">x</x-doc::badge>');
    }

    public function test_badge_type(): void
    {
        $b = $this->nodeFrom('<x-doc::badge type="danger">x</x-doc::badge>');
        self::assertInstanceOf(Badge::class, $b);
        self::assertSame('danger', $b->toneName());
    }

    public function test_section_number_and_rule(): void
    {
        $s = $this->nodeFrom('<x-doc::section title="T" number="3" rule>x</x-doc::section>');
        self::assertInstanceOf(Section::class, $s);
        self::assertContains('3', self::texts($s->render()));
        self::assertStringContainsString('section.rule', self::allStyleNames($s->render()));

        $off = $this->nodeFrom('<x-doc::section title="T" rule="false">x</x-doc::section>');
        self::assertInstanceOf(Section::class, $off);
        self::assertStringNotContainsString('section.rule', self::allStyleNames($off->render()));

        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessage('on <x-doc::section>');
        $this->nodeFrom('<x-doc::section title="T" rule="maybe">x</x-doc::section>');
    }

    public function test_empty_field_shows_placeholder(): void
    {
        $f = $this->nodeFrom('<x-doc::field placeholder="No response">   </x-doc::field>');
        self::assertInstanceOf(Field::class, $f);
        self::assertTrue($f->isEmpty());
        self::assertContains('No response', self::texts($f->render()));
    }

    public function test_bad_lines(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessage('on <x-doc::field>');
        $this->nodeFrom('<x-doc::field lines="abc">x</x-doc::field>');
    }

    public function test_lines(): void
    {
        $f = $this->nodeFrom('<x-doc::field lines="3">x</x-doc::field>');
        self::assertInstanceOf(Field::class, $f);
        $inner = $f->render()->children()[0];
        self::assertInstanceOf(\Papercut\View::class, $inner);
        self::assertSame('3lh', (string) $inner->style()->minHeight);
    }

    public function test_style_attributes_accept_em_and_lh(): void
    {
        $v = $this->nodeFrom('<x-doc::view padding="0.5em" min-height="4lh">x</x-doc::view>');
        self::assertInstanceOf(\Papercut\View::class, $v);
        self::assertSame('0.5em', (string) $v->style()->padding?->top);
        self::assertSame('4lh', (string) $v->style()->minHeight);
    }

    public function test_text_tag_renders_mark_as_highlight(): void
    {
        $t = $this->nodeFrom('<x-doc::text>a <mark>b</mark></x-doc::text>');
        self::assertInstanceOf(Text::class, $t);
        $highlighted = array_values(array_filter($t->spanList(), static fn ($s) => $s->style->background !== null));
        self::assertCount(1, $highlighted);
        self::assertSame('b', $highlighted[0]->text);
    }

    public function test_inline_error_lists_mark(): void
    {
        $this->expectException(\Papercut\Laravel\Exception\InvalidStructure::class);
        $this->expectExceptionMessage('<mark>');
        $this->nodeFrom('<x-doc::text><x-doc::view>x</x-doc::view><x-doc::card>y</x-doc::card></x-doc::text>');
    }

    public function test_choices_build_a_radio_list(): void
    {
        $list = $this->nodeFrom('<x-doc::choices type="radio" emphasize-selected padding="4pt"><x-doc::choice selected>Yes</x-doc::choice><x-doc::choice :selected="false">No <b>way</b></x-doc::choice></x-doc::choices>');
        self::assertInstanceOf(ChoiceList::class, $list);
        self::assertTrue($list->isRadio());
        self::assertCount(2, $list->options());
        self::assertTrue($list->options()[0]->selected);
        self::assertFalse($list->options()[1]->selected);
        self::assertSame(4.0, $list->style()->padding?->top->points ?? null);
        self::assertStringContainsString('No way', implode('', self::texts($list->render())));
    }

    public function test_choices_default_to_checkbox_and_bad_type_fails(): void
    {
        $list = $this->nodeFrom('<x-doc::choices><x-doc::choice>A</x-doc::choice></x-doc::choices>');
        self::assertInstanceOf(ChoiceList::class, $list);
        self::assertFalse($list->isRadio());
        try {
            $this->nodeFrom('<x-doc::choices type="dropdown"><x-doc::choice>A</x-doc::choice></x-doc::choices>');
            self::fail('expected InvalidValue');
        } catch (InvalidValue $e) {
            self::assertStringContainsString('radio, checkbox', $e->getMessage());
            self::assertStringContainsString('on <x-doc::choices>', $e->getMessage());
        }
    }

    public function test_choice_with_a_badge_and_text_keeps_both(): void
    {
        $list = $this->nodeFrom('<x-doc::choices><x-doc::choice>Hi <x-doc::badge>b</x-doc::badge></x-doc::choice></x-doc::choices>');
        self::assertInstanceOf(ChoiceList::class, $list);
        self::assertInstanceOf(View::class, $list->options()[0]->label);
    }

    public function test_choice_outside_choices_fails(): void
    {
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('must be inside <x-doc::choices>');
        $this->nodeFrom('<x-doc::column><x-doc::choice>A</x-doc::choice></x-doc::column>');
    }

    public function test_choices_reject_other_children(): void
    {
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('can only contain <x-doc::choice>');
        $this->nodeFrom('<x-doc::choices><x-doc::card>x</x-doc::card></x-doc::choices>');
    }

    public function test_key_value_builds_pairs(): void
    {
        $list = $this->nodeFrom('<x-doc::key-value label-width="40%"><x-doc::kv label="Respondent">Jane</x-doc::kv><x-doc::kv><x-slot:label>Tier</x-slot:label><x-doc::badge type="tip">Gold</x-doc::badge></x-doc::kv></x-doc::key-value>');
        self::assertInstanceOf(KeyValueList::class, $list);
        self::assertCount(2, $list->pairs());
        self::assertInstanceOf(Text::class, $list->pairs()[0]->value);
        self::assertInstanceOf(Badge::class, $list->pairs()[1]->value);
        self::assertStringContainsString('Tier', implode('', self::texts($list->pairs()[1]->label)));
    }

    public function test_key_value_label_width_accepts_length_and_percent(): void
    {
        foreach (['30mm' => Length::class, '40%' => Percent::class] as $width => $class) {
            $list = $this->nodeFrom('<x-doc::key-value label-width="' . $width . '"><x-doc::kv label="A">b</x-doc::kv></x-doc::key-value>');
            self::assertInstanceOf(KeyValueList::class, $list);
            $table = $list->render();
            self::assertInstanceOf(\Papercut\Table::class, $table);
            self::assertInstanceOf($class, $table->trackList()?->tracks[0] ?? null);
        }
    }

    public function test_kv_label_slot_wins_over_attribute(): void
    {
        $list = $this->nodeFrom('<x-doc::key-value><x-doc::kv label="Attr"><x-slot:label>Slot</x-slot:label> v</x-doc::kv></x-doc::key-value>');
        self::assertInstanceOf(KeyValueList::class, $list);
        self::assertSame('Slot', implode('', self::texts($list->pairs()[0]->label)));
    }

    public function test_kv_blank_label_is_missing(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessage('on <x-doc::kv>');
        $this->nodeFrom('<x-doc::key-value><x-doc::kv label="  ">x</x-doc::kv></x-doc::key-value>');
    }

    public function test_choice_bad_selected(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessage('on <x-doc::choice>');
        $this->nodeFrom('<x-doc::choices><x-doc::choice selected="maybe">A</x-doc::choice></x-doc::choices>');
    }

    public function test_choice_and_kv_reject_unknown_attributes(): void
    {
        foreach ([
            ['<x-doc::choices><x-doc::choice selcted>A</x-doc::choice></x-doc::choices>', 'on <x-doc::choice>', 'selected'],
            ['<x-doc::key-value><x-doc::kv lable="A">v</x-doc::kv></x-doc::key-value>', 'on <x-doc::kv>', 'label'],
            ['<x-doc::key-value><x-doc::kv label="A" color="red">v</x-doc::kv></x-doc::key-value>', 'on <x-doc::kv>', 'color'],
        ] as [$blade, $where, $needle]) {
            try {
                $this->nodeFrom($blade);
                self::fail('expected UnknownAttribute');
            } catch (UnknownAttribute $e) {
                self::assertStringContainsString($where, $e->getMessage());
                self::assertStringContainsString($needle, $e->getMessage());
            }
        }
    }

    public function test_key_value_bad_label_width(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessage('on <x-doc::key-value>');
        $this->nodeFrom('<x-doc::key-value label-width="wide"></x-doc::key-value>');
    }

    public function test_kv_without_label_fails(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessage('on <x-doc::kv>');
        $this->nodeFrom('<x-doc::key-value><x-doc::kv>x</x-doc::kv></x-doc::key-value>');
    }

    public function test_kv_outside_key_value_fails(): void
    {
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('must be inside <x-doc::key-value>');
        $this->nodeFrom('<x-doc::column><x-doc::kv label="A">b</x-doc::kv></x-doc::column>');
    }

    public function test_key_value_rejects_other_children_and_empty_renders(): void
    {
        $empty = $this->nodeFrom('<x-doc::key-value></x-doc::key-value>');
        self::assertInstanceOf(KeyValueList::class, $empty);
        self::assertInstanceOf(View::class, $empty->render());
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('can only contain <x-doc::kv>');
        $this->nodeFrom('<x-doc::key-value><x-doc::card>x</x-doc::card></x-doc::key-value>');
    }

    public function test_pdf_and_html_render_all_tags(): void
    {
        self::assertStringStartsWith('%PDF', Papercut::view('pdf.decorators')->pdf()->toString());
        $html = Papercut::view('pdf.decorators')->html()->page()->toString();
        self::assertStringContainsString('Overview', $html);
        self::assertStringContainsString('None', $html);
        self::assertStringContainsString('Respondent', $html);
        self::assertStringContainsString('Jane', $html);
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

    private static function allStyleNames(View $view): string
    {
        $s = (string) $view->styleName();
        foreach ($view->children() as $child) {
            if ($child instanceof View) {
                $s .= ' ' . self::allStyleNames($child);
            }
        }
        return $s;
    }
}
