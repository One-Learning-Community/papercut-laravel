<?php

declare(strict_types=1);

namespace Papercut\Laravel\Tests\Feature;

use Papercut\Decorator\{Callout, Section};
use Papercut\Exception\{InvalidValue, UnknownAttribute};
use Papercut\Laravel\Exception\InvalidStructure;
use Papercut\Laravel\Tests\TestCase;
use Papercut\{Text, View};

final class DecoratorVariantsTest extends TestCase
{
    public function test_variant_attribute_on_section(): void
    {
        config(['papercut.styles' => ['section.title.large' => ['font-size' => '14pt']]]);
        $section = $this->nodeFrom('<x-doc::section title="T" variant="large">x</x-doc::section>');

        self::assertInstanceOf(Section::class, $section);
        self::assertSame(['large'], $section->variants());
        $heading = $section->render()->children()[0];
        self::assertInstanceOf(View::class, $heading);
        $title = $heading->children()[0];
        self::assertInstanceOf(Text::class, $title);
        self::assertSame('section.title section.title.large?', $title->styleName());
    }

    public function test_variant_on_callout_with_tone(): void
    {
        config(['papercut.styles' => ['callout.large' => ['padding' => '20pt']]]);
        $callout = $this->nodeFrom('<x-doc::callout type="tip" variant="large">x</x-doc::callout>');

        self::assertInstanceOf(Callout::class, $callout);
        self::assertSame('callout callout.tip callout.large?', $callout->render()->styleName());
    }

    public function test_unknown_variant_throws_listing_names(): void
    {
        try {
            $this->nodeFrom('<x-doc::section title="T" variant="huge">x</x-doc::section>');
            self::fail('Expected InvalidStructure');
        } catch (InvalidStructure $e) {
            self::assertStringContainsString('section.title.huge', $e->getMessage());
            self::assertStringContainsString('section.huge', $e->getMessage());
            self::assertStringContainsString('<x-doc::section>', $e->getMessage());
        }
    }

    public function test_invalid_variant_word_throws(): void
    {
        $this->expectException(InvalidValue::class);
        $this->nodeFrom('<x-doc::section variant="Big_One">x</x-doc::section>');
    }

    public function test_user_style_with_question_mark_rejected(): void
    {
        $this->expectException(InvalidValue::class);
        $this->nodeFrom('<x-doc::section style="section.large?">x</x-doc::section>');
    }

    public function test_variant_attribute_is_not_a_style_property(): void
    {
        config(['papercut.styles' => ['card.large' => ['padding' => '20pt']]]);
        $card = $this->nodeFrom('<x-doc::card variant="large">x</x-doc::card>');
        self::assertInstanceOf(\Papercut\Decorator\Card::class, $card);
        self::assertSame(['large'], $card->variants());
    }

    public function test_optional_marker_in_tone_rejected(): void
    {
        foreach (['callout' => 'note?', 'badge' => 'tip?'] as $tag => $tone) {
            try {
                $this->nodeFrom("<x-doc::$tag type=\"$tone\">x</x-doc::$tag>");
                self::fail("Expected an error for $tag");
            } catch (InvalidValue | UnknownAttribute) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function test_optional_marker_in_code_style_rejected(): void
    {
        $this->expectException(UnknownAttribute::class);
        $this->nodeFrom('<x-doc::code style="foo?">x</x-doc::code>');
    }

    public function test_a_variant_that_collides_with_a_part_name_is_rejected(): void
    {
        try {
            $this->nodeFrom('<x-doc::choices variant="selected"><x-doc::choice selected>a</x-doc::choice></x-doc::choices>');
            self::fail('Expected InvalidStructure');
        } catch (InvalidStructure $e) {
            self::assertStringContainsString("'selected' collides", $e->getMessage());
        }
    }
}
