<?php

declare(strict_types=1);

namespace Papercut\Laravel\Components;

/** `<x-doc::stat label="Revenue" note="Up 12%">$48,200</x-doc::stat>`: the slot is the value. */
final class Stat extends PapercutComponent
{
    /** @return list<string> */
    protected function ownAttributes(): array
    {
        return ['label', 'note', 'variant'];
    }

    protected function build(array $data): object
    {
        $stat = \Papercut\Decorator\Stat::make($this->inlineText($data));
        $label = $this->slotOrAttribute($data, 'label');
        if ($label !== null) {
            $stat = $stat->label($label);
        }
        $note = $this->slotOrAttribute($data, 'note');
        if ($note !== null) {
            $stat = $stat->note($note);
        }

        return $this->styled($stat, $data);
    }
}
