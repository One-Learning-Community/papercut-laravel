<?php

declare(strict_types=1);

namespace Quoin\Laravel\Components;

use Quoin\Exception\ImageUnreadable;
use Quoin\Laravel\Exception\InvalidStructure;
use Quoin\Laravel\Rendering\ImagePath;
use Quoin\Laravel\Tag;

/**
 * <x-doc::svg>: an SVG from src (a file under the image path), data (markup) or markup inside the tag.
 *
 * @internal Engine detail, not public API. It may change in any release.
 */
final class Svg extends QuoinComponent
{
    protected function ownAttributes(): array
    {
        return ['src', 'data', 'alt'];
    }

    protected function build(array $data): object
    {
        $own = $this->attrs($data)['own'];
        $src = $own['src'] ?? null;
        $markup = $own['data'] ?? null;
        $inside = trim($this->text($data));
        if (count(array_filter([$src !== null, $markup !== null, $inside !== ''])) !== 1) {
            throw new InvalidStructure(Tag::of('svg') . ' requires exactly one of src, data or SVG markup inside the tag');
        }
        if (($src !== null && !is_string($src)) || ($markup !== null && !is_string($markup))) {
            throw new InvalidStructure(Tag::of('svg') . ' src must be a string path and data a string of SVG markup');
        }

        $alt = $this->altOwn($own);

        $where = rtrim(Tag::of('svg'), '>') . ($src !== null ? ' src="' . $src . '">' : '>');
        try {
            $node = $src !== null
                ? \Quoin\Svg::file(ImagePath::resolve($src))
                : \Quoin\Svg::fromString($markup ?? $inside);
        } catch (ImageUnreadable $e) {
            throw new ImageUnreadable($e->getMessage() . ' on ' . $where, 0, $e);
        }

        if ($alt !== null) {
            $node = $node->alt($alt);
        }

        return $this->styled($node, $data);
    }
}
