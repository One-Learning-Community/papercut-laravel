<?php

declare(strict_types=1);

namespace Papercut\Laravel\Tests\Feature;

use Papercut\Image;
use Papercut\Markup\Markup;
use Papercut\Laravel\Exception\InvalidStructure;
use Papercut\Laravel\Facades\Papercut;
use Papercut\Laravel\PapercutManager;
use Papercut\Laravel\Tests\Fixtures\FixtureImageResolver;
use Papercut\Laravel\Tests\Fixtures\RecordingImageResolver;
use Papercut\Laravel\Tests\Fixtures\RecordingImagesA;
use Papercut\Laravel\Tests\Fixtures\RecordingImagesB;
use Papercut\Laravel\Tests\TestCase;
use Papercut\Style\TextAlign;
use Papercut\Text;
use Papercut\View;

final class MarkupComponentTest extends TestCase
{
    private function fresh(): void
    {
        app()->forgetInstance(PapercutManager::class);
        Papercut::clearResolvedInstances();
    }

    public function test_html_and_text(): void
    {
        $n = $this->nodeFrom('<x-doc::markup :html="$h" />', ['h' => '<p>a</p><p>b</p>']);
        self::assertInstanceOf(View::class, $n);
        $t = $this->nodeFrom('<x-doc::markup :text="$p" />', ['p' => "x&nbsp;\ny"]);
        self::assertInstanceOf(Text::class, $t);
        $empty = $this->nodeFrom('<x-doc::markup :html="$h" />', ['h' => null]);
        self::assertInstanceOf(View::class, $empty);
        self::assertSame([], $empty->children());
        $emptyText = $this->nodeFrom('<x-doc::markup :text="$h" />', ['h' => null]);
        self::assertInstanceOf(View::class, $emptyText);
    }

    /** @return array<string, array{mixed}> */
    public static function escapedMarkup(): array
    {
        return [
            'string' => ['<p>x &lt;b&gt;y&lt;/b&gt; &amp;nbsp; &amp;lt;i&amp;gt; "q" it\'s</p><p>z</p>'],
            'html string' => [new \Illuminate\Support\HtmlString('<p>x &lt;b&gt;y&lt;/b&gt; &amp;nbsp; &amp;lt;i&amp;gt;</p>')],
        ];
    }

    private static function spans(\Papercut\Node|\Papercut\Component $n): mixed
    {
        return $n instanceof Text ? array_map(fn ($s) => $s->text, $n->spanList())
            : array_map(fn ($c) => self::spans($c), $n instanceof View ? $n->children() : []);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('escapedMarkup')]
    public function test_values_reach_the_converter_unaltered(mixed $value): void
    {
        $raw = $value instanceof \Illuminate\Contracts\Support\Htmlable ? $value->toHtml() : $value;
        $viaTag = $this->nodeFrom('<x-doc::markup :html="$v" />', ['v' => $value]);
        self::assertSame(self::spans(Markup::html($raw)), self::spans($viaTag));
        $textVia = $this->nodeFrom('<x-doc::markup :text="$v" />', ['v' => $value]);
        self::assertSame(self::spans(Markup::text($raw)), self::spans($textVia));
    }

    public function test_empty_string_produces_nothing(): void
    {
        $n = $this->nodeFrom('<x-doc::markup :html="$h" />', ['h' => '']);
        self::assertInstanceOf(View::class, $n);
        self::assertSame([], $n->children());
    }

    public function test_unbuildable_images_resolver(): void
    {
        config(['papercut.markup.images' => \Papercut\Laravel\Tests\Fixtures\UnbuildableImageResolver::class]);
        $this->fresh();
        $this->expectExceptionMessageMatches('/^Cannot build papercut\.markup\.images resolver /');
        Papercut::markupOptions();
    }

    public function test_label_inside_a_bold_text_tag(): void
    {
        $t = $this->nodeFrom('<x-doc::text bold>× <x-doc::markup :html="$l" /></x-doc::text>', ['l' => '<p>Agree</p>']);
        self::assertInstanceOf(Text::class, $t);
        self::assertSame(['× ', 'Agree'], array_map(fn ($s) => $s->text, $t->spanList()));
    }

