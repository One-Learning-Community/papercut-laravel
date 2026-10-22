<?php

declare(strict_types=1);

namespace Papercut\Laravel\Rendering;

use Papercut\Laravel\Tag;
use Papercut\Laravel\Exception\InvalidStructure;
use Papercut\Laravel\Components\HeaderRows;
use Papercut\Decorator\ChoiceOption;
use Papercut\Decorator\KeyValuePair;
use Papercut\ListItem;
use Papercut\TableCell;
use Papercut\TableRow;
use Papercut\Markup\Markup;
use Papercut\Text;
use Papercut\View;

final class Segments
{
    private const array PARENTS = [
        TableCell::class => ['td', 'tr'],
        TableRow::class => ['tr', 'table'],
        HeaderRows::class => ['thead', 'table'],
        ListItem::class => ['li', 'ol|ul'],
        ChoiceOption::class => ['choice', 'choices'],
        KeyValuePair::class => ['kv', 'key-value'],
    ];

    /**
     * @param list<object|string> $segs
     * @param list<class-string> $allow table-structure classes (TableCell, TableRow, HeaderRows) this parent accepts
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
                throw new InvalidStructure(Tag::of($tag) . " can only contain text and inline tags (<b>, <i>, <u>, <s>, <a>, <br>, <span>, <mark>)");
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
                    $out = $out->append(Text::markup($seg));
                }
            } elseif ($seg instanceof Text || $seg instanceof View) {
                $out = $out->append(Markup::flatten($seg));
            } else {
                throw new InvalidStructure(Tag::of($tag) . " can only contain text and inline tags (<b>, <i>, <u>, <s>, <a>, <br>, <span>, <mark>)");
            }
        }

        return $out;
    }
}
