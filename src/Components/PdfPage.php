<?php

declare(strict_types=1);

namespace Papercut\Laravel\Components;

use Papercut\Exception\{InvalidValue, PdfUnreadable};
use Papercut\Laravel\Exception\InvalidStructure;
use Papercut\Laravel\Rendering\ImagePath;
use Papercut\Laravel\Tag;

/** <x-doc::pdf-page>: a page of an existing PDF, from src (a file under the image path) or data (its bytes). */
final class PdfPage extends PapercutComponent
{
    /** Blade escapes bound attributes as text, which would corrupt binary data; a constructor parameter reaches the tag as it is. */
    public function __construct(private readonly mixed $data = null)
    {
    }

    protected function ownAttributes(): array
    {
        return ['src', 'data', 'page', 'password'];
    }

    protected function build(array $data): object
    {
        $own = $this->attrs($data)['own'];
        if ($this->data !== null) {
            $own['data'] = $this->data;
        }

        return $this->styled(self::node($own, 'src', 'data', 'page', 'password', 'pdf-page'), $data);
    }

    /**
     * A PdfPage from a tag's attributes, shared with the template attributes of <x-doc::page>.
     *
     * @param array<string, mixed> $own
     */
    public static function node(array $own, string $srcKey, string $dataKey, string $pageKey, string $passwordKey, string $tag): \Papercut\PdfPage
    {
        $src = $own[$srcKey] ?? null;
        $bytes = $own[$dataKey] ?? null;
        if (($src === null) === ($bytes === null)) {
            throw new InvalidStructure(Tag::of($tag) . " requires exactly one of $srcKey or $dataKey");
        }
        if (($src !== null && !is_string($src)) || ($bytes !== null && !is_string($bytes))) {
            throw new InvalidStructure(Tag::of($tag) . " $srcKey must be a string path and $dataKey a string of PDF bytes");
        }
        $location = 'on ' . Tag::of($tag);

        $page = $own[$pageKey] ?? 1;
        if (!is_int($page)) {
            $text = is_scalar($page) ? trim((string) $page) : get_debug_type($page);
            if (preg_match('/^[1-9][0-9]*$/', $text) !== 1) {
                throw InvalidValue::for('page number', $text)->because('expected a whole number from 1')->withContext($pageKey)->within($location);
            }
            $page = (int) $text;
        }
        $password = $own[$passwordKey] ?? '';
        if (!is_string($password)) {
            throw new InvalidStructure(Tag::of($tag) . " $passwordKey must be a string");
        }

        try {
            return $src !== null
                ? \Papercut\PdfPage::file(ImagePath::resolve($src, 'PDF', PdfUnreadable::class), $page, $password)
                : \Papercut\PdfPage::bytes((string) $bytes, $page, $password);
        } catch (PdfUnreadable $e) {
            throw new PdfUnreadable($e->getMessage() . ' on ' . rtrim(Tag::of($tag), '>') . ' ' . $srcKey . '="' . $src . '">', 0, $e);
        } catch (InvalidValue $e) {
            throw $e->withContext($pageKey)->within($location);
        }
    }
}
