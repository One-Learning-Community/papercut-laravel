<?php

declare(strict_types=1);

namespace Papercut\Laravel\Tests\Feature;

use Papercut\Exception\InvalidValue;
use Papercut\Laravel\Exception\InvalidStructure;
use Papercut\Laravel\Facades\Papercut;
use Papercut\Laravel\Tests\TestCase;
use Papercut\Toc;

final class TocComponentTest extends TestCase
{
    public function test_own_attributes_build_a_toc(): void
    {
        $t = $this->nodeFrom('<x-doc::toc levels="1-3" title="Contents" :leaders="false" />');
        self::assertInstanceOf(Toc::class, $t);
        self::assertSame(1, $t->fromLevel());
        self::assertSame(3, $t->toLevel());
        self::assertSame('Contents', $t->titleText());
        self::assertFalse($t->hasLeaders());
    }

    public function test_defaults_and_string_false(): void
    {
        $t = $this->nodeFrom('<x-doc::toc leaders="false" />');
        self::assertFalse($t->hasLeaders());
        $d = $this->nodeFrom('<x-doc::toc />');
        self::assertTrue($d->hasLeaders());
        self::assertNull($d->titleText());
    }

    public function test_a_blank_title_is_no_title(): void
    {
        self::assertNull($this->nodeFrom('<x-doc::toc title="   " />')->titleText());
    }

    public function test_a_single_level_and_spaces(): void
    {
        $t = $this->nodeFrom('<x-doc::toc levels="2" />');
        self::assertSame([2, 2], [$t->fromLevel(), $t->toLevel()]);
        $s = $this->nodeFrom('<x-doc::toc levels=" 1 - 2 " />');
        self::assertSame([1, 2], [$s->fromLevel(), $s->toLevel()]);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('badLevels')]
    public function test_bad_levels_throw(string $levels): void
    {
        try {
            $this->nodeFrom('<x-doc::toc levels="' . $levels . '" />');
            self::fail('expected InvalidValue');
        } catch (InvalidValue $e) {
            self::assertStringContainsString('levels', $e->getMessage());
            self::assertStringContainsString('toc', $e->getMessage());
        }
    }

    /** @return array<string, array{string}> */
    public static function badLevels(): array
    {
        return ['letters' => ['a-b'], 'reversed' => ['3-1'], 'zero' => ['0'], 'too deep' => ['1-5']];
    }

    public function test_content_is_rejected(): void
    {
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('must not have content');
        $this->nodeFrom('<x-doc::toc>Contents</x-doc::toc>');
    }

    public function test_the_core_exception_type_catches_it(): void
    {
        $this->expectException(\Papercut\Exception\InvalidStructure::class);
        $this->nodeFrom('<x-doc::toc>Contents</x-doc::toc>');
    }

    public function test_heading_with_toc_false_is_left_out_of_the_pdf_contents(): void
    {
        $blade = <<<'BLADE'
            <x-doc::document>
              <x-doc::toc levels="1-2" />
              <x-doc::h2>Kept heading</x-doc::h2>
              <x-doc::h2 toc="false">Hidden heading</x-doc::h2>
            </x-doc::document>
            BLADE;
        $view = 'toc-' . getmypid() . '-' . md5($blade);
        $path = sys_get_temp_dir() . "/$view.blade.php";
        file_put_contents($path, $blade);
        \Illuminate\Support\Facades\View::addLocation(sys_get_temp_dir());
        try {
            $text = $this->pdfText(Papercut::view($view)->pdf()->toString());
        } finally {
            unlink($path);
        }
        self::assertSame(2, substr_count($text, 'Kept heading'));
        self::assertSame(1, substr_count($text, 'Hidden heading'));
    }
}
