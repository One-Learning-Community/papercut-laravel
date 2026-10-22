<?php

declare(strict_types=1);

namespace Papercut\Laravel\Tests\Unit;

use Papercut\Laravel\Exception\InvalidStructure;
use Papercut\Laravel\Rendering\{NodeRegistry, Segments};
use Papercut\Text;
use Papercut\Laravel\Tests\TestCase;

final class NodeRegistryTest extends TestCase
{
    public function test_markers_round_trip_with_text(): void
    {
        $r = new NodeRegistry();
        $r->scope(function () use ($r) {
            $a = Text::make('a');
            $b = Text::make('b');
            $html = '  ' . $r->register($a) . "\n hello " . $r->register($b);
            $segs = $r->segments($html);
            self::assertSame($a, $segs[1]);
            self::assertSame("\n hello ", $segs[2]);
            self::assertSame($b, $segs[3]);
            self::assertSame(0, $r->remaining());
        });
    }

    public function test_marker_format_is_printable(): void
    {
        $r = new NodeRegistry();
        $r->scope(function () use ($r) {
            self::assertMatchesRegularExpression('/^\[\[pc:[0-9a-f]{16}:\d+\]\]$/', (string) $r->register(Text::make('x')));
        });
    }

    public function test_foreign_nonce_stays_literal(): void
    {
        $r = new NodeRegistry();
        $r->scope(function () use ($r) {
            $segs = $r->segments('x [[pc:0000000000000000:1]] y');
            self::assertSame(['x [[pc:0000000000000000:1]] y'], $segs);
        });
    }

    public function test_nested_scopes_are_isolated(): void
    {
        $r = new NodeRegistry();
        $r->scope(function () use ($r) {
            $outer = (string) $r->register(Text::make('outer'));
            $r->scope(function () use ($r, $outer) {
                self::assertSame([$outer], $r->segments($outer));
            });
            self::assertInstanceOf(Text::class, $r->segments($outer)[0]);
        });
    }

    public function test_register_outside_scope_fails(): void
    {
        $this->expectException(InvalidStructure::class);
        (new NodeRegistry())->register(Text::make('x'));
    }

    public function test_node_taken_twice_fails(): void
    {
        $r = new NodeRegistry();
        $this->expectException(InvalidStructure::class);
        $r->scope(function () use ($r) {
            $m = (string) $r->register(Text::make('x'));
            $r->segments($m);
            $r->segments($m);
        });
    }

    public function test_segments_helpers(): void
    {
        $t = Text::make('x');
        self::assertCount(2, Segments::nodes(["\n  ", $t, ' more text '], 'column'));
        self::assertSame('a <b>b</b>', Segments::text(['a <b>b</b>'], 'text'));
        $this->expectException(InvalidStructure::class);
        Segments::text(['a', $t], 'text');
    }
}