    public function test_configured_resolver_images(): void
    {
        config(['papercut.markup.images' => FixtureImageResolver::class]);
        $this->fresh();
        $n = $this->nodeFrom('<x-doc::markup :html="$h" />', ['h' => '<img src="a.png">']);
        self::assertTrue($n instanceof Image || ($n instanceof View && $n->children()[0] instanceof Image));
    }

    public function test_images_attribute_overrides_config(): void
    {
        config(['papercut.markup.images' => RecordingImagesB::class]);
        $this->fresh();
        RecordingImageResolver::reset();
        $a = $this->nodeFrom('<x-doc::markup :html="$h" images="' . RecordingImagesA::class . '" />', ['h' => '<img src="a.png">']);
        $b = $this->nodeFrom('<x-doc::markup :html="$h" />', ['h' => '<img src="b.png">']);
        self::assertSame([RecordingImagesA::class . ':a.png', RecordingImagesB::class . ':b.png'], RecordingImageResolver::$calls);
        foreach ([$a, $b] as $n) {
            self::assertTrue($n instanceof Image || ($n instanceof View && $n->children()[0] instanceof Image));
        }
    }

    public function test_images_none_shows_alt_text(): void
    {
        config(['papercut.markup.images' => RecordingImagesB::class]);
        $this->fresh();
        RecordingImageResolver::reset();
        $n = $this->nodeFrom('<x-doc::markup :html="$h" images="none" />', ['h' => '<p><img src="a.png" alt="A picture"></p>']);
        self::assertSame([], RecordingImageResolver::$calls);
        self::assertSame(['A picture'], array_map(fn ($s) => $s->text, $n->spanList()));
    }

    public function test_images_attribute_rejects_non_resolver_class(): void
    {
        $this->fresh();
        $this->expectExceptionMessage('Invalid <x-doc::markup> images: ' . var_export(\stdClass::class, true) . '; expected an invokable class name implementing ' . \Papercut\Laravel\Markup\ImageResolver::class . " or 'none'");
        $this->nodeFrom('<x-doc::markup :html="$h" images="' . \stdClass::class . '" />', ['h' => '<p>x</p>']);
    }

    public function test_resolver_instance_shared_across_tags(): void
    {
        $this->fresh();
        RecordingImageResolver::reset();
        $tag = '<x-doc::markup :html="$h" images="' . RecordingImagesA::class . '" />';
        $this->nodeFrom($tag, ['h' => '<img src="a.png">']);
        $this->nodeFrom($tag, ['h' => '<img src="b.png">']);
        self::assertSame(1, RecordingImageResolver::$built);
        self::assertCount(2, RecordingImageResolver::$calls);
    }

    public function test_a_tag_naming_the_configured_class_shares_the_config_instance(): void
    {
        config(['papercut.markup.images' => RecordingImagesA::class]);
        $this->fresh();
        RecordingImageResolver::reset();
        Papercut::markupOptions();
        $this->nodeFrom('<x-doc::markup :html="$h" />', ['h' => '<img src="a.png">']);
        $this->nodeFrom('<x-doc::markup :html="$h" images="' . RecordingImagesA::class . '" />', ['h' => '<img src="b.png">']);
        $this->nodeFrom('<x-doc::markup :html="$h" images="\\' . RecordingImagesA::class . '" />', ['h' => '<img src="c.png">']);

        self::assertSame(1, RecordingImageResolver::$built);
        self::assertCount(3, RecordingImageResolver::$calls);
    }

    public function test_a_leading_backslash_names_the_same_resolver(): void
    {
        $this->fresh();
        RecordingImageResolver::reset();
        $this->nodeFrom('<x-doc::markup :html="$h" images="' . RecordingImagesA::class . '" />', ['h' => '<img src="a.png">']);
        $this->nodeFrom('<x-doc::markup :html="$h" images="\\' . RecordingImagesA::class . '" />', ['h' => '<img src="b.png">']);

        self::assertSame(1, RecordingImageResolver::$built);
        self::assertCount(2, RecordingImageResolver::$calls);
    }

