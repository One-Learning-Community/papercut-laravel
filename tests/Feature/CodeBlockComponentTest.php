<?php

declare(strict_types=1);

namespace Papercut\Laravel\Tests\Feature;

use Papercut\Decorator\CodeBlock;
use Papercut\Laravel\Facades\Papercut;
use Papercut\Laravel\PapercutManager;
use Papercut\Laravel\Tests\TestCase;
use Papercut\{Text, View};

final class CodeBlockComponentTest extends TestCase
{
    private const string FIX = __DIR__ . '/../../../core/tests/fixtures/code';

    private function fresh(): void
    {
        app()->forgetInstance(PapercutManager::class);
        Papercut::clearResolvedInstances();
    }

    /** @param array<string, mixed> $data */
    private function rendered(string $blade, array $data = []): View
    {
        $node = $this->nodeFrom($blade, $data);
        self::assertInstanceOf(CodeBlock::class, $node);

        return $node->render();
    }

    /** @return list<string> */
    private static function texts(View $view): array
    {
        $out = [];
        foreach ($view->children() as $child) {
            if ($child instanceof Text) {
                $out[] = implode('', array_map(static fn ($s): string => $s->lineBreak ? "\n" : $s->text, $child->spanList()));
            }
        }

        return $out;
    }

    /** @return list<string> */
    private static function colours(View $view): array
    {
        $out = [];
        foreach ($view->children() as $child) {
            if ($child instanceof Text) {
                foreach ($child->spanList() as $span) {
                    if ($span->style->color !== null) {
                        $out[] = (string) $span->style->color;
                    }
                }
            }
        }

        return $out;
    }

    public function test_language_pre_renders_a_code_block(): void
    {
        $view = $this->rendered('<x-doc::pre language="php">' . "\n    // hi\n    \$a = 1;\n" . '</x-doc::pre>');
        self::assertSame('PHP', self::texts($view)[0]);
        self::assertNotSame([], self::colours($view));
    }

    public function test_theme_label_and_variant_attributes(): void
    {
        $dark = $this->rendered('<x-doc::pre language="php" theme="github-dark">$a = 1;</x-doc::pre>');
        self::assertSame('#24292e', (string) $dark->style()->background);

        self::assertSame('composer.json', self::texts($this->rendered('<x-doc::pre language="json" label="composer.json">{}</x-doc::pre>'))[0]);
        self::assertCount(1, $this->rendered('<x-doc::pre language="json" :label="false">{}</x-doc::pre>')->children());
        self::assertCount(1, $this->rendered('<x-doc::pre language="json" label="false">{}</x-doc::pre>')->children());

        $strip = $this->rendered('<x-doc::pre language="json" variant="strip">{}</x-doc::pre>');
        $caption = $this->rendered('<x-doc::pre language="json" variant="caption">{}</x-doc::pre>');
        self::assertInstanceOf(View::class, $strip->children()[0], 'strip puts the label in a bar');
        self::assertInstanceOf(Text::class, $caption->children()[0], 'caption puts the label above the box');
        self::assertInstanceOf(View::class, $caption->children()[1]);
        self::assertSame('JSON', self::texts($caption)[0]);
    }

    public function test_pre_without_language_is_unchanged(): void
    {
        self::assertInstanceOf(Text::class, $this->nodeFrom('<x-doc::pre>x</x-doc::pre>'));
    }

    public function test_an_empty_language_is_absent(): void
    {
        self::assertInstanceOf(Text::class, $this->nodeFrom('<x-doc::pre language="">x</x-doc::pre>'));
        self::assertInstanceOf(Text::class, $this->nodeFrom('<x-doc::pre language="" theme="">x</x-doc::pre>'));
    }

    public function test_an_empty_theme_is_absent(): void
    {
        $view = $this->rendered('<x-doc::pre language="php" theme="">$a = 1;</x-doc::pre>');
        self::assertSame('#f6f8fa', (string) $view->style()->background);
    }

    public function test_language_pre_keeps_its_literal_slot(): void
    {
        $view = $this->rendered("<x-doc::pre language=\"text\">\n    {{ '<a & b>' }}\n      deeper\n</x-doc::pre>");
        self::assertSame("<a & b>\n  deeper", self::texts($view)[1]);
    }

