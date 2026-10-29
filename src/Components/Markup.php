<?php

declare(strict_types=1);

namespace Papercut\Laravel\Components;

use Illuminate\Contracts\Support\Htmlable;
use Papercut\Laravel\Exception\InvalidStructure;
use Papercut\Laravel\PapercutManager;
use Papercut\Laravel\Tag;
use Papercut\Markup\{BlankParagraphs, Markup as Converter, MarkupOptions, StyleMode};

/** `<x-doc::markup :html="$h" />` or `:text="$t"`: converts HTML or plain text to nodes. */
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
        $isHtml = $this->html !== MarkupAbsent::Value;
        if ($isHtml === ($this->text !== MarkupAbsent::Value)) {
            throw new InvalidStructure(Tag::of('markup') . ' requires exactly one of html or text');
        }
        $options = $this->options($this->attrs($data)['own']);
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

        return new MarkupOptions($styles, $images, $blank);
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
