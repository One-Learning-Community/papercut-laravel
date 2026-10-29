<?php

declare(strict_types=1);

namespace Papercut\Laravel\Rendering;

use Papercut\Laravel\Components\Pre;
use Papercut\Laravel\Tag;
use Illuminate\Support\HtmlString;
use Papercut\Laravel\Exception\InvalidStructure;

final class NodeRegistry
{
    /** @var list<array{nonce: string, view: ?string, nodes: array<int, object>, taken: array<int, true>, next: int}> */
    private array $scopes = [];

    private const int REMEMBERED = 1000;
    private const string SHAPE = '/\[\[pc:[0-9a-f]{16}:\d+\]\]/i';

    /** @var array<string, true> nonces this registry issued, oldest first, so a marker from an earlier render is told apart from user text */
    private array $issued = [];

    /** Whether the text holds something shaped like a node marker. */
    public static function looksMarked(string $text): bool
    {
        return preg_match(self::SHAPE, $text) === 1;
    }

    /**
     * A sentence for a "can only contain" error when the offending child is text shaped like a node marker
     * (it may be a marker from a cache or an earlier render, or just user text).
     */
    public static function staleHint(mixed $child): string
    {
        if (!$child instanceof \Papercut\Text) {
            return '';
        }
        $text = implode('', array_map(static fn ($span) => $span->text, $child->spanList()));

        return self::looksMarked($text)
            ? '. The text looks like a papercut node marker, which may come from rendered output that was cached or reused; rendered output can\'t be reused across renders, so cache the data instead.'
            : '';
    }

    public function scope(callable $fn, ?string $view = null): mixed
    {
        $nonce = bin2hex(random_bytes(8));
        $this->issued[$nonce] = true;
        if (count($this->issued) > self::REMEMBERED) {
            unset($this->issued[array_key_first($this->issued)]);
        }
        $this->scopes[] = ['nonce' => $nonce, 'view' => $view, 'nodes' => [], 'taken' => [], 'next' => 0];
        try {
            return $fn();
        } finally {
            array_pop($this->scopes);
        }
    }

    public function register(object $node): HtmlString
    {
        $key = array_key_last($this->scopes);
        if ($key === null) {
            throw new InvalidStructure(Tag::any() . ' components can only be rendered through Papercut::view().');
        }
        $id = $this->scopes[$key]['next']++;
        $this->scopes[$key]['nodes'][$id] = $node;

        return new HtmlString('[[pc:' . $this->scopes[$key]['nonce'] . ':' . $id . ']]');
    }

    /**
     * The rendered html as text and node objects. Pre's compile-time start mark (U+E000) can end up in
     * text that only mentions a pre tag (inside a PHP string); {@see Pre::unmark()} removes it from the
     * text segments unless $keepStartMark: a pre slot reads its own mark and unmarks the rest itself.
     *
     * @return list<object|string>
     */
    public function segments(string $html, bool $keepStartMark = false): array
    {
        $key = array_key_last($this->scopes);
        $parts = preg_split('/(\[\[pc:[0-9a-f]{16}:\d+\]\])/i', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            throw new InvalidStructure('The rendered output could not be searched for papercut nodes: ' . preg_last_error_msg());
        }

        $out = [];
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $node = null;
            $isMarker = preg_match('/^\[\[pc:([0-9a-f]{16}):(\d+)\]\]$/i', $part, $m);
            if ($isMarker === false) {
                throw new InvalidStructure('The rendered output could not be searched for papercut nodes: ' . preg_last_error_msg());
            }
            if ($isMarker === 1 && ($key === null || strtolower($m[1]) !== $this->scopes[$key]['nonce'])) {
                if (isset($this->issued[strtolower($m[1])])) {
                    throw new InvalidStructure("Found a papercut node marker from another or an earlier render. Rendered papercut output can't be cached or reused across renders; cache the data instead and render it each time.");
                }
                $isMarker = 0; // never issued here: ordinary text that happens to look like a marker
            }
            if ($isMarker === 1) {
                $id = (int) $m[2];
                $exact = $m[1] === $this->scopes[$key]['nonce'] && str_starts_with($part, '[[pc:');
                if (!$exact || !isset($this->scopes[$key]['nodes'][$id])) {
                    $view = $this->scopes[$key]['view'];
                    throw new InvalidStructure('A slot containing ' . Tag::any() . ' tags was modified as a string (e.g. strtoupper($slot))' . ($view !== null ? " in view [$view]" : ''));
                }
                if (isset($this->scopes[$key]['taken'][$id])) {
                    throw new InvalidStructure('A ' . Tag::any() . ' element was output twice (is {{ $slot }} echoed more than once?)');
                }
                $this->scopes[$key]['taken'][$id] = true;
                $node = $this->scopes[$key]['nodes'][$id];
            }
            if ($node === null && !$keepStartMark) {
                $part = Pre::unmark($part);
                if ($part === '') {
                    continue;
                }
            }
            if ($node !== null) {
                $out[] = $node;
            } elseif ($out !== [] && is_string($out[array_key_last($out)])) {
                $out[array_key_last($out)] .= $part;
            } else {
                $out[] = $part;
            }
        }

        return $out;
    }

    public function remaining(): int
    {
        $key = array_key_last($this->scopes);

        return $key === null ? 0 : count($this->scopes[$key]['nodes']) - count($this->scopes[$key]['taken']);
    }
}
