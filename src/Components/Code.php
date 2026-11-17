<?php

declare(strict_types=1);

namespace Quoin\Laravel\Components;

use Quoin\Exception\InvalidValue;
use Quoin\Exception\UnknownAttribute;
use Quoin\Laravel\Exception\InvalidStructure;
use Quoin\Laravel\Rendering\NodeRegistry;
use Quoin\Laravel\Tag;
use Quoin\Span;
use Quoin\Style\Style;
use Quoin\Text;

/**
 * Inline code for use inside text tags: a span in the `code` style. Like `<x-doc::pre>`, its
 * content is literal text.
 *
 * @internal Engine detail, not public API. It may change in any release.
 */
final class Code extends QuoinComponent
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
