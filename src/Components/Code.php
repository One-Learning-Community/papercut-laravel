<?php

declare(strict_types=1);

namespace Papercut\Laravel\Components;

use Papercut\Exception\InvalidValue;
use Papercut\Exception\UnknownAttribute;
use Papercut\Laravel\Exception\InvalidStructure;
use Papercut\Laravel\Rendering\NodeRegistry;
use Papercut\Laravel\Tag;
use Papercut\Span;
use Papercut\Style\Style;
use Papercut\Text;

/**
 * Inline code for use inside text tags: a span in the `code` style. Like `<x-doc::pre>`, its
 * content is literal text.
 */
final class Code extends PapercutComponent
{
    protected function build(array $data): object
    {
        $text = '';
        foreach (app(NodeRegistry::class)->segments((string) ($data['slot'] ?? '')) as $seg) {
            if (!is_string($seg)) {
                throw new InvalidStructure(Tag::of('code') . ' can only contain text');
            }
            $text .= $seg;
        }
        $text = html_entity_decode(trim($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        ['own' => $own, 'style' => $style] = $this->attrs($data);
        $name = $own['style'] ?? null;
        $location = 'on ' . Tag::of('code');
        try {
            if (is_string($name)) {
                $this->requireThemeStyles(preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [$name]);
            }
            $spanStyle = Style::fromAttributes($style);
            if ($name instanceof Style) {
                $spanStyle = $name->merge($spanStyle);
            }
        } catch (UnknownAttribute | InvalidValue $e) {
            throw $e->within($location);
        }

        // A named style layers over `code`, so the monospace font survives it.
        return Text::spans(new Span($text, $spanStyle, null, false, is_string($name) ? 'code ' . $name : 'code'));
    }
}
