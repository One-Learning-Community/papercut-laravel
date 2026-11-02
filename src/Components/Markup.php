<?php

declare(strict_types=1);

namespace Papercut\Laravel\Components;

use Illuminate\Contracts\Support\Htmlable;
use Papercut\Laravel\Exception\InvalidStructure;
use Papercut\Laravel\PapercutManager;
use Papercut\Laravel\Rendering\NodeRegistry;
use Papercut\Laravel\Tag;
use Papercut\Markup\{BlankParagraphs, Markup as Converter, MarkupOptions, StyleMode};

/**
 * `<x-doc::markup :html="$h" />`, `:text="$t"` or HTML in the slot: converts HTML or plain text to nodes.
 *
 * @internal Part of Papercut's engine, not its public API. It may change in any release.
 */
final class Markup extends PapercutComponent
{
    /**
     * html and text are constructor parameters so Blade passes them raw (bound attributes would be run through e()).
     * The sentinel default keeps "absent" distinct from null.
     */
    public function __construct(public mixed $html = MarkupAbsent::Value, public mixed $text = MarkupAbsent::Value)
    {
    }

    protected function ownAttributes(): array
    {
        return ['styles', 'blank-paragraphs', 'images'];
    }

    protected function build(array $data): object
    {
        $slot = $this->slotHtml($data);
        $given = ($this->html !== MarkupAbsent::Value) + ($this->text !== MarkupAbsent::Value) + ($slot !== null);
        if ($given !== 1) {
            throw new InvalidStructure(Tag::of('markup') . ($given === 0 ? ' requires' : ' takes only') . ' one of html, text or HTML in its slot');
        }
        $options = $this->options($this->attrs($data)['own']);
        if ($slot !== null) {
            return Converter::html($slot, $options);
        }
        $isHtml = $this->html !== MarkupAbsent::Value;
        $source = $isHtml ? $this->html : $this->text;
        if ($source instanceof \Stringable || $source instanceof Htmlable) {
            $source = $source instanceof Htmlable ? $source->toHtml() : (string) $source;
        } elseif (is_int($source) || is_float($source)) {
            $source = (string) $source;
        } elseif ($source !== null && !is_string($source)) {
            throw new InvalidStructure(Tag::of('markup') . ' ' . ($isHtml ? 'html' : 'text') . ' must be a string or null');
        }

        return $isHtml ? Converter::html($source, $options) : Converter::text($source, $options);
    }

    /**
     * The slot as HTML, or null when it holds only whitespace. It takes plain HTML and components that
     * render to HTML; papercut tags inside would arrive as nodes, which HTML can't hold.
     *
     * @param array<string, mixed> $data
     */
    private function slotHtml(array $data): ?string
    {
        $html = '';
        foreach (app(NodeRegistry::class)->segments((string) ($data['slot'] ?? '')) as $segment) {
            if (!is_string($segment)) {
                throw new InvalidStructure(Tag::of('markup') . ' takes plain HTML in its slot, not ' . Tag::any() . ' tags; put those next to the markup tag instead');
            }
            $html .= $segment;
        }

        return trim($html) === '' ? null : $html;
    }

    /** @param array<string, mixed> $own */
    private function options(array $own): MarkupOptions
    {
        $manager = app(PapercutManager::class);
        $base = $manager->markupOptions();
        if (!isset($own['styles']) && !isset($own['blank-paragraphs']) && !isset($own['images'])) {
            return $base;
        }
        $styles = $base->styles;
        if (isset($own['styles'])) {
            $styles = is_string($own['styles']) ? StyleMode::tryFrom($own['styles']) : null;
            $styles ?? throw new \InvalidArgumentException('Invalid ' . Tag::of('markup') . ' styles: ' . self::show($own['styles']) . "; expected 'theme' or 'original'");
        }
        $blank = $base->blankParagraphs;
        if (isset($own['blank-paragraphs'])) {
            $blank = is_string($own['blank-paragraphs']) ? BlankParagraphs::tryFrom($own['blank-paragraphs']) : null;
            $blank ?? throw new \InvalidArgumentException('Invalid ' . Tag::of('markup') . ' blank-paragraphs: ' . self::show($own['blank-paragraphs']) . "; expected 'drop' or 'keep'");
        }

        $images = $base->images;
        if (isset($own['images'])) {
            $images = $own['images'] === 'none' ? null : $manager->markupImageResolver($own['images']);
        }

        return new MarkupOptions($styles, $images, $blank, $base->code);
    }

    private static function show(mixed $v): string
    {
        return is_scalar($v) ? var_export($v, true) : get_debug_type($v);
    }
}

/** Default for the markup tag's html and text parameters: "not given", as opposed to null. */
enum MarkupAbsent
{
    case Value;
}
