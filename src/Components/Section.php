<?php

declare(strict_types=1);

namespace Papercut\Laravel\Components;


final class Section extends PapercutComponent
{
    /** @return list<string> */
    protected function ownAttributes(): array
    {
        return ['title', 'number', 'rule'];
    }

    protected function build(array $data): object
    {
        ['own' => $own] = $this->attrs($data);
        $section = \Papercut\Decorator\Section::make(...$this->children($data));
        $title = $this->slotOrAttribute($data, 'title');
        if ($title !== null) {
            $section = $section->title($title);
        }
        if (isset($own['number'])) {
            $number = $own['number'];
            $section = $section->number(is_int($number) ? $number : $this->scalarOwn($number, 'number'));
        }
        if (isset($own['rule'])) {
            $section = $section->rule($this->boolOwn($own['rule'], 'rule'));
        }

        return $this->styled($section, $data);
    }
}
