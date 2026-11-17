<?php

declare(strict_types=1);

namespace Quoin\Laravel\Components;

use Quoin\Laravel\Tag;
use Quoin\Exception\{ImageUnreadable, InvalidValue};
use Quoin\Laravel\Exception\InvalidStructure;
use Quoin\Laravel\Rendering\ImagePath;
use Quoin\Style\ImageFit;

/** @internal Engine detail, not public API. It may change in any release. */
final class Image extends QuoinComponent
{
    /** Blade escapes bound attributes as text, which would corrupt binary data; a constructor parameter reaches the tag as it is. */
    public function __construct(private readonly mixed $data = null)
    {
    }

    protected function ownAttributes(): array
    {
        return ['src', 'data', 'fit', 'alt'];
    }

    protected function build(array $data): object
    {
        $own = $this->attrs($data)['own'];
        $src = $own['src'] ?? null;
        $bytes = $this->data ?? $own['data'] ?? null;
        if (($src === null) === ($bytes === null)) {
            throw new InvalidStructure(Tag::of('image') . ' requires exactly one of src or data');
        }
        if (($src !== null && !is_string($src)) || ($bytes !== null && !is_string($bytes))) {
            throw new InvalidStructure(Tag::of('image') . ' src must be a string path');
        }

        $alt = $this->altOwn($own);

        if ($src !== null) {
            try {
                $node = \Quoin\Image::file(ImagePath::resolve($src));
            } catch (ImageUnreadable $e) {
                throw new ImageUnreadable($e->getMessage() . ' on ' . rtrim(Tag::of('image'), '>') . ' src="' . $src . '">', 0, $e);
            }
        } else {
            $node = \Quoin\Image::bytes((string) $bytes);
        }

        if ($alt !== null) {
            $node = $node->alt($alt);
        }
        if (isset($own['fit'])) {
            $fit = ImageFit::tryFrom((string) $own['fit'])
                ?? throw InvalidValue::for('fit', (string) $own['fit'])->because('expected contain, cover or fill')->withContext('fit')->within('on ' . Tag::of('image'));
            $node = $node->fit($fit);
        }

        return $this->styled($node, $data);
    }
}
