<?php

declare(strict_types=1);

namespace Papercut\Laravel\Components;


final class Blockquote extends PapercutComponent
{
    /** @return list<string> */
    protected function ownAttributes(): array
    {
        return ['cite'];
    }

    protected function build(array $data): object
    {
        $quote = \Papercut\Decorator\Blockquote::make(...$this->children($data));
        $cite = $this->slotOrAttribute($data, 'cite');
        if ($cite !== null) {
            $quote = $quote->cite($cite);
        }

        return $this->styled($quote, $data);
    }
}
