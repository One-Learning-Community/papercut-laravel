<?php

declare(strict_types=1);

namespace Papercut\Laravel\Rendering;

use Papercut\Laravel\Tag;
use Illuminate\Support\HtmlString;
use Papercut\Laravel\Exception\InvalidStructure;

final class NodeRegistry
{
    /** @var list<array{nonce: string, view: ?string, nodes: array<int, object>, taken: array<int, true>, next: int}> */
    private array $scopes = [];

    public function scope(callable $fn, ?string $view = null): mixed
    {
        $this->scopes[] = ['nonce' => bin2hex(random_bytes(8)), 'view' => $view, 'nodes' => [], 'taken' => [], 'next' => 0];
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

    /** @return list<object|string> */
    public function segments(string $html): array
    {
        $key = array_key_last($this->scopes);
        $parts = preg_split('/(\[\[pc:[0-9a-f]{16}:\d+\]\])/i', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return [$html];
        }

        $out = [];
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $node = null;
            if ($key !== null && preg_match('/^\[\[pc:([0-9a-f]{16}):(\d+)\]\]$/i', $part, $m) === 1
                && strtolower($m[1]) === $this->scopes[$key]['nonce']) {
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
