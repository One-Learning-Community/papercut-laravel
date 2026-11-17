<?php

declare(strict_types=1);

namespace Quoin\Laravel\Components;

use Quoin\Decorator\ChoiceOption;
use Quoin\Text;
use Quoin\View;

/** @internal Engine detail, not public API. It may change in any release. */
final class Choice extends QuoinComponent
{
    /** @return list<string> */
    protected function ownAttributes(): array
    {
        return ['selected'];
    }

    protected function build(array $data): object
    {
        $this->rejectStyleAttributes($data);
        ['own' => $own] = $this->attrs($data);
        $nodes = $this->children($data);
        $label = match (count($nodes)) {
            0 => Text::make(''),
            1 => $nodes[0],
            default => View::make(...$nodes),
        };

        return new ChoiceOption($label, array_key_exists('selected', $own) && $this->boolOwn($own['selected'], 'selected'));
    }
}
