<?php

declare(strict_types=1);

namespace Papercut\Laravel\Components;

use Illuminate\Support\Str;
use Papercut\Laravel\Tag;
use Papercut\Exception\{InvalidValue, UnknownAttribute};
use Papercut\Laravel\Exception\InvalidStructure;
use Papercut\Laravel\Rendering\NodeRegistry;
use Papercut\Laravel\Rendering\Segments;
use Papercut\Value\{Edges, Length};

final class Page extends PapercutComponent
{
    private const array SLOTS = ['header', 'footer', 'first-header', 'first-footer', 'even-header', 'even-footer', 'background', 'overlay'];

    /** Blade escapes bound attributes as text, which would corrupt the template's bytes; a constructor parameter reaches the tag as it is. */
    public function __construct(private readonly mixed $templateData = null)
    {
    }

    protected function ownAttributes(): array
    {
        return ['size', 'orientation', 'margin', 'header-gap', 'footer-gap', 'template', 'template-data', 'template-page', 'template-password', 'mirror'];
    }

    protected function build(array $data): object
    {
        ['own' => $own, 'style' => $style] = $this->attrs($data);
        $location = 'on ' . Tag::of('page');
        foreach (array_keys($style) as $key) {
            throw UnknownAttribute::for($key)->within($location);
        }
        if (isset($own['style'])) {
            throw UnknownAttribute::for('style')->within($location);
        }

        // Blade camel-cases a slot name: <x-slot:first-header> arrives as "firstHeader". Kebab-casing it back
        // also accepts the camelCase spelling (<x-slot:firstHeader>) as an alias, and when both spellings of a
        // slot are given, the later one silently replaces the earlier. Both are deliberate.
        $slots = [];
        foreach ($this->namedSlots($data) as $name => $content) {
            $slots[Str::kebab($name)] = $content;
        }
        foreach (array_keys($slots) as $name) {
            if (!in_array($name, self::SLOTS, true)) {
                throw new InvalidStructure("Unknown slot \"$name\" on " . Tag::of('page') . "; allowed slots: " . implode(', ', self::SLOTS));
            }
        }

        if ($this->templateData !== null) {
            $own['template-data'] = $this->templateData;
        }
        $page = self::configured($own);
        if (isset($own['mirror'])) {
            $page = $page->mirrorMargins($this->boolOwn($own['mirror'], 'mirror'));
        }
        $registry = app(NodeRegistry::class);
        foreach (self::SLOTS as $name) {
            if (!isset($slots[$name])) {
                continue;
            }
            if ($name === 'overlay') {
                $places = [];
                foreach (Segments::nodes($registry->segments($slots[$name]), 'page', [\Papercut\Place::class]) as $node) {
                    $places[] = $node instanceof \Papercut\Place
                        ? $node
                        : throw new InvalidStructure('The overlay slot of ' . Tag::of('page') . ' takes only ' . Tag::of('place') . ' tags');
                }
                $page = $page->overlay(...$places);
                continue;
            }
            $nodes = Segments::nodes($registry->segments($slots[$name]), 'page');
            /** @var list<\Papercut\Node> $nodes */
            $page = match ($name) {
                'header' => $page->header(...$nodes),
                'footer' => $page->footer(...$nodes),
                'first-header' => $page->firstHeader(...$nodes),
                'first-footer' => $page->firstFooter(...$nodes),
                'even-header' => $page->evenHeader(...$nodes),
                'even-footer' => $page->evenFooter(...$nodes),
                'background' => $page->background(...$nodes), // @phpstan-ignore match.alwaysTrue (SLOTS is the only source of $name; the default arm guards a slot added without a case)
                default => throw new \LogicException("Unhandled page slot \"$name\""),
            };
        }

        /** @var list<\Papercut\Node> $body */
        $body = $this->children($data);

        return $page->body(...$body);
    }

    /**
     * @param list<\Papercut\Node> $body
     */
    public static function implicit(array $body): \Papercut\Page
    {
        return self::configured([])->body(...$body);
    }

    /** A paper name (`a4`) or a width and height (`8.5in 11in`). */
    private static function sized(string $size): \Papercut\Page
    {
        $parts = preg_split('/\s+/', trim($size), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($parts) === 2) {
            return \Papercut\Page::size(Length::parse($parts[0]), Length::parse($parts[1]));
        }
        if (count($parts) === 1 && in_array(strtolower($parts[0]), \Papercut\Page::sizeNames(), true)) {
            return \Papercut\Page::named($parts[0]);
        }
        throw InvalidValue::for('page size', $size)->because(sprintf(
            'expected one of %s, or a width and height such as "8.5in 11in"',
            implode(', ', \Papercut\Page::sizeNames()),
        ));
    }

    /** @param array<string, mixed> $own */
    private static function configured(array $own): \Papercut\Page
    {
        $location = 'on ' . Tag::of('page');
        if (isset($own['template']) || isset($own['template-data'])) {
            if (isset($own['size']) || isset($own['orientation'])) {
                throw new InvalidStructure(Tag::of('page') . ' takes its size from the template; remove size and orientation');
            }
            $template = PdfPage::node($own, 'template', 'template-data', 'template-page', 'template-password', 'page');

            return self::spaced(\Papercut\Page::template($template), $own);
        }
        try {
            $size = $own['size'] ?? config('papercut.paper', 'a4');
            if (!is_string($size)) {
                throw InvalidValue::for('page size', is_scalar($size) ? (string) $size : get_debug_type($size));
            }
            $page = self::sized($size);
        } catch (InvalidValue $e) {
            throw $e->withContext('size')->within($location);
        }

        $orientation = $own['orientation'] ?? 'portrait';
        if ($orientation === 'landscape') {
            $page = $page->landscape();
        } elseif ($orientation !== 'portrait') {
            throw InvalidValue::for('orientation', is_scalar($orientation) ? (string) $orientation : get_debug_type($orientation), 'portrait')
                ->withContext('orientation')->within($location);
        }

        return self::spaced($page, $own);
    }

    /**
     * Margins and header and footer gaps, from the attributes or the config.
     *
     * @param array<string, mixed> $own
     */
    private static function spaced(\Papercut\Page $page, array $own): \Papercut\Page
    {
        $location = 'on ' . Tag::of('page');
        $margin = $own['margin'] ?? config('papercut.margin', '20mm');
        try {
            $page = $page->margin($margin instanceof Edges ? $margin : Edges::parse((string) (is_scalar($margin) ? $margin : '20mm')));
        } catch (InvalidValue $e) {
            throw $e->withContext('margin')->within($location);
        }

        foreach (['header-gap' => 'headerGap', 'footer-gap' => 'footerGap'] as $attr => $method) {
            if (isset($own[$attr])) {
                $gap = $own[$attr];
                try {
                    $page = $page->$method($gap instanceof Length ? $gap : Length::parse((string) (is_scalar($gap) ? $gap : '')));
                } catch (InvalidValue $e) {
                    throw $e->withContext($attr)->within($location);
                }
            }
        }

        return $page;
    }
}
