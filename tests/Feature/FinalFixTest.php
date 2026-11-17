<?php

declare(strict_types=1);

namespace Quoin\Laravel\Tests\Feature;

use Quoin\Exception\{ImageUnreadable, InvalidValue, UnknownAttribute};
use Quoin\Laravel\Exception\InvalidStructure;
use Quoin\Laravel\Facades\Quoin;
use Quoin\Laravel\QuoinManager;
use Quoin\Laravel\Rendering\NodeRegistry;
use Quoin\Laravel\Tests\TestCase;
use Quoin\Text;

final class FinalFixTest extends TestCase
{
    public function test_whitespace_in_config_style_name_is_rejected(): void
    {
        config(['quoin.styles' => ['my style' => ['padding' => '4pt']]]);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("'my style'");
        $this->app()->make(QuoinManager::class)->pdfOptions();
    }

    public function test_bad_named_style_names_config_key(): void
    {
        config(['quoin.styles' => ['card' => ['bogus-attr' => 'x']]]);
        $this->expectException(UnknownAttribute::class);
        $this->expectExceptionMessage('in config quoin.styles.card');
        $this->app()->make(QuoinManager::class)->pdfOptions();
    }

    public function test_bad_theme_color_names_config_key(): void
    {
        config(['quoin.theme' => ['color' => 'not-a-color']]);
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessage('in config quoin.theme.color');
        $this->app()->make(QuoinManager::class)->pdfOptions();
    }

    public function test_theme_numbers_must_be_positive_numeric(): void
    {
        foreach (['font_size' => ['abc', 0, -3], 'line_height' => ['x', 0, -1.5]] as $key => $bad) {
            foreach ($bad as $value) {
                config(['quoin.theme' => [$key => $value]]);
                try {
                    (new QuoinManager($this->app()->make(NodeRegistry::class), $this->app()->make(\Quoin\Laravel\Preview\PreviewRegistry::class)))->pdfOptions();
                    self::fail("accepted $key=" . var_export($value, true));
                } catch (\InvalidArgumentException $e) {
                    self::assertStringContainsString("Invalid quoin.theme.$key:", $e->getMessage());
                }
            }
        }
    }

    public function test_mono_family_must_be_a_string(): void
    {
        foreach ([['Courier'], 12, true] as $value) {
            config(['quoin.theme' => ['mono_family' => $value]]);
            try {
                (new QuoinManager($this->app()->make(NodeRegistry::class), $this->app()->make(\Quoin\Laravel\Preview\PreviewRegistry::class)))->pdfOptions();
                self::fail('accepted mono_family=' . var_export($value, true));
            } catch (\InvalidArgumentException $e) {
                self::assertStringContainsString('Invalid quoin.theme.mono_family:', $e->getMessage());
            }
        }
    }

    public function test_mono_family_must_be_a_real_known_family(): void
    {
        foreach (['', 'monospace', 'Monospace', 'Nonesuch'] as $value) {
            config(['quoin.theme' => ['mono_family' => $value]]);
            try {
                (new QuoinManager($this->app()->make(NodeRegistry::class), $this->app()->make(\Quoin\Laravel\Preview\PreviewRegistry::class)))->pdfOptions();
                self::fail('accepted mono_family=' . var_export($value, true));
            } catch (\InvalidArgumentException $e) {
                self::assertStringContainsString('Invalid quoin.theme.mono_family:', $e->getMessage());
            }
        }
    }

    public function test_altered_marker_in_slot_is_reported(): void
    {
        try {
            Quoin::view('pdf.shouted')->pdf()->toString();
            self::fail('accepted');
        } catch (\Illuminate\View\ViewException $e) {
            self::assertStringContainsString('modified as a string (e.g. strtoupper($slot)) in view [pdf.shouted]', $e->getMessage());
        }
    }

    public function test_current_nonce_marker_with_unknown_id_fails(): void
    {
        $r = new NodeRegistry();
        $this->expectException(InvalidStructure::class);
        $r->scope(function () use ($r): void {
            $m = (string) $r->register(Text::make('x'));
            $forged = (string) preg_replace('/:\d+\]\]$/', ':99]]', $m);
            $r->segments($forged);
        });
    }

