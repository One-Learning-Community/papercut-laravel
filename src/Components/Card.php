<?php

declare(strict_types=1);

namespace Papercut\Laravel\Components;


final class Card extends PapercutComponent
{
    /** @return list<string> */
    protected function ownAttributes(): array
    {
        return ['title'];
    }

    protected function build(array $data): object
    {
        $card = \Papercut\Decorator\Card::make(...$this->children($data));
        $title = $this->slotOrAttribute($data, 'title');
        if ($title !== null) {
            $card = $card->title($title);
        }

        return $this->styled($card, $data);
    }
}
