<?php

declare(strict_types=1);

namespace Papercut\Laravel\Tests\Feature;

use Papercut\{Column, Document, Page, PdfPage, Place, QrCode, Text};
use Papercut\Exception\{InvalidValue, PdfUnreadable};
use Papercut\Laravel\Exception\InvalidStructure;
use Papercut\Laravel\Tests\TestCase;
use Papercut\Style\Anchor;
use Papercut\Value\{Length, Percent};
use function Papercut\pt;

/** <x-doc::pdf-page>, <x-doc::place>, and the template attributes and overlay slot on <x-doc::page>. */
final class PdfTemplateComponentTest extends TestCase
{
    private string $dir;
    private string $template;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/papercut-template-' . getmypid() . '-' . bin2hex(random_bytes(3));
        mkdir($this->dir . '/img', 0777, true);
        $page = static fn (string $word): Page => Page::size(pt(400), pt(300))->margin(pt(0))->body(
            Column::make()->width(pt(50))->height(pt(50))->background('#000'),
            Text::make($word),
        );
        $this->template = Document::make($page('First'), $page('Second'))->toPdf()->bytes();
        file_put_contents($this->dir . '/img/certificate.pdf', $this->template);
        config(['papercut.image_path' => $this->dir . '/img']);
    }

    protected function tearDown(): void
    {
        @unlink($this->dir . '/img/certificate.pdf');
        @rmdir($this->dir . '/img');
        @rmdir($this->dir);
        parent::tearDown();
    }

    public function test_pdf_page_src_is_resolved_inside_the_image_path(): void
    {
        $n = $this->nodeFrom('<x-doc::pdf-page src="certificate.pdf" page="2" width="100pt" />');

        self::assertInstanceOf(PdfPage::class, $n);
        self::assertSame(['kind' => 'file', 'value' => $this->dir . '/img/certificate.pdf'], $n->source());
        self::assertSame(2, $n->pageNumber());
        self::assertInstanceOf(Length::class, $n->style()->width);
        self::assertEqualsWithDelta(100.0, $n->style()->width->points, 1e-9);
    }

    public function test_pdf_page_data_takes_the_bytes_unchanged(): void
    {
        $n = $this->nodeFrom('<x-doc::pdf-page :data="$pdf" password="pw" />', ['pdf' => $this->template]);

        self::assertInstanceOf(PdfPage::class, $n);
        self::assertSame(['kind' => 'bytes', 'value' => $this->template], $n->source());
        self::assertSame('pw', $n->password());
    }

    public function test_pdf_page_needs_exactly_one_source(): void
    {
        foreach (['<x-doc::pdf-page />', '<x-doc::pdf-page src="certificate.pdf" :data="$pdf" />'] as $blade) {
            try {
                $this->nodeFrom($blade, ['pdf' => $this->template]);
                self::fail("$blade should be refused");
            } catch (InvalidStructure $e) {
                self::assertStringContainsString('requires exactly one of src or data', $e->getMessage());
            }
        }
    }

    public function test_pdf_page_number_must_be_a_whole_number_from_one(): void
    {
        foreach (['0', 'two', '1.5'] as $page) {
            try {
                $this->nodeFrom('<x-doc::pdf-page src="certificate.pdf" page="' . $page . '" />');
                self::fail("page=\"$page\" should be refused");
            } catch (InvalidValue $e) {
                self::assertStringContainsString('page', $e->getMessage());
            }
        }
    }

    public function test_a_missing_pdf_names_the_tag(): void
    {
        $this->expectException(PdfUnreadable::class);
        $this->expectExceptionMessage('on <x-doc::pdf-page src="missing.pdf">');

        $this->nodeFrom('<x-doc::pdf-page src="missing.pdf" />');
    }

    public function test_a_pdf_outside_the_image_path_is_refused(): void
    {
        $this->expectException(PdfUnreadable::class);
        $this->expectExceptionMessage('escapes the image directory');

        $this->nodeFrom('<x-doc::pdf-page src="../certificate.pdf" />');
    }

    public function test_page_template_takes_the_size_and_draws_the_page(): void
    {
        $page = $this->nodeFrom('<x-doc::page template="certificate.pdf" template-page="2" margin="0">Body</x-doc::page>');

        self::assertInstanceOf(Page::class, $page);
        self::assertEqualsWithDelta(400.0, $page->width(), 1e-6);
        self::assertEqualsWithDelta(300.0, $page->height(), 1e-6);
        self::assertSame(2, $page->templatePage()?->pageNumber());
        self::assertStringContainsString('Second', $this->pdfText(Document::make($page)->toPdf()->bytes()));
    }

    public function test_page_template_data_takes_bytes(): void
    {
        $page = $this->nodeFrom('<x-doc::page :template-data="$pdf">Body</x-doc::page>', ['pdf' => $this->template]);

        self::assertInstanceOf(Page::class, $page);
        self::assertSame('bytes', $page->templatePage()?->source()['kind']);
    }

    public function test_page_template_and_size_do_not_mix(): void
    {
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('takes its size from the template');

        $this->nodeFrom('<x-doc::page template="certificate.pdf" size="a4">Body</x-doc::page>');
    }

    public function test_overlay_slot_takes_places(): void
    {
        $page = $this->nodeFrom(<<<'BLADE'
            <x-doc::page template="certificate.pdf">
                <x-slot:overlay>
                    <x-doc::place x="50%" y="42%" anchor="top"><x-doc::text size="24pt">{{ $name }}</x-doc::text></x-doc::place>
                    <x-doc::place x="85%" y="80%" anchor="center" width="12%"><x-doc::qr-code value="https://example.com/b/1" width="100%" /></x-doc::place>
                </x-slot:overlay>
            </x-doc::page>
            BLADE, ['name' => 'Ada Lovelace']);

        self::assertInstanceOf(Page::class, $page);
        $places = $page->overlayPlaces();
        self::assertCount(2, $places);
        self::assertEquals(Percent::of(50), $places[0]->x());
        self::assertEquals(Percent::of(42), $places[0]->y());
        self::assertSame(Anchor::Top, $places[0]->anchorPoint());
        self::assertNull($places[0]->boxWidth());
        self::assertSame(Anchor::Center, $places[1]->anchorPoint());
        self::assertEquals(Percent::of(12), $places[1]->boxWidth());
        self::assertInstanceOf(QrCode::class, $places[1]->children()[0]);

        $words = $this->pdfText(Document::make($page)->toPdf()->bytes());
        self::assertStringContainsString('Ada Lovelace', $words);
        self::assertStringContainsString('First', $words);
    }

    public function test_place_defaults_to_the_top_left_corner_and_takes_lengths(): void
    {
        $place = $this->placeFrom('<x-doc::place x="20mm" y="1in">Hi</x-doc::place>');

        self::assertSame(Anchor::TopLeft, $place->anchorPoint());
        self::assertEquals(\Papercut\mm(20), $place->x());
        self::assertEquals(\Papercut\in(1), $place->y());
    }

    public function test_place_needs_x_and_y(): void
    {
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('requires x and y');

        $this->placeFrom('<x-doc::place x="10%">Hi</x-doc::place>');
    }

    public function test_place_refuses_an_unknown_anchor(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessage('top-left, top, top-right, left, center, right, bottom-left, bottom, bottom-right');

        $this->placeFrom('<x-doc::place x="0" y="0" anchor="middle">Hi</x-doc::place>');
    }

    public function test_place_refuses_a_length_without_a_unit(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessage('"12pt"');

        $this->placeFrom('<x-doc::place x="12" y="0">Hi</x-doc::place>');
    }

    public function test_the_overlay_slot_only_takes_places(): void
    {
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('The overlay slot of <x-doc::page> takes only <x-doc::place> tags');

        $this->nodeFrom('<x-doc::page><x-slot:overlay><x-doc::text>Loose</x-doc::text></x-slot:overlay> Body</x-doc::page>');
    }

    public function test_a_place_outside_the_overlay_is_refused(): void
    {
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('<x-doc::place> only works in the overlay slot of <x-doc::page>');

        $this->nodeFrom('<x-doc::page><x-doc::place x="0" y="0">Hi</x-doc::place></x-doc::page>');
    }

    private function placeFrom(string $blade): Place
    {
        $page = $this->nodeFrom('<x-doc::page><x-slot:overlay>' . $blade . '</x-slot:overlay> Body</x-doc::page>');
        self::assertInstanceOf(Page::class, $page);

        return $page->overlayPlaces()[0];
    }
}
