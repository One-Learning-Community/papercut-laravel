<?php

declare(strict_types=1);

namespace Papercut\Laravel\Rendering;

use Papercut\Laravel\Tag;
use Papercut\Laravel\Exception\InvalidStructure;
use Papercut\Laravel\Components\HeaderRows;
use Papercut\Decorator\Badge;
use Papercut\Decorator\ChoiceOption;
use Papercut\Decorator\KeyValuePair;
use Papercut\ListItem;
use Papercut\Place;
use Papercut\TableCell;
use Papercut\TableRow;
use Papercut\Markup\Markup;
use Papercut\Equation;
use Papercut\Exception\InvalidValue;
use Papercut\Span;
use Papercut\Text;
use Papercut\Style\Style;
use Papercut\View;

/** @internal Part of Papercut's engine, not its public API. It may change in any release. */
final class Segments
{
    private const array PARENTS = [
        TableCell::class => ['td', 'tr'],
        TableRow::class => ['tr', 'table'],
        HeaderRows::class => ['thead', 'table'],
        ListItem::class => ['li', 'ol|ul'],
        ChoiceOption::class => ['choice', 'choices'],
        KeyValuePair::class => ['kv', 'key-value|totals'],
    ];

    /**
     * @param list<object|string> $segs
     * @param list<class-string> $allow structure classes (TableCell, TableRow, HeaderRows, Place, ...) this parent accepts
     * @return list<object>
     */
    public static function nodes(array $segs, string $tag, array $allow = []): array
    {
        $out = [];
        foreach ($segs as $seg) {
            if (is_string($seg)) {
                if (trim($seg) !== '') {
                    $out[] = Text::markup(trim($seg));
                }
            } else {
                if ($seg instanceof Place && !in_array(Place::class, $allow, true)) {
                    throw new InvalidStructure(Tag::of('place') . ' only works in the overlay slot of ' . Tag::of('page'));
                }
                foreach (self::PARENTS as $class => [$name, $parent]) {
                    if ($seg instanceof $class && !in_array($class, $allow, true)) {
                        throw new InvalidStructure(Tag::of($name) . ' must be inside ' . implode(' or ', array_map(Tag::of(...), explode('|', $parent))));
                    }
                }
                $out[] = $seg;
            }
        }

        return $out;
    }

    /** @param list<object|string> $segs */
    public static function text(array $segs, string $tag): string
    {
        $text = '';
        foreach ($segs as $seg) {
            if (!is_string($seg)) {
                throw new InvalidStructure(Tag::of($tag) . " can only contain text and inline tags (<b>, <i>, <u>, <s>, <a>, <br>, <span>, <mark>, <code>, <badge>, <math>)");
            }
            $text .= $seg;
        }

        return $text;
    }

    /**
     * Builds one Text from a text tag's slot: strings are inline markup (outer whitespace trimmed),
     * Text objects are appended with their notes, Views are flattened into the line.
     *
     * @param list<object|string> $segs
     */
    public static function inline(array $segs, string $tag): Text
    {
        $last = count($segs) - 1;
        $out = Text::spans();
        foreach ($segs as $i => $seg) {
            if (is_string($seg)) {
                if ($i === 0) {
                    $seg = ltrim($seg);
                }
                if ($i === $last) {
                    $seg = rtrim($seg);
                }
                if ($seg !== '') {
                    try {
                        $out = $out->append(Text::markup($seg));
                    } catch (InvalidValue $e) {
                        // An inline tag opened around a component leaves its text in two pieces, each with half a tag.
                        $names = self::inlineComponentNames($segs);
                        if ($names === []) {
                            throw $e;
                        }
                        throw new InvalidStructure(implode(' and ', array_map(Tag::of(...), $names)) . " can't sit inside inline HTML tags such as <b> or <a>; put it beside them in " . Tag::of($tag) . ' and style the surrounding text instead. ' . $e->getMessage(), 0, $e);
                    }
                }
            } elseif ($seg instanceof Badge) {
                // Inside a text tag a badge is an inline badge, not the block one.
                try {
                    $span = $seg->inlineSpan();
                } catch (\InvalidArgumentException $e) {
                    throw new InvalidStructure(Tag::of('badge') . ' inside ' . Tag::of($tag) . ': ' . $e->getMessage());
                }
                if ($span === null) {
                    throw new InvalidStructure(Tag::of('badge') . ' inside ' . Tag::of($tag) . ' can only contain plain text, with no inline tags or style attributes');
                }
                $out = $out->append(Text::spans($span));
            } elseif ($seg instanceof Equation && app(NodeRegistry::class)->isInline($seg)) {
                // Inside a text tag an equation (<x-doc::math>) is an inline one.
                if ($seg->style() != Style::none() || $seg->styleName() !== null && $seg->styleName() !== 'equation') {
                    throw new InvalidStructure(Tag::of('math') . ' inside ' . Tag::of($tag) . ' takes no style attributes; style the surrounding text');
                }
                $out = $out->append(Text::spans(Span::math($seg->math())));
            } elseif ($seg instanceof Text || $seg instanceof View) {
                $out = $out->append(Markup::flatten($seg));
            } else {
                throw new InvalidStructure(Tag::of($tag) . " can only contain text and inline tags (<b>, <i>, <u>, <s>, <a>, <br>, <span>, <mark>, <code>, <badge>, <math>)");
            }
        }

        return $out;
    }

    /**
     * @param list<object|string> $segs
     * @return list<string> the inline component tags (math, badge) the slot holds
     */
    private static function inlineComponentNames(array $segs): array
    {
        $names = [];
        foreach ($segs as $seg) {
            if ($seg instanceof Equation && app(NodeRegistry::class)->isInline($seg)) {
                $names['math'] = 'math';
            } elseif ($seg instanceof Badge) {
                $names['badge'] = 'badge';
            }
        }

        return array_values($names);
    }
}