    public function test_config_theme_variant_and_custom_grammar(): void
    {
        config(['papercut.code' => [
            'theme' => 'demo-dark',
            'variant' => 'strip',
            'grammars' => ['demo' => self::FIX . '/demo.tmLanguage.json'],
            'themes' => ['demo-dark' => self::FIX . '/demo-dark.json'],
            'aliases' => ['dm' => 'demo'],
            'injections' => [['path' => self::FIX . '/demo-sigils.injection.json', 'into' => ['source.demo']]],
        ]]);
        $this->fresh();
        $view = $this->rendered('<x-doc::pre language="dm">demo {{ $s }}</x-doc::pre>', ['s' => '@@x']);
        self::assertSame('#101010', (string) $view->style()->background);
        self::assertContains('#00ff00', self::colours($view));
        self::assertContains('#ff00ff', self::colours($view));
        self::assertSame('strip', app(PapercutManager::class)->codeHighlighting()->variant);
        self::assertInstanceOf(View::class, $view->children()[0], 'the configured variant is applied');
    }

    public function test_unreadable_grammar_path_throws_naming_the_config_key(): void
    {
        config(['papercut.code.grammars' => ['demo' => '/nope/missing.json']]);
        $this->fresh();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('papercut.code.grammars.demo');
        app(PapercutManager::class)->codeHighlighting();
    }

    public function test_invalid_variant_in_config_throws(): void
    {
        config(['papercut.code.variant' => 'large']);
        $this->fresh();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('papercut.code.variant');
        app(PapercutManager::class)->codeHighlighting();
    }

    public function test_code_highlighting_is_built_once(): void
    {
        $m = app(PapercutManager::class);
        self::assertSame($m->codeHighlighting(), $m->codeHighlighting());
    }

    public function test_markup_highlight_switch(): void
    {
        $html = '<pre><code class="language-php">$a = 1;</code></pre>';
        $on = $this->nodeFrom('<x-doc::markup :html="$h" />', ['h' => $html]);
        self::assertTrue($this->hasCodeBlock($on));

        config(['papercut.markup.highlight' => false]);
        $this->fresh();
        $off = $this->nodeFrom('<x-doc::markup :html="$h" />', ['h' => $html]);
        self::assertFalse($this->hasCodeBlock($off));
        // a per-tag override must not switch it back on
        $off = $this->nodeFrom('<x-doc::markup :html="$h" styles="original" />', ['h' => $html]);
        self::assertFalse($this->hasCodeBlock($off));
    }

    private function hasCodeBlock(mixed $node): bool
    {
        // markup renders the block to a view, so look for its language label
        if ($node instanceof Text) {
            return array_map(static fn ($s): string => $s->text, $node->spanList()) === ['PHP'];
        }

        return $node instanceof View && array_filter($node->children(), fn ($c): bool => $this->hasCodeBlock($c)) !== [];
    }

    public function test_label_true_is_rejected_and_empty_keeps_the_default(): void
    {
        try {
            $this->nodeFrom('<x-doc::pre language="php" :label="true">x</x-doc::pre>');
            self::fail('Expected InvalidValue.');
        } catch (\Papercut\Exception\InvalidValue $e) {
            self::assertStringContainsString('label', $e->getMessage());
        }
        self::assertSame('PHP', self::texts($this->rendered('<x-doc::pre language="php" label="">x</x-doc::pre>'))[0]);
    }

    public function test_custom_config_without_phiki_gives_plain_text_and_the_install_note(): void
    {
        config(['papercut.code.grammars' => ['demo' => self::FIX . '/demo.tmLanguage.json']]);
        app()->forgetInstance(PapercutManager::class);
        Papercut::clearResolvedInstances();
        app()->instance(PapercutManager::class, new class(app(\Papercut\Laravel\Rendering\NodeRegistry::class), app(\Papercut\Laravel\Preview\PreviewRegistry::class)) extends PapercutManager {
            protected function phikiAvailable(): bool
            {
                return false;
            }
        });

        $view = $this->rendered('<x-doc::pre language="demo">demo</x-doc::pre>');
        self::assertSame([], self::colours($view));
        self::assertSame('demo', self::texts($view)[1]);
        self::assertStringContainsString('install phiki/phiki', implode('|', array_map(static fn ($n): string => $n->feature, $view->notes())));

        // markup keeps rendering too
        $md = $this->nodeFrom('<x-doc::markup :html="$h" />', ['h' => '<p>a</p>']);
        self::assertInstanceOf(Text::class, $md);
    }
}
