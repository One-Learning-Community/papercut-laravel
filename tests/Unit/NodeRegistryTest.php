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

    public function test_a_marker_from_another_render_fails_with_the_usual_cause(): void
    {
        $r = new NodeRegistry();
        $stale = $r->scope(fn () => (string) $r->register(Text::make('a')));

        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage("can't be cached or reused across renders");
        $r->scope(fn () => $r->segments("x $stale y"));
    }

    public function test_a_never_issued_marker_stays_literal_text(): void
    {
        $r = new NodeRegistry();
        self::assertSame(['[[pc:0123456789abcdef:1]]'], $r->segments('[[pc:0123456789abcdef:1]]'));
        $r->scope(function () use ($r) {
            self::assertSame(['x [[pc:0123456789abcdef:1]] y'], $r->segments('x [[pc:0123456789abcdef:1]] y'));
        });
    }

    public function test_only_the_most_recent_nonces_are_remembered(): void
    {
        $r = new NodeRegistry();
        $first = $r->scope(fn () => (string) $r->register(Text::make('a')));
        for ($i = 0; $i < 1000; $i++) {
            $r->scope(fn () => null);
        }
        self::assertSame([$first], $r->segments($first));
    }

    public function test_the_hint_is_added_only_for_marker_shaped_text(): void
    {
        self::assertStringContainsString('cache the data', NodeRegistry::staleHint(Text::markup('a [[pc:0123456789abcdef:1]]')));
        self::assertSame('', NodeRegistry::staleHint(Text::markup('plain')));
        self::assertSame('', NodeRegistry::staleHint(null));
    }

    public function test_a_marker_outside_any_scope_fails_too(): void
    {
        $r = new NodeRegistry();
        $stale = $r->scope(fn () => (string) $r->register(Text::make('a')));

        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('from another or an earlier render');
        $r->segments($stale);
    }

    public function test_text_that_only_resembles_a_marker_stays_literal(): void
    {
        $r = new NodeRegistry();
        $r->scope(function () use ($r) {
            foreach (['[[pc:0000000000000000:1]]', '[[pc:0000:1]]', '[[pc:0000000000000000]]', '[[pc:0000000000000000:x]]', '[[pc:000000000000000g:1]]', '[pc:0000000000000000:1]', 'x [[pc:00000000000000000:1]] y'] as $text) {
                self::assertSame([$text], $r->segments($text));
            }
        });
    }

    public function test_nested_scopes_each_resolve_only_their_own_markers(): void
    {
        $r = new NodeRegistry();
        $r->scope(function () use ($r) {
            $outer = (string) $r->register(Text::make('outer'));
            $r->scope(function () use ($r, $outer) {
                $inner = (string) $r->register(Text::make('inner'));
                self::assertInstanceOf(Text::class, $r->segments($inner)[0]);
                try {
                    $r->segments($outer);
                    self::fail('the outer marker is foreign inside the inner scope');
                } catch (InvalidStructure $e) {
                    self::assertStringContainsString('from another or an earlier render', $e->getMessage());
                }
            });
            self::assertInstanceOf(Text::class, $r->segments($outer)[0]);
        });
    }

    public function test_a_failed_split_throws_with_the_pcre_error(): void
    {
        $r = new NodeRegistry();
        $old = ini_set('pcre.backtrack_limit', '1');
        try {
            $this->expectException(InvalidStructure::class);
            $this->expectExceptionMessageMatches('/could not be searched for papercut nodes/');
            $r->scope(fn () => $r->segments(str_repeat('a [[pc:0000000000000000:1]] ', 50)));
        } finally {
            ini_set('pcre.backtrack_limit', (string) $old);
        }
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
