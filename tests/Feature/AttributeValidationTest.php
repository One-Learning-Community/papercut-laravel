<?php

declare(strict_types=1);

namespace Quoin\Laravel\Tests\Feature;

use Quoin\Exception\InvalidValue;
use Quoin\Export\{HtmlOptions, PdfOptions};
use Quoin\{Document, Page, Table};
use Quoin\Laravel\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/** Blade attributes get the same checks as the builder setters (D3, D10). */
final class AttributeValidationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        set_error_handler(static function (int $no, string $msg, string $file, int $line): never {
            throw new \ErrorException($msg, 0, $no, $file, $line);
        });
    }

    protected function tearDown(): void
    {
        restore_error_handler();
        parent::tearDown();
    }

    /** @return iterable<string, array{string, string}> */
    public static function zeroSizes(): iterable
    {
        yield 'image width' => ['<x-doc::image :data="$b" width="0pt" />', 'width'];
        yield 'image height' => ['<x-doc::image :data="$b" height="0" />', 'height'];
        yield 'qr width' => ['<x-doc::qr-code value="a" width="0pt" />', 'width'];
    }

    #[DataProvider('zeroSizes')]
    public function test_zero_image_or_qr_size_throws_naming_it(string $blade, string $attribute): void
    {
        try {
            $this->nodeFrom($blade, ['b' => 'raw']);
            self::fail('expected InvalidValue');
        } catch (InvalidValue $e) {
            self::assertStringContainsString($attribute, $e->getMessage());
            self::assertStringContainsString('more than 0', $e->getMessage());
        }
    }

    public function test_huge_colspan_is_clamped_like_the_builder(): void
    {
        $table = $this->nodeFrom('<x-doc::table><x-doc::tr><x-doc::td colspan="99999999999">x</x-doc::td></x-doc::tr></x-doc::table>');
        self::assertInstanceOf(Table::class, $table);
        $doc = Document::make(Page::a4()->body($table));
        $messages = [];
        $started = microtime(true);
        $doc->toPdf(new PdfOptions(onWarning: function (string $m) use (&$messages): void {
            $messages[] = $m;
        }, cacheDir: false));
        self::assertLessThan(10.0, microtime(true) - $started);
        self::assertCount(1, $messages);
        self::assertStringContainsString('colspan runs past column 1000; clamped', $messages[0]);
        self::assertSame(1000, preg_match_all('/<col[ >]/', $doc->toHtml(new HtmlOptions())->bytes()));
    }

    /** @return iterable<string, array{string, string}> */
    public static function negatives(): iterable
    {
        yield 'width' => ['<x-doc::column width="-10pt">x</x-doc::column>', 'width'];
        yield 'height percent' => ['<x-doc::column height="-5%">x</x-doc::column>', 'height'];
        yield 'padding' => ['<x-doc::column padding="2pt -1pt">x</x-doc::column>', 'padding'];
        yield 'border width' => ['<x-doc::column border-width="-1pt">x</x-doc::column>', 'border-width'];
        yield 'radius' => ['<x-doc::column radius="-3pt">x</x-doc::column>', 'radius'];
        yield 'gap' => ['<x-doc::column gap="-3pt">x</x-doc::column>', 'gap'];
        yield 'opacity' => ['<x-doc::column opacity="1.5">x</x-doc::column>', 'opacity'];
        yield 'grow' => ['<x-doc::column grow="-1">x</x-doc::column>', 'grow'];
    }

    #[DataProvider('negatives')]
    public function test_bad_attribute_throws_naming_it(string $blade, string $attribute): void
    {
        try {
            $this->nodeFrom($blade);
            self::fail('expected InvalidValue');
        } catch (InvalidValue $e) {
            self::assertStringContainsString($attribute, $e->getMessage());
        }
    }

    public function test_legitimate_values_still_build(): void
    {
        $node = $this->nodeFrom('<x-doc::column width="0pt" padding="0" margin="-4pt 0 0 0" opacity="0" grow="0" letter-spacing="-0.5pt">x</x-doc::column>');
        self::assertInstanceOf(\Quoin\View::class, $node);
    }

    public function test_bound_null_means_not_set_and_array_throws(): void
    {
        $node = $this->nodeFrom('<x-doc::column :width="$w" padding="2pt">x</x-doc::column>', ['w' => null]);
        self::assertInstanceOf(\Quoin\View::class, $node);
        self::assertNull($node->style()->width);

        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessage('width');
        $this->nodeFrom('<x-doc::column :width="$w">x</x-doc::column>', ['w' => ['1pt']]);
    }
}
