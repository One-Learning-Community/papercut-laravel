<?php

declare(strict_types=1);

namespace Papercut\Laravel\Components;

use Papercut\Laravel\Rendering\NodeRegistry;
use Papercut\Laravel\Tag;
use Papercut\Laravel\Exception\InvalidStructure;
use Papercut\TableRow;
use Papercut\Value\TrackList;

final class Table extends PapercutComponent
{
    protected function ownAttributes(): array
    {
        return ['columns', 'repeat-header'];
    }

    protected function build(array $data): object
    {
        ['own' => $own] = $this->attrs($data);
        $table = \Papercut\Table::make();

        $columns = $own['columns'] ?? null;
        if ($columns instanceof TrackList || is_string($columns)) {
            $table = $table->columns($columns);
        } elseif (is_array($columns)) {
            /** @var list<\Papercut\Value\Length|\Papercut\Value\Percent|\Papercut\Value\Fr|\Papercut\Value\Auto> $tracks */
            $tracks = array_values($columns);
            $table = $table->columns(...$tracks);
        }

        if (array_key_exists('repeat-header', $own)) {
            $v = $own['repeat-header'];
            $table = $table->repeatHeader(is_string($v) ? !in_array(strtolower($v), ['false', '0', 'no', 'off'], true) : (bool) $v);
        }

        $sawHeader = false;
        $sawBody = false;
        foreach ($this->rawChildren($data, [TableRow::class, HeaderRows::class]) as $child) {
            if ($child instanceof HeaderRows && !$sawHeader && !$sawBody) {
                $sawHeader = true;
                foreach ($child->rows as $row) {
                    $table = $table->headerRow($row);
                }
            } elseif ($child instanceof TableRow) {
                $sawBody = true;
                $table = $table->bodyRow($child);
            } else {
                $name = $child instanceof HeaderRows ? 'thead' : (method_exists($child, 'debugName') ? $child->debugName() : get_debug_type($child));
                throw new InvalidStructure(Tag::of('table') . ' can only contain ' . Tag::of('thead') . ' (once, first) and ' . Tag::of('tr') . ", found <$name>" . NodeRegistry::staleHint($child));
            }
        }

        return $this->styled($table, $data);
    }
}
