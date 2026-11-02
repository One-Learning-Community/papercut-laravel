<?php

declare(strict_types=1);

namespace Papercut\Laravel\Components;

use Papercut\Exception\InvalidValue;
use Papercut\Laravel\Tag;
use Papercut\Value\{Color, Length};

/**
 * Text flowing through several columns; `column-gap` is the gap between columns, `gap` the ordinary style gap between children.
 *
 * @internal Part of Papercut's engine, not its public API. It may change in any release.
 */
final class ColumnFlow extends PapercutComponent
{
    protected function ownAttributes(): array
    {
        return ['columns', 'widths', 'column-gap', 'rule'];
    }

    protected function build(array $data): object
    {
        $own = $this->attrs($data)['own'];
        $flow = \Papercut\ColumnFlow::make(...$this->children($data));
        $where = 'on ' . Tag::of('column-flow');

        try {
            if (isset($own['column-gap'])) {
                $flow = $flow->columnGap(...array_map(
                    static fn (string $g): Length => Length::parse($g),
                    $this->words($own['column-gap'], 'column-gap'),
                ));
            }
            $count = null;
            if (isset($own['columns'])) {
                $raw = $this->scalarOwn($own['columns'], 'columns');
                if (filter_var($raw, FILTER_VALIDATE_INT) === false) {
                    throw InvalidValue::for('columns', $raw)->because('expected a whole number')->withContext('columns');
                }
                $count = (int) $raw;
                $flow = $flow->columns($count);
            }
            if (isset($own['widths'])) {
                $shares = array_map(static function (string $w): float {
                    if (!is_numeric($w)) {
                        throw InvalidValue::for('widths', $w)->because('expected numbers such as "2 1"')->withContext('widths');
                    }

                    return (float) $w;
                }, $this->words($own['widths'], 'widths'));
                if ($count !== null && $count !== count($shares)) {
                    throw InvalidValue::for('widths', implode(' ', $shares))->because(sprintf('expected %d widths to match columns', $count))->withContext('widths');
                }
                $flow = $flow->columnWidths(...$shares);
            }
            if (isset($own['rule'])) {
                $flow = $this->withRule($flow, $own['rule']);
            }
        } catch (InvalidValue $e) {
            throw $e->within($where);
        }

        return $this->styled($flow, $data);
    }

    /** @return non-empty-list<string> */
    private function words(mixed $value, string $name): array
    {
        $words = preg_split('/\s+/', trim($this->scalarOwn($value, $name)), -1, PREG_SPLIT_NO_EMPTY);
        if ($words === false || $words === []) {
            throw InvalidValue::for($name, '')->withContext($name);
        }

        return $words;
    }

    private function withRule(\Papercut\View $flow, mixed $value): \Papercut\View
    {
        if ($value === true || $value === '1') {
            return $flow->columnRule();
        }
        if ($value === false || $value === '0') {
            return $flow;
        }
        $text = trim($this->scalarOwn($value, 'rule'));
        if ($text === '' || $text === 'true') {
            return $flow->columnRule();
        }
        if ($text === 'false') {
            return $flow;
        }
        $space = strcspn($text, " \t\r\n");
        $first = substr($text, 0, $space);
        $rest = trim(substr($text, $space));
        try {
            $width = Length::parse($first);
            $color = $rest !== '' ? Color::parse($rest) : null;
        } catch (InvalidValue $e) {
            throw $e->withContext('rule');
        }

        return $flow->columnRule($width, $color);
    }
}
