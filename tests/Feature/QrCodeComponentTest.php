<?php

declare(strict_types=1);

namespace Papercut\Laravel\Tests\Feature;

use Papercut\Barcode\{ErrorCorrection, QrEyes, QrGradient, QrShape};
use Papercut\Exception\{InvalidValue, UnknownAttribute};
use Papercut\Laravel\Exception\InvalidStructure;
use Papercut\Laravel\Tests\TestCase;
use Papercut\QrCode;
use Papercut\Value\Color;
use PHPUnit\Framework\Attributes\DataProvider;

final class QrCodeComponentTest extends TestCase
{
    public function test_value_level_quiet_zone_and_style(): void
    {
        $n = $this->nodeFrom('<x-doc::qr-code value="https://acme.test/pay/142" level="H" quiet-zone="2" width="30mm" color="indigo-700" />');

        self::assertInstanceOf(QrCode::class, $n);
        self::assertSame('https://acme.test/pay/142', $n->value());
        self::assertSame(ErrorCorrection::High, $n->level());
        self::assertSame(2, $n->quietZoneModules());
        self::assertEqualsWithDelta(\Papercut\mm(30)->points, $n->style()->width instanceof \Papercut\Value\Length ? $n->style()->width->points : null, 1e-9);
    }

    public function test_bound_values_and_default_level(): void
    {
        $n = $this->nodeFrom('<x-doc::qr-code :value="$url" />', ['url' => 'https://acme.test/x']);

        self::assertInstanceOf(QrCode::class, $n);
        self::assertSame(ErrorCorrection::Medium, $n->level());
        self::assertSame(QrCode::DEFAULT_QUIET_ZONE, $n->quietZoneModules());
    }

    public function test_level_accepts_names(): void
    {
        $n = $this->nodeFrom('<x-doc::qr-code value="x" level="quartile" />');

        self::assertInstanceOf(QrCode::class, $n);
        self::assertSame(ErrorCorrection::Quartile, $n->level());
    }

    public function test_a_value_is_required(): void
    {
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('requires a value');

        $this->nodeFrom('<x-doc::qr-code />');
    }

    public function test_content_is_rejected(): void
    {
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('use the value attribute');

        $this->nodeFrom('<x-doc::qr-code value="x">https://acme.test</x-doc::qr-code>');
    }

    public function test_a_bad_level_is_explained(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessage('expected L, M, Q or H');

        $this->nodeFrom('<x-doc::qr-code value="x" level="extreme" />');
    }

    public function test_a_bad_quiet_zone_is_explained(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessage('whole number of modules');

        $this->nodeFrom('<x-doc::qr-code value="x" quiet-zone="-1" />');
    }

    public function test_unknown_attributes_suggest_the_right_one(): void
    {
        $this->expectException(UnknownAttribute::class);
        $this->expectExceptionMessage('Did you mean "value"');

        $this->nodeFrom('<x-doc::qr-code valeu="x" />');
    }

    public function test_shape_eyes_and_colors_style_the_code(): void
    {
        $n = $this->nodeFrom('<x-doc::qr-code value="x" shape="dots" eyes="dot" eye-color="#e11d48" gradient="#4f46e5 rgb(14, 116, 144)" gradient-type="radial" />');

        self::assertInstanceOf(QrCode::class, $n);
        $design = $n->design();
        self::assertNotNull($design);
        self::assertSame(QrShape::Dots, $design->shape);
        self::assertSame(QrEyes::Dot, $design->eyes);
        self::assertEquals(Color::hex('#e11d48'), $design->eyeColor);
        self::assertEquals([Color::hex('#4f46e5'), Color::rgb(14, 116, 144)], [$design->gradientFrom, $design->gradientTo]);
        self::assertSame(QrGradient::Radial, $design->gradientType);
    }

    public function test_dots_raise_the_level_to_quartile(): void
    {
        $n = $this->nodeFrom('<x-doc::qr-code value="x" level="L" shape="dots" />');

        self::assertInstanceOf(QrCode::class, $n);
        self::assertSame(ErrorCorrection::Quartile, $n->level());
    }

    public function test_a_plain_tag_stays_plain(): void
    {
        $n = $this->nodeFrom('<x-doc::qr-code value="x" />');

        self::assertInstanceOf(QrCode::class, $n);
        self::assertNull($n->design());
    }

    public function test_the_gradient_is_diagonal_by_default(): void
    {
        $n = $this->nodeFrom('<x-doc::qr-code value="x" gradient="#000 #333" />');

        self::assertInstanceOf(QrCode::class, $n);
        self::assertSame(QrGradient::Diagonal, $n->design()?->gradientType);
    }

    /** @return iterable<string, array{string, string}> */
    public static function badDesigns(): iterable
    {
        yield 'shape' => ['shape="hearts"', 'expected square, rounded or dots'];
        yield 'eyes' => ['eyes="star"', 'expected square, dot or pointy'];
        yield 'one gradient color' => ['gradient="#000"', 'expected two colors'];
        yield 'gradient type' => ['gradient="#000 #333" gradient-type="spiral"', 'expected horizontal, vertical, diagonal, inverse-diagonal or radial'];
        yield 'type without gradient' => ['gradient-type="radial"', 'needs a gradient'];
    }

    #[DataProvider('badDesigns')]
    public function test_bad_design_attributes_are_explained(string $attrs, string $message): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessage($message);

        $this->nodeFrom('<x-doc::qr-code value="x" ' . $attrs . ' />');
    }

    public function test_a_styled_tag_renders_to_pdf(): void
    {
        $n = $this->nodeFrom('<x-doc::qr-code value="x" shape="rounded" gradient="#4f46e5 #0e7490" />');
        self::assertInstanceOf(QrCode::class, $n);
        $pdf = \Papercut\Laravel\Facades\Papercut::make(\Papercut\Document::make(\Papercut\Page::a4()->body($n)))->pdf()->toString();

        self::assertStringStartsWith('%PDF-', $pdf);
        self::assertStringContainsString('/ShadingType', $pdf);
    }

    public function test_it_renders_to_pdf(): void
    {
        $pdf = \Papercut\Laravel\Facades\Papercut::make(\Papercut\Document::make(\Papercut\Page::a4()->body(QrCode::make('x'))))->pdf()->toString();

        self::assertStringStartsWith('%PDF-', $pdf);
    }
}
