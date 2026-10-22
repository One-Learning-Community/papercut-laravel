<?php

declare(strict_types=1);

namespace Papercut\Laravel\Components;

use Papercut\Laravel\Tag;
use Closure;
use Illuminate\View\Component;
use Illuminate\View\ComponentSlot;
use Papercut\Exception\InvalidValue;
use Papercut\Style\Style;
use Papercut\Exception\UnknownAttribute;
use Papercut\Laravel\PapercutManager;
use Papercut\Laravel\Rendering\NodeRegistry;
use Papercut\Laravel\Rendering\Segments;

abstract class PapercutComponent extends Component
{
    final public function render(): string
    {
        return '';
    }

    final public function resolveView(): Closure
    {
        /** @param array<string, mixed> $data */
        return fn (array $data) => app(NodeRegistry::class)->register($this->build($data));
    }

    /** @param array<string, mixed> $data */
    abstract protected function build(array $data): object;

    protected function tag(): string
    {
        $short = substr((string) strrchr('\\' . static::class, '\\'), 1);

        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '-$0', $short));
    }

    /** @return list<string> */
    protected function ownAttributes(): array
    {
        return [];
    }

    /**
     * @param array<string, mixed> $data
     * @return array{own: array<string, mixed>, style: array<string, mixed>}
     */
    protected function attrs(array $data): array
    {
        $bag = $data['attributes'] ?? null;
        $all = $bag instanceof \Illuminate\View\ComponentAttributeBag ? $bag->getAttributes() : [];
        $own = [];
        $style = [];
        foreach ($all as $key => $value) {
            $key = (string) $key;
            if ($key === 'style' || in_array($key, $this->ownAttributes(), true)) {
                $own[$key] = $value;
            } else {
                $style[$key] = $value;
            }
        }

        return ['own' => $own, 'style' => $style];
    }

    /** @return array<string, string> user attribute key => style key (container components only) */
    protected function aliases(): array
    {
        return [];
    }

    /**
     * Applies style attributes and a named style (string) or Style object, tagging errors with the tag.
     *
     * @template T of \Papercut\ListNode|\Papercut\ListItem|\Papercut\View|\Papercut\Link|\Papercut\Spacer|\Papercut\Text|\Papercut\PageNumber|\Papercut\Image|\Papercut\Table|\Papercut\TableRow|\Papercut\TableCell|\Papercut\Decorator\Decorator
     * @param T $node
     * @param array<string, mixed> $style
     * @return T
     */
    protected function applyStyle(object $node, array $style, mixed $styleName = null): object
    {
        $location = 'on ' . Tag::of($this->tag());
        $aliases = $this->aliases();
        try {
            $plain = [];
            foreach ($style as $key => $value) {
                if (isset($aliases[$key])) {
                    try {
                        $node = $node->attributes([$aliases[$key] => $value]);
                    } catch (InvalidValue $e) {
                        $re = InvalidValue::for($e->kind, $e->input, $e->suggestion)->withContext($key);
                        throw $e->reason !== null ? $re->because($e->reason) : $re;
                    }
                } else {
                    $plain[$key] = $value;
                }
            }
            if ($plain !== []) {
                $node = $node->attributes($plain);
            }
            if (is_string($styleName)) {
                app(PapercutManager::class)->pdfOptions()->theme()->named($styleName);
                $node = $node->apply($styleName);
            } elseif ($styleName instanceof Style) {
                $node = $node->apply($styleName);
            } elseif ($styleName !== null) {
                throw InvalidValue::for('style', is_scalar($styleName) ? (string) $styleName : get_debug_type($styleName))->withContext('style');
            }
        } catch (UnknownAttribute | InvalidValue $e) {
            throw $e->within($location);
        }

        return $node;
    }

    /**
     * @template T of \Papercut\ListNode|\Papercut\ListItem|\Papercut\View|\Papercut\Link|\Papercut\Spacer|\Papercut\Text|\Papercut\PageNumber|\Papercut\Image|\Papercut\Table|\Papercut\TableRow|\Papercut\TableCell|\Papercut\Decorator\Decorator
     * @param T $node
     * @param array<string, mixed> $data
     * @return T
     */
    protected function styled(object $node, array $data): object
    {
        ['own' => $own, 'style' => $style] = $this->attrs($data);

        return $this->applyStyle($node, $style, $own['style'] ?? null);
    }

    /**
     * Like styled(), but maps the own `align` to text-align and `size` to font-size.
     *
     * @template T of \Papercut\Text|\Papercut\PageNumber
     * @param T $node
     * @param array<string, mixed> $data
     * @return T
     */
    protected function textStyled(object $node, array $data): object
    {
        ['own' => $own, 'style' => $style] = $this->attrs($data);
        $mapped = [];
        if (isset($own['align'])) {
            $mapped['text-align'] = $own['align'];
        }
        if (isset($own['size'])) {
            $mapped['font-size'] = $own['size'];
        }

        return $this->applyStyle($node, [...$mapped, ...$style], $own['style'] ?? null);
    }

    /**
     * @param array<string, mixed> $data
     * @return list<\Papercut\Node>
     */
    protected function children(array $data): array
    {
        /** @var list<\Papercut\Node> $nodes */
        $nodes = $this->rawChildren($data, []);

        return $nodes;
    }

    /**
     * @param array<string, mixed> $data
     * @param list<class-string> $allow table-structure classes this parent accepts
     * @return list<object>
     */
    protected function rawChildren(array $data, array $allow): array
    {
        return Segments::nodes(app(NodeRegistry::class)->segments((string) ($data['slot'] ?? '')), $this->tag(), $allow);
    }

    /** @param array<string, mixed> $data */
    protected function text(array $data): string
    {
        return Segments::text(app(NodeRegistry::class)->segments((string) ($data['slot'] ?? '')), $this->tag());
    }

    /** @param array<string, mixed> $data */
    protected function inlineText(array $data): \Papercut\Text
    {
        return Segments::inline(app(NodeRegistry::class)->segments((string) ($data['slot'] ?? '')), $this->tag());
    }

    /**
     * A named slot's content as one inline Text, or null when the slot is absent.
     *
     * @param array<string, mixed> $data
     */
    protected function slotText(array $data, string $name): ?\Papercut\Text
    {
        $slots = $this->namedSlots($data);
        if (!isset($slots[$name])) {
            return null;
        }

        return Segments::inline(app(NodeRegistry::class)->segments($slots[$name]), $this->tag());
    }

    /**
     * The slot named $name, else the own attribute of that name (a string), else null.
     *
     * @param array<string, mixed> $data
     */
    protected function slotOrAttribute(array $data, string $name): \Papercut\Text|string|null
    {
        $slot = $this->slotText($data, $name);
        if ($slot !== null) {
            return $slot;
        }
        $value = $this->attrs($data)['own'][$name] ?? null;

        return $value === null ? null : $this->scalarOwn($value, $name);
    }

    /**
     * For tags that take only their own attributes: any other attribute (or `style`) is an error
     * with a suggestion drawn from the tag's own attribute names.
     *
     * @param array<string, mixed> $data
     */
    protected function rejectStyleAttributes(array $data): void
    {
        ['own' => $own, 'style' => $style] = $this->attrs($data);
        $unknown = array_keys($style);
        if (array_key_exists('style', $own)) {
            $unknown[] = 'style';
        }
        foreach ($unknown as $key) {
            $key = (string) $key;
            $best = null;
            $bestDistance = 3;
            foreach ($this->ownAttributes() as $candidate) {
                $d = levenshtein($key, $candidate);
                if ($d < $bestDistance) {
                    $best = $candidate;
                    $bestDistance = $d;
                }
            }
            throw UnknownAttribute::for($key, $best)->within('on ' . Tag::of($this->tag()));
        }
    }

    protected function scalarOwn(mixed $value, string $name): string
    {
        if (!is_scalar($value)) {
            throw InvalidValue::for($name, get_debug_type($value))->withContext($name)->within('on ' . Tag::of($this->tag()));
        }

        return (string) $value;
    }

    /**
     * Fails early, tagged with this tag, when a theme style name does not exist.
     *
     * @param list<string> $names
     */
    protected function requireThemeStyles(array $names): void
    {
        $theme = app(PapercutManager::class)->pdfOptions()->theme();
        try {
            foreach ($names as $name) {
                $theme->named($name);
            }
        } catch (UnknownAttribute $e) {
            throw $e->within('on ' . Tag::of($this->tag()));
        }
    }

    /**
     * Parses a boolean own attribute with the style bool rules (bare attribute, true/false, 1/0).
     */
    protected function boolOwn(mixed $value, string $name): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        try {
            return Style::fromAttributes(['keep-together' => $value])->keepTogether ?? true;
        } catch (InvalidValue $e) {
            $re = InvalidValue::for($e->kind, $e->input, $e->suggestion)->withContext($name);
            throw ($e->reason !== null ? $re->because($e->reason) : $re)->within('on ' . Tag::of($this->tag()));
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    protected function namedSlots(array $data): array
    {
        $out = [];
        foreach ($data as $name => $value) {
            if ($name !== 'slot' && $value instanceof ComponentSlot) {
                $out[(string) $name] = (string) $value;
            }
        }

        return $out;
    }
}
