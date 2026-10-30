<?php

declare(strict_types=1);

namespace Papercut\Laravel\Components;

use Papercut\Decorator\CodeBlock;
use Papercut\Exception\InvalidValue;
use Papercut\Laravel\Exception\InvalidStructure;
use Papercut\Laravel\Rendering\NodeRegistry;
use Papercut\Laravel\PapercutManager;
use Papercut\Laravel\Tag;
use Papercut\Span;
use Papercut\Style\Style;
use Papercut\Text;

/**
 * Preformatted text: the slot is literal (no tags are parsed, HTML entities are decoded), so
 * `{{ $code }}` comes out exactly as the variable's value.
 */
final class Pre extends PapercutComponent
{
    /**
     * Marks the start of a pre slot. Blade trims a slot before the component sees it, which would
     * cost the first line its indentation; this private-use code point, inserted right after the
     * opening tag at compile time, keeps the slot's start intact until {@see lines()} removes it.
     */
    public const string START = "\u{E000}";

    /**
     * Blade string preprocessor: puts START after each (non-self-closing) pre opening tag.
     */
    public static function markOpeningTags(string $template): string
    {
        $prefix = config('papercut.prefix', 'doc');
        if (!is_string($prefix) || preg_match('/^[a-z][a-z0-9-]*$/', $prefix) !== 1 || !str_contains($template, '::pre')) {
            return $template;
        }
        $pattern = '/<\s*x[-:]' . preg_quote($prefix, '/') . '::pre(?<attrs>(?:\s+(?:'
            . '@(?:class|style)(\((?:(?>[^()]+)|(?-1))*\))'
            . '|\{\{\s*\$attributes(?:[^}]+?)?\s*\}\}'
            . '|[\w\-:.@%]+(?:=(?:"[^"]*"|\'[^\']*\'|[^\'"=<>\s]+))?'
            . '))*)\s*(?<close>\/?)>/';
        $mark = static fn (string $part): string => preg_replace_callback(
            $pattern,
            static fn (array $m): string => $m['close'] === '/' ? $m[0] : $m[0] . self::START,
            $part,
        ) ?? $part;

        // @verbatim regions are output as written, so no mark may go into them.
        $parts = preg_split('/(@verbatim.*?@endverbatim)/s', $template, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        if ($parts === false) {
            return $template;
        }

        return implode('', array_map(static fn (string $part): string => str_starts_with($part, '@verbatim') ? $part : $mark($part), $parts));
    }

    /**
     * Removes START where the compile-time hook left it in text that only mentions a pre tag (inside a
     * PHP string): right after something shaped like a pre opening tag, escaped or not. A U+E000 anywhere
     * else is the author's own character and stays.
     */
    public static function unmark(string $text): string
    {
        $prefix = config('papercut.prefix', 'doc');
        if (!is_string($prefix) || !str_contains($text, self::START)) {
            return $text;
        }
        $pattern = '/((?:<|&lt;)\s*x[-:]' . preg_quote($prefix, '/') . '::pre\b[^<>]*?(?:>|&gt;))' . self::START . '/u';

        return preg_replace($pattern, '$1', $text) ?? $text;
    }

    protected function ownAttributes(): array
    {
        return ['language', 'theme', 'label', 'variant'];
    }

    protected function build(array $data): object
    {
        $lines = self::lines(self::literal($this->rawSlot($data)));
        ['own' => $own] = $this->attrs($data);
        // An empty language or theme is the same as none (a Blade variable that came out blank).
        foreach (['language', 'theme'] as $name) {
            if (($own[$name] ?? null) === '') {
                unset($own[$name]);
            }
        }
        if (isset($own['language'])) {
            return $this->codeBlock($lines, $own, $data);
        }
        foreach (['theme', 'label', 'variant'] as $name) {
            if (isset($own[$name])) {
                throw new InvalidStructure($name . ' on ' . Tag::of('pre') . ' needs a language');
            }
        }

        $spans = [];
        foreach ($lines as $i => $line) {
            if ($i > 0) {
                $spans[] = Span::break();
            }
            if ($line !== '') {
                $spans[] = Span::text($line);
            }
        }

        ['own' => $own, 'style' => $style] = $this->attrs($data);
        $name = $own['style'] ?? null;

        // A named style layers over `pre`, so the monospace font and pre-wrap survive it.
        $text = $this->applyStyle(Text::spans(...$spans), $style, is_string($name) ? 'pre ' . $name : 'pre');

        return $name instanceof Style ? $this->applyStyle($text, [], $name) : $text;
    }

    /**
     * @param list<string> $lines
     * @param array<string, mixed> $own
     * @param array<string, mixed> $data
     */
    private function codeBlock(array $lines, array $own, array $data): object
    {
        $block = CodeBlock::make(implode("\n", $lines))
            ->language($this->scalarOwn($own['language'], 'language'))
            ->highlighting(app(PapercutManager::class)->codeHighlighting());
        if (isset($own['theme'])) {
            $block = $block->theme($this->scalarOwn($own['theme'], 'theme'));
        }
        if (isset($own['label'])) {
            $label = $own['label'];
            if ($label === true) {
                throw InvalidValue::for('label', 'true')->because('expected text, or false to hide it')->withContext('label')->within('on ' . Tag::of('pre'));
            }
            $text = $label === false ? 'false' : $this->scalarOwn($label, 'label');
            // an empty label keeps the language's default
            if ($text !== '') {
                $block = $block->label($text === 'false' ? false : $text);
            }
        }

        return $this->styled($block, $data);
    }

    /** @param array<string, mixed> $data */
    private function rawSlot(array $data): string
    {
        $text = '';
        foreach (app(NodeRegistry::class)->segments((string) ($data['slot'] ?? ''), keepStartMark: true) as $seg) {
            if (!is_string($seg)) {
                throw new InvalidStructure(Tag::of('pre') . ' can only contain text; tags inside it are not parsed and components are not allowed');
            }
            $text .= $seg;
        }

        return $text;
    }

    private static function literal(string $slot): string
    {
        return html_entity_decode($slot, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * The lines of the slot with the common leading indentation removed (tabs and spaces count as
     * characters). The text normally starts with START: then the first line is dropped when blank,
     * trailing blank lines are dropped and every non-blank line, the first included, takes part in
     * the indentation. Without START (a component rendered some other way) Blade has already trimmed
     * the first line's indentation, so that line is left alone and the indentation is taken from the
     * lines after it.
     *
     * @return list<string>
     */
    private static function lines(string $text): array
    {
        $marked = str_starts_with($text, self::START);
        if ($marked) {
            $text = substr($text, strlen(self::START));
        }
        $text = self::unmark($text);
        $lines = preg_split('/\r\n|\n|\r/', $text) ?: [''];
        if (trim($lines[0]) === '') {
            array_shift($lines);
        }
        if ($marked) {
            while ($lines !== [] && trim($lines[array_key_last($lines)]) === '') {
                array_pop($lines);
            }
        } elseif ($lines !== [] && trim($lines[array_key_last($lines)]) === '') {
            array_pop($lines);
        }

        $from = $marked ? 0 : 1;
        $indent = null;
        foreach (array_slice($lines, $from) as $line) {
            if (trim($line) === '') {
                continue;
            }
            $lead = substr($line, 0, strspn($line, " \t"));
            if ($indent === null) {
                $indent = $lead;
                continue;
            }
            $n = 0;
            $max = min(strlen($indent), strlen($lead));
            while ($n < $max && $indent[$n] === $lead[$n]) {
                $n++;
            }
            $indent = substr($indent, 0, $n);
        }
        $indent ??= '';

        $strip = static fn (string $line): string => trim($line) === '' ? '' : (str_starts_with($line, $indent) ? substr($line, strlen($indent)) : $line);

        return $marked
            ? array_map($strip, $lines)
            : ($lines === [] ? [] : [trim($lines[0]) === '' ? '' : $lines[0], ...array_map($strip, array_slice($lines, 1))]);
    }
}