    public function test_foreign_nonce_marker_is_never_resolved(): void
    {
        $r = new NodeRegistry();
        $out = $r->scope(fn (): array => $r->segments('[[pc:0123456789abcdef:1]]'));
        self::assertSame(['[[pc:0123456789abcdef:1]]'], $out);
    }

    public function test_user_text_shaped_like_a_marker_renders_literally(): void
    {
        $n = $this->nodeFrom('<x-doc::text>Comment: {{ $c }}</x-doc::text>', ['c' => '[[pc:0123456789abcdef:1]]']);
        self::assertStringContainsString('[[pc:0123456789abcdef:1]]', implode('', array_map(fn ($s) => $s->text, $n->spanList())));
    }

    public function test_a_wrong_component_child_gets_no_stale_hint(): void
    {
        foreach ([
            '<x-doc::choices><x-doc::choice>a</x-doc::choice><x-doc::text>b</x-doc::text></x-doc::choices>',
            '<x-doc::tr><x-doc::td>a</x-doc::td><x-doc::p>b</x-doc::p></x-doc::tr>',
        ] as $blade) {
            try {
                $this->nodeFrom($blade);
                self::fail('accepted');
            } catch (InvalidStructure $e) {
                self::assertStringContainsString('can only contain', $e->getMessage());
                self::assertStringNotContainsString('node marker', $e->getMessage());
            }
        }
    }

    public function test_a_stale_marker_in_choices_names_the_cause(): void
    {
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessageMatches('/can only contain.*cache the data instead/');
        $this->nodeFrom('<x-doc::choices>{{ $c }}</x-doc::choices>', ['c' => '[[pc:0123456789abcdef:1]]']);
    }

    public function test_output_twice_message(): void
    {
        try {
            Quoin::view('pdf.twice')->pdf()->toString();
            self::fail('accepted');
        } catch (\Illuminate\View\ViewException $e) {
            self::assertStringContainsString('was output twice (is {{ $slot }} echoed more than once?)', $e->getMessage());
        }
    }

    public function test_image_error_names_src(): void
    {
        config(['quoin.image_path' => sys_get_temp_dir()]);
        try {
            $this->nodeFrom('<x-doc::image src="nope-missing.png" />');
            self::fail('accepted');
        } catch (ImageUnreadable $e) {
            self::assertStringEndsWith(' on <x-doc::image src="nope-missing.png">', $e->getMessage());
        }
    }

    public function test_spacer_size_and_grow_conflict(): void
    {
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('<x-doc::spacer> takes size or grow, not both');
        $this->nodeFrom('<x-doc::spacer size="5mm" grow="2" />');
    }

    public function test_save_failure_throws(): void
    {
        $pending = Quoin::make(\Quoin\Document::make(\Quoin\Page::named('a4')->body('hello')))->pdf();
        $path = sys_get_temp_dir() . '/quoin-no-such-dir-' . bin2hex(random_bytes(3)) . '/out.pdf';
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Directory does not exist: ' . dirname($path));
        @$pending->save($path);
    }

    public function test_app_component_forwards_merged_attributes(): void
    {
        $col = $this->nodeFrom('<x-boxed background="slate-50"><x-doc::text>a</x-doc::text></x-boxed>');
        self::assertInstanceOf(\Quoin\View::class, $col);
        $dump = print_r($col, true);
        self::assertMatchesRegularExpression('/\[background\] => Quoin.Value.Color.*\[r\] => 248/s', $dump, 'caller attribute forwarded');
        self::assertMatchesRegularExpression('/\[padding\] => Quoin.Value.Edges.*\[points\] => 4\b/s', $dump, 'merged default padding');
        $plain = print_r($this->nodeFrom('<x-boxed><x-doc::text>a</x-doc::text></x-boxed>'), true);
        $over = print_r($this->nodeFrom('<x-boxed padding="9pt"><x-doc::text>a</x-doc::text></x-boxed>'), true);
        self::assertNotSame($plain, $over, 'caller padding overrides the merged default');
        self::assertNotSame($plain, $dump);
    }

    public function test_extends_section_stack_layout_renders(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'pc-ext') . '.pdf';
        file_put_contents($file, Quoin::view('pdf.extended')->pdf()->toString());
        exec('pdftotext ' . escapeshellarg($file) . ' - 2>/dev/null', $out);
        unlink($file);
        $text = implode("\n", $out);
        self::assertStringContainsString('Layout title', $text);
        self::assertStringContainsString('Body text', $text);
    }
}
