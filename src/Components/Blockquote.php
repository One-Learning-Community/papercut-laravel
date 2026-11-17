<?php

declare(strict_types=1);

namespace Quoin\Laravel\Components;

/** @internal Engine detail, not public API. It may change in any release. */
final class Blockquote extends QuoinComponent
{
    /** @return list<string> */
    protected function ownAttributes(): array
    {
        return ['cite', 'variant'];
    }

    protected function build(array $data): object
    {
        $quote = \Quoin\Decorator\Blockquote::make(...$this->children($data));
        $cite = $this->slotOrAttribute($data, 'cite');
        if ($cite !== null) {
            $quote = $quote->cite($cite);
        }

        return $this->styled($quote, $data);
    }
}
