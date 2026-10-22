<?php

declare(strict_types=1);

namespace Papercut\Laravel\Components;

use Papercut\Laravel\Tag;
use Papercut\Exception\{InvalidValue, UnknownAttribute};
use Papercut\Laravel\Exception\InvalidStructure;

final class Document extends PapercutComponent
{
    protected function ownAttributes(): array
    {
        return ['title', 'author', 'subject', 'keywords', 'lang'];
    }

    protected function build(array $data): object
    {
        ['own' => $own, 'style' => $style] = $this->attrs($data);
        foreach (array_keys($style) as $key) {
            throw UnknownAttribute::for($key)->within('on ' . Tag::of('document'));
        }
        if (isset($own['style'])) {
            throw UnknownAttribute::for('style')->within('on ' . Tag::of('document'));
        }

        $children = $this->rawChildren($data, []);
        $pages = array_values(array_filter($children, static fn (object $n): bool => $n instanceof \Papercut\Page));
        if ($pages === []) {
            if ($children === []) {
                throw new InvalidStructure(Tag::of('document') . ' must contain at least one ' . Tag::of('page'));
            }
            /** @var list<\Papercut\Node> $children */
            $pages = [Page::implicit($children)];
        } elseif (count($pages) !== count($children)) {
            throw new InvalidStructure(Tag::of('document') . ' children must all be ' . Tag::of('page') . '; put other content inside a page');
        }

        $doc = \Papercut\Document::make(...$pages);
        foreach (['title', 'author', 'subject', 'keywords', 'lang'] as $key) {
            if (isset($own[$key])) {
                if (!is_scalar($own[$key])) {
                    throw InvalidValue::for($key, get_debug_type($own[$key]))->withContext($key)->within('on ' . Tag::of('document'));
                }
                $doc = $doc->$key((string) $own[$key]);
            }
        }

        return $doc;
    }
}
