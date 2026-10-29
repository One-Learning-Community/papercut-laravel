<?php

declare(strict_types=1);

namespace Papercut\Laravel\Tests\Feature;

use Papercut\{Document, Page};
use Papercut\Exception\InvalidValue;
use Papercut\Laravel\Exception\InvalidStructure;
use Papercut\Laravel\Tests\TestCase;

/** The first-header, first-footer, even-header and even-footer slots and the mirror attribute on <x-doc::page>. */
final class PageVariantsComponentTest extends TestCase
{
    private function page(string $blade): Page
    {
        $page = $this->nodeFrom($blade);
        self::assertInstanceOf(Page::class, $page);

        return $page;
    }

    public function test_first_and_even_slots_set_the_variants(): void
    {
        $page = $this->page(<<<'BLADE'
            <x-doc::page>
                <x-slot:first-header><x-doc::text>FH</x-doc::text></x-slot:first-header>
                <x-slot:first-footer><x-doc::text>FF</x-doc::text></x-slot:first-footer>
                <x-slot:even-header><x-doc::text>EH</x-doc::text></x-slot:even-header>
                <x-slot:even-footer><x-doc::text>EF</x-doc::text></x-slot:even-footer>
                Body
            </x-doc::page>
            BLADE);

        self::assertCount(1, $page->firstHeaderNodes() ?? []);
        self::assertCount(1, $page->firstFooterNodes() ?? []);
        self::assertCount(1, $page->evenHeaderNodes() ?? []);
        self::assertCount(1, $page->evenFooterNodes() ?? []);
    }

    public function test_an_empty_slot_means_none_on_that_page(): void
    {
        $page = $this->page(<<<'BLADE'
            <x-doc::page>
                <x-slot:first-header></x-slot:first-header>
                <x-slot:first-footer> </x-slot:first-footer>
                Body
            </x-doc::page>
            BLADE);

        self::assertSame([], $page->firstHeaderNodes());
        self::assertSame([], $page->firstFooterNodes());
        self::assertNull($page->evenHeaderNodes());
        self::assertNull($page->evenFooterNodes());
    }

    public function test_mirror_attribute_turns_on_mirrored_margins(): void
    {
        self::assertTrue($this->page('<x-doc::page mirror>Body</x-doc::page>')->mirrorsMargins());
        self::assertTrue($this->page('<x-doc::page mirror="true">Body</x-doc::page>')->mirrorsMargins());
        self::assertFalse($this->page('<x-doc::page mirror="false">Body</x-doc::page>')->mirrorsMargins());
        self::assertFalse($this->page('<x-doc::page>Body</x-doc::page>')->mirrorsMargins());
    }

    public function test_mirror_refuses_a_value_that_is_not_a_boolean(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessage('on <x-doc::page>');

        $this->page('<x-doc::page mirror="sideways">Body</x-doc::page>');
    }

    public function test_unknown_slot_error_lists_the_new_slots(): void
    {
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('first-header, first-footer, even-header, even-footer');
        // The newline after the closing slot tag matters: with the body text glued to it, this test
        // left a Blade output buffer open when the exception was thrown.
        $this->page("<x-doc::page><x-slot:last-header>X</x-slot:last-header>\nBody</x-doc::page>");
    }

    public function test_variant_slots_render_in_pdf(): void
    {
        $page = $this->page(<<<'BLADE'
            <x-doc::page>
                <x-slot:first-header><x-doc::text>Opening banner</x-doc::text></x-slot:first-header>
                <x-doc::text>Body text</x-doc::text>
            </x-doc::page>
            BLADE);

        $text = $this->pdfText(Document::make($page)->toPdf()->bytes());

        self::assertStringContainsString('Opening banner', $text);
        self::assertStringContainsString('Body text', $text);
    }
}
