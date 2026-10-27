<?php

declare(strict_types=1);

namespace Papercut\Laravel\Tests\Feature;

use Papercut\Exception\ImageUnreadable;
use Papercut\Laravel\Exception\InvalidStructure;
use Papercut\Laravel\Facades\Papercut;
use Papercut\Laravel\Tests\TestCase;
use Papercut\Svg;
use Papercut\Value\Length;

final class SvgComponentTest extends TestCase
{
    private const string LOGO = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 20"><title>Acme</title><rect width="40" height="20"/></svg>';

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/papercut-svg-' . getmypid() . '-' . bin2hex(random_bytes(3));
        mkdir($this->dir . '/img', 0777, true);
        file_put_contents($this->dir . '/img/logo.svg', self::LOGO);
        file_put_contents($this->dir . '/secret.svg', self::LOGO);
        config(['papercut.image_path' => $this->dir . '/img']);
    }

    protected function tearDown(): void
    {
        foreach (['/img/logo.svg', '/secret.svg'] as $file) {
            @unlink($this->dir . $file);
        }
        @rmdir($this->dir . '/img');
        @rmdir($this->dir);
        parent::tearDown();
    }

    public function test_src_is_resolved_inside_the_image_path(): void
    {
        $n = $this->nodeFrom('<x-doc::svg src="logo.svg" width="40mm" />');

        self::assertInstanceOf(Svg::class, $n);
        self::assertSame(['kind' => 'file', 'value' => $this->dir . '/img/logo.svg'], $n->source());
        self::assertInstanceOf(Length::class, $n->style()->width);
        self::assertEqualsWithDelta(\Papercut\mm(40)->points, $n->style()->width->points, 1e-9);
    }

    public function test_data_takes_markup(): void
    {
        $n = $this->nodeFrom('<x-doc::svg :data="$svg" />', ['svg' => self::LOGO]);

        self::assertInstanceOf(Svg::class, $n);
        self::assertSame(['kind' => 'string', 'value' => self::LOGO], $n->source());
    }

    public function test_markup_can_be_written_inside_the_tag(): void
    {
        $n = $this->nodeFrom('<x-doc::svg height="10mm">' . self::LOGO . '</x-doc::svg>');

        self::assertInstanceOf(Svg::class, $n);
        self::assertSame('Acme', $n->document()->title);
    }

    public function test_markup_from_a_variable_inside_the_tag(): void
    {
        $n = $this->nodeFrom('<x-doc::svg>{!! $svg !!}</x-doc::svg>', ['svg' => self::LOGO]);

        self::assertInstanceOf(Svg::class, $n);
        self::assertSame('Acme', $n->document()->title);
    }

    public function test_it_needs_exactly_one_source(): void
    {
        foreach (['<x-doc::svg />', '<x-doc::svg src="logo.svg" :data="$svg" />', '<x-doc::svg src="logo.svg">' . self::LOGO . '</x-doc::svg>'] as $blade) {
            try {
                $this->nodeFrom($blade, ['svg' => self::LOGO]);
                self::fail("$blade should be refused");
            } catch (InvalidStructure $e) {
                self::assertStringContainsString('requires exactly one of src, data or SVG markup inside the tag', $e->getMessage());
            }
        }
    }

    public function test_a_path_outside_the_image_path_is_refused(): void
    {
        $this->expectException(ImageUnreadable::class);
        $this->expectExceptionMessage('Image path escapes the image directory: ../secret.svg');

        $this->nodeFrom('<x-doc::svg src="../secret.svg" />');
    }

    public function test_a_missing_file_is_named(): void
    {
        $this->expectException(ImageUnreadable::class);
        $this->expectExceptionMessage('Image not found: ' . $this->dir . '/img/missing.svg');

        $this->nodeFrom('<x-doc::svg src="missing.svg" />');
    }

    public function test_bad_markup_names_the_tag(): void
    {
        $this->expectException(ImageUnreadable::class);
        $this->expectExceptionMessage('Cannot read SVG: it is not well-formed XML');
        $this->expectExceptionMessage('on <x-doc::svg>');

        $this->nodeFrom('<x-doc::svg data="<svg" />');
    }

    public function test_it_renders_to_pdf_and_html(): void
    {
        \Illuminate\Support\Facades\View::addNamespace('svgtest', $this->dir);
        file_put_contents($this->dir . '/doc.blade.php', '<x-doc::document><x-doc::page><x-doc::svg src="logo.svg" width="40mm" /></x-doc::page></x-doc::document>');
        try {
            $pdf = Papercut::view('svgtest::doc')->pdf()->toString();
            self::assertStringStartsWith('%PDF-', $pdf);
            $html = (string) Papercut::view('svgtest::doc')->html();
            self::assertStringContainsString('<img src="data:image/svg+xml;base64,', $html);
            self::assertStringContainsString('alt="Acme"', $html);
        } finally {
            unlink($this->dir . '/doc.blade.php');
        }
    }
}
