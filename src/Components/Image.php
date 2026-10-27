<?php

declare(strict_types=1);

namespace Papercut\Laravel\Components;

use Papercut\Laravel\Tag;
use Papercut\Exception\{ImageUnreadable, InvalidValue};
use Papercut\Laravel\Exception\InvalidStructure;
use Papercut\Laravel\Rendering\ImagePath;
use Papercut\Style\ImageFit;

final class Image extends PapercutComponent
{
    /** Blade escapes bound attributes as text, which would corrupt binary data; a constructor parameter reaches the tag as it is. */
    public function __construct(private readonly mixed $data = null)
    {
    }

    protected function ownAttributes(): array
    {
        return ['src', 'data', 'fit'];
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

        if ($src !== null) {
            try {
                $node = \Papercut\Image::file(ImagePath::resolve($src));
            } catch (ImageUnreadable $e) {
                throw new ImageUnreadable($e->getMessage() . ' on ' . rtrim(Tag::of('image'), '>') . ' src="' . $src . '">', 0, $e);
            }
        } else {
            $node = \Papercut\Image::bytes((string) $bytes);
        }

        if (isset($own['fit'])) {
            $fit = ImageFit::tryFrom((string) $own['fit'])
                ?? throw InvalidValue::for('fit', (string) $own['fit'])->because('expected contain, cover or fill')->withContext('fit')->within('on ' . Tag::of('image'));
            $node = $node->fit($fit);
        }

        return $this->styled($node, $data);
    }
}
