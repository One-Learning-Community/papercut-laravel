<?php

declare(strict_types=1);

namespace Papercut\Laravel\Components;

use Papercut\Exception\ImageUnreadable;
use Papercut\Laravel\Exception\InvalidStructure;
use Papercut\Laravel\Rendering\ImagePath;
use Papercut\Laravel\Tag;

/** <x-doc::svg>: an SVG from src (a file under the image path), data (markup) or markup inside the tag. */
final class Svg extends PapercutComponent
{
    protected function ownAttributes(): array
    {
        return ['src', 'data'];
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

        $where = rtrim(Tag::of('svg'), '>') . ($src !== null ? ' src="' . $src . '">' : '>');
        try {
            $node = $src !== null
                ? \Papercut\Svg::file(ImagePath::resolve($src))
                : \Papercut\Svg::fromString($markup ?? $inside);
        } catch (ImageUnreadable $e) {
            throw new ImageUnreadable($e->getMessage() . ' on ' . $where, 0, $e);
        }

        return $this->styled($node, $data);
    }
}
