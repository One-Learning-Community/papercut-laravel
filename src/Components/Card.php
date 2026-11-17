<?php

declare(strict_types=1);

namespace Quoin\Laravel\Components;

/** @internal Engine detail, not public API. It may change in any release. */
final class Card extends QuoinComponent
{
    /** @return list<string> */
    protected function ownAttributes(): array
    {
        return ['title', 'heading', 'variant'];
    }

    protected function build(array $data): object
    {
        ['own' => $own] = $this->attrs($data);
        $card = \Quoin\Decorator\Card::make(...$this->children($data));
        if (isset($own['heading'])) {
            $card = $card->heading($this->headingOwn($own['heading']));
        }
        $title = $this->slotOrAttribute($data, 'title');
        if ($title !== null) {
            $card = $card->title($title);
        }

        return $this->styled($card, $data);
    }
}
