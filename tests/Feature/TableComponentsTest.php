<?php

declare(strict_types=1);

namespace Papercut\Laravel\Tests\Feature;

use Papercut\Exception\InvalidValue;
use Papercut\Table;
use Papercut\Laravel\Exception\InvalidStructure;
use Papercut\Laravel\Tests\TestCase;

final class TableComponentsTest extends TestCase
{
    private const string TABLE = <<<'BLADE'
<x-doc::table columns="3fr 25mm 20mm" :repeat-header="false">
  <x-doc::thead><x-doc::th>Course</x-doc::th><x-doc::th>Credits</x-doc::th><x-doc::th>Grade</x-doc::th></x-doc::thead>
  @foreach ($courses as $c)
    <x-doc::tr>
      <x-doc::td>{{ $c[0] }}</x-doc::td>
      <x-doc::td>{{ $c[1] }}</x-doc::td>
      <x-doc::td align="right" colspan="1">{{ $c[2] }}</x-doc::td>
    </x-doc::tr>
  @endforeach
</x-doc::table>
BLADE;

    public function test_table_from_blade(): void
    {
        $t = $this->nodeFrom(self::TABLE, ['courses' => [['Math', 4, 'A'], ['Art', 2, 'B']]]);
        self::assertInstanceOf(Table::class, $t);
        self::assertNotNull($t->trackList());
        self::assertCount(3, $t->trackList()->tracks);
        self::assertCount(1, $t->headerRows());
        self::assertTrue($t->headerRows()[0]->cells()[0]->header());
        self::assertCount(2, $t->bodyRows());
        self::assertFalse($t->repeatsHeader());
    }

    public function test_thead_with_multiple_rows(): void
    {
        $t = $this->nodeFrom('<x-doc::table><x-doc::thead><x-doc::tr><x-doc::th>A</x-doc::th></x-doc::tr><x-doc::tr><x-doc::th>B</x-doc::th></x-doc::tr></x-doc::thead></x-doc::table>');
        self::assertInstanceOf(Table::class, $t);
        self::assertCount(2, $t->headerRows());
        self::assertTrue($t->repeatsHeader());
    }

    public function test_repeat_header_presence_and_string_false(): void
    {
        $t = $this->nodeFrom('<x-doc::table repeat-header />');
        self::assertInstanceOf(Table::class, $t);
        self::assertTrue($t->repeatsHeader());
        $t = $this->nodeFrom('<x-doc::table repeat-header="false" />');
        self::assertInstanceOf(Table::class, $t);
        self::assertFalse($t->repeatsHeader());
    }

    public function test_colspan_and_align(): void
    {
        $t = $this->nodeFrom('<x-doc::table><x-doc::tr><x-doc::td colspan="2" align="right" padding="2mm">x</x-doc::td></x-doc::tr></x-doc::table>');
        self::assertInstanceOf(Table::class, $t);
        self::assertSame(2, $t->bodyRows()[0]->cells()[0]->span());
    }

    public function test_td_and_th_take_rowspan(): void
    {
        $t = $this->nodeFrom('<x-doc::table><x-doc::tr><x-doc::td rowspan="2">a</x-doc::td><x-doc::th rowspan="3">b</x-doc::th></x-doc::tr></x-doc::table>');
        self::assertInstanceOf(Table::class, $t);
        $cells = $t->bodyRows()[0]->cells();
        self::assertSame(2, $cells[0]->rowsSpanned());
        self::assertSame(3, $cells[1]->rowsSpanned());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('badRowspans')]
    public function test_bad_rowspan_throws(string $value, string $tag): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessage('rowspan');
        $this->expectExceptionMessage($tag);
        $this->nodeFrom("<x-doc::table><x-doc::tr><x-doc::$tag rowspan=\"$value\">x</x-doc::$tag></x-doc::tr></x-doc::table>");
    }

    /** @return iterable<string, array{string, string}> */
    public static function badRowspans(): iterable
    {
        yield 'zero on td' => ['0', 'td'];
        yield 'text on th' => ['x', 'th'];
    }

    /** @return iterable<string, array{string, string}> */
    public static function misplaced(): iterable
    {
        yield 'td in column' => ['<x-doc::column><x-doc::td>x</x-doc::td></x-doc::column>', '<x-doc::td> must be inside <x-doc::tr>'];
        yield 'tr in column' => ['<x-doc::column><x-doc::tr><x-doc::td>x</x-doc::td></x-doc::tr></x-doc::column>', '<x-doc::tr> must be inside <x-doc::table>'];
        yield 'thead in column' => ['<x-doc::column><x-doc::thead><x-doc::th>x</x-doc::th></x-doc::thead></x-doc::column>', '<x-doc::thead> must be inside <x-doc::table>'];
        yield 'text in tr' => ['<x-doc::table><x-doc::tr>loose</x-doc::tr></x-doc::table>', '<x-doc::tr> can only contain <x-doc::td> or <x-doc::th>'];
        yield 'column in table' => ['<x-doc::table><x-doc::column /></x-doc::table>', '<x-doc::table> can only contain'];
        yield 'mixed thead' => ['<x-doc::table><x-doc::thead><x-doc::th>a</x-doc::th><x-doc::tr><x-doc::th>b</x-doc::th></x-doc::tr></x-doc::thead></x-doc::table>', '<x-doc::thead> can only contain'];
        yield 'two theads' => ['<x-doc::table><x-doc::thead><x-doc::th>a</x-doc::th></x-doc::thead><x-doc::thead><x-doc::th>b</x-doc::th></x-doc::thead></x-doc::table>', '<x-doc::table> can only contain'];
        yield 'thead after body' => ['<x-doc::table><x-doc::tr><x-doc::td>a</x-doc::td></x-doc::tr><x-doc::thead><x-doc::th>b</x-doc::th></x-doc::thead></x-doc::table>', '<x-doc::table> can only contain'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('misplaced')]
    public function test_structure_errors(string $blade, string $message): void
    {
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage($message);
        $this->nodeFrom($blade);
    }
}
