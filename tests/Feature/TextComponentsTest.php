<?php

declare(strict_types=1);

namespace Papercut\Laravel\Tests\Feature;

use Papercut\{Image, PageNumber, Text};
use Papercut\Exception\ImageUnreadable;
use Papercut\Laravel\Exception\InvalidStructure;
use Papercut\Laravel\Tests\TestCase;
use Papercut\Style\{ImageFit, TextAlign};

final class TextComponentsTest extends TestCase
{
    public function test_text_with_inline_markup_and_style(): void
    {
        $t = $this->nodeFrom("<x-doc::text size=\"18pt\" bold align=\"right\">\n  Hello <i>there</i>\n</x-doc::text>");
        self::assertInstanceOf(Text::class, $t);
        self::assertSame(18.0, $t->style()->fontSize?->points);
        self::assertSame(700, $t->style()->fontWeight);
        self::assertSame(TextAlign::Right, $t->style()->textAlign);
        self::assertSame(['Hello ', 'there'], array_map(fn ($s) => $s->text, $t->spanList()));
        self::assertTrue($t->spanList()[1]->style->italic);
    }

    public function test_escaped_user_data_is_not_markup(): void
    {
        $t = $this->nodeFrom('<x-doc::text>{{ $evil }}</x-doc::text>', ['evil' => '<b>hacked</b> & co']);
        self::assertCount(1, $t->spanList());
        self::assertSame('<b>hacked</b> & co', $t->spanList()[0]->text);
        self::assertNull($t->spanList()[0]->style->fontWeight);
    }

    public function test_headings_and_paragraph(): void
    {
        self::assertSame('h2', $this->nodeFrom('<x-doc::h2>Title</x-doc::h2>')->preset());
        self::assertSame('p', $this->nodeFrom('<x-doc::p>Body <b>bold</b></x-doc::p>')->preset());
    }

    public function test_block_inside_text_is_rejected(): void
    {
        $this->expectException(InvalidStructure::class);
        $this->nodeFrom('<x-doc::text>a <x-doc::spacer /></x-doc::text>');
    }

    public function test_page_number(): void
    {
        $n = $this->nodeFrom('<x-doc::page-number format="Page {page} of {total}" align="center" />');
        self::assertInstanceOf(PageNumber::class, $n);
        self::assertSame('Page {page} of {total}', $n->format());
        self::assertSame(TextAlign::Center, $n->style()->textAlign);
    }

    public function test_image_src_resolution_and_errors(): void
    {
        $dir = $this->tempDir('papercut-img');
        file_put_contents("$dir/logo.png", 'x');
        config(['papercut.image_path' => $dir]);
        $img = $this->nodeFrom('<x-doc::image src="logo.png" height="12mm" fit="cover" />');
        self::assertInstanceOf(Image::class, $img);
        self::assertSame(['kind' => 'file', 'value' => "$dir/logo.png"], $img->source());
        self::assertSame(ImageFit::Cover, $img->fitMode());
        self::assertSame('bytes', $this->nodeFrom('<x-doc::image :data="$b" />', ['b' => 'raw'])->source()['kind']);
        $this->expectException(ImageUnreadable::class);
        $this->nodeFrom('<x-doc::image src="missing.png" />');
    }

    public function test_image_requires_exactly_one_source(): void
    {
        $this->expectException(InvalidStructure::class);
        $this->nodeFrom('<x-doc::image />');
    }
}