    public function test_images_attribute_that_is_not_a_class_name_is_invalid(): void
    {
        $this->fresh();
        $this->expectExceptionMessage('Invalid <x-doc::markup> images: true; expected an invokable class name implementing ' . \Papercut\Laravel\Markup\ImageResolver::class . " or 'none'");
        $this->nodeFrom('<x-doc::markup :html="$h" :images="true" />', ['h' => '<p>x</p>']);
    }

    public function test_images_none_is_case_sensitive(): void
    {
        $this->fresh();
        $this->expectExceptionMessage("Invalid <x-doc::markup> images: 'None'");
        $this->nodeFrom('<x-doc::markup :html="$h" images="None" />', ['h' => '<p>x</p>']);
    }

    public function test_unbuildable_images_resolver_on_a_tag(): void
    {
        $this->fresh();
        $this->expectExceptionMessageMatches('/^Cannot build <x-doc::markup> images resolver /');
        $this->nodeFrom('<x-doc::markup :html="$h" images="' . \Papercut\Laravel\Tests\Fixtures\UnbuildableImageResolver::class . '" />', ['h' => '<p>x</p>']);
    }

    public function test_config_styles_and_tag_override(): void
    {
        $html = '<p style="text-align: center">x</p>';
        $theme = $this->nodeFrom('<x-doc::markup :html="$h" />', ['h' => $html]);
        self::assertInstanceOf(Text::class, $theme);
        self::assertNotSame(TextAlign::Center, $theme->style()->textAlign);

        config(['papercut.markup.styles' => 'original']);
        $this->fresh();
        $orig = $this->nodeFrom('<x-doc::markup :html="$h" />', ['h' => $html]);
        self::assertInstanceOf(Text::class, $orig);
        self::assertSame(TextAlign::Center, $orig->style()->textAlign);

        $back = $this->nodeFrom('<x-doc::markup :html="$h" styles="theme" />', ['h' => $html]);
        self::assertInstanceOf(Text::class, $back);
        self::assertNotSame(TextAlign::Center, $back->style()->textAlign);
    }

    public function test_blank_paragraphs_override(): void
    {
        $html = '<p>a</p><p>&nbsp;</p><p>b</p>';
        $drop = $this->nodeFrom('<x-doc::markup :html="$h" />', ['h' => $html]);
        $keep = $this->nodeFrom('<x-doc::markup :html="$h" blank-paragraphs="keep" />', ['h' => $html]);
        self::assertInstanceOf(View::class, $drop);
        self::assertInstanceOf(View::class, $keep);
        self::assertCount(2, $drop->children());
        self::assertCount(3, $keep->children());
    }

    public function test_config_errors_surface_at_first_use(): void
    {
        config(['papercut.markup.styles' => 'fancy']);
        $this->fresh();
        app()->make(PapercutManager::class); // booting does not throw
        try {
            $this->nodeFrom('<x-doc::markup :html="$h" />', ['h' => '<p>a</p>']);
            self::fail('expected exception');
        } catch (\InvalidArgumentException $e) {
            self::assertSame("Invalid papercut.markup.styles: 'fancy'; expected 'theme' or 'original'", $e->getMessage());
        }

        config(['papercut.markup.styles' => 'theme', 'papercut.markup.blank_paragraphs' => 'maybe']);
        $this->fresh();
        $this->expectExceptionMessage("Invalid papercut.markup.blank_paragraphs: 'maybe'; expected 'drop' or 'keep'");
        Papercut::markupOptions();
    }

    public function test_bad_images_config(): void
    {
        config(['papercut.markup.images' => \stdClass::class]);
        $this->fresh();
        $this->expectExceptionMessage('Invalid papercut.markup.images: ' . var_export(\stdClass::class, true) . '; expected an invokable class name implementing ' . \Papercut\Laravel\Markup\ImageResolver::class . ' or null');
        Papercut::markupOptions();
    }

    public function test_bad_tag_override(): void
    {
        $this->expectExceptionMessage("Invalid <x-doc::markup> styles: 'fancy'; expected 'theme' or 'original'");
        $this->nodeFrom('<x-doc::markup :html="$h" styles="fancy" />', ['h' => '<p>a</p>']);
    }

    public function test_numbers_are_cast_to_strings(): void
    {
        $html = $this->nodeFrom('<x-doc::markup :html="$v" />', ['v' => 123]);
        self::assertInstanceOf(Text::class, $html);
        self::assertSame(['123'], self::spans($html));
        $text = $this->nodeFrom('<x-doc::markup :text="$v" />', ['v' => 1.5]);
        self::assertInstanceOf(Text::class, $text);
        self::assertSame(['1.5'], self::spans($text));
    }

    public function test_exactly_one_source(): void
    {
        $cases = [
            '<x-doc::markup :html="$h" :text="$h" />' => '<x-doc::markup> takes only one of html, text or HTML in its slot',
            '<x-doc::markup :html="$h"><p>b</p></x-doc::markup>' => '<x-doc::markup> takes only one of html, text or HTML in its slot',
            '<x-doc::markup :text="$h"><p>b</p></x-doc::markup>' => '<x-doc::markup> takes only one of html, text or HTML in its slot',
            '<x-doc::markup />' => '<x-doc::markup> requires one of html, text or HTML in its slot',
            "<x-doc::markup>\n   \n</x-doc::markup>" => '<x-doc::markup> requires one of html, text or HTML in its slot',
        ];
        foreach ($cases as $blade => $message) {
            try {
                $this->nodeFrom($blade, ['h' => 'x']);
                self::fail('expected InvalidStructure for ' . $blade);
            } catch (InvalidStructure $e) {
                self::assertSame($message, $e->getMessage(), $blade);
            }
        }
    }

    public function test_slot_is_converted_like_the_html_attribute(): void
    {
        $html = '<h3>Results</h3><p>Intro <b>text</b>.</p><ul><li>one</li><li>two</li></ul>';
        $fromSlot = $this->nodeFrom('<x-doc::markup>' . $html . '</x-doc::markup>');
        $fromAttribute = $this->nodeFrom('<x-doc::markup :html="$h" />', ['h' => $html]);
        self::assertEquals($fromAttribute, $fromSlot);
    }

    public function test_slot_echoes_stay_text_and_loops_run(): void
    {
        $n = $this->nodeFrom(
            '<x-doc::markup>@foreach ($items as $item)<p>{{ $item }}</p>@endforeach</x-doc::markup>',
            ['items' => ['<b>not bold</b>', 'a & b']],
        );
        self::assertSame([['<b>not bold</b>'], ['a & b']], self::spans($n));
    }

    public function test_slot_takes_components_that_render_html(): void
    {
        $n = $this->nodeFrom('<x-doc::markup><table><x-price-row label="Design" amount="400" /><x-price-row label="Build" amount="900" /></table></x-doc::markup>');
        $table = $n instanceof View ? $n->children()[0] ?? null : $n;
        self::assertInstanceOf(\Papercut\Table::class, $table);
        self::assertCount(2, $table->bodyRows());
    }

    public function test_slot_takes_the_tag_options(): void
    {
        $n = $this->nodeFrom('<x-doc::markup styles="original"><p style="text-align: center">x</p></x-doc::markup>');
        self::assertInstanceOf(Text::class, $n);
        self::assertSame(TextAlign::Center, $n->style()->textAlign);
    }

    public function test_papercut_tags_in_the_slot_are_an_error(): void
    {
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('<x-doc::markup> takes plain HTML in its slot, not <x-doc::*> tags; put those next to the markup tag instead');
        $this->nodeFrom('<x-doc::markup><p>a</p><x-doc::text>b</x-doc::text></x-doc::markup>');
    }
}
