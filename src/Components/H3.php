<?php

declare(strict_types=1);

namespace Quoin\Laravel\Components;

/** @internal Engine detail, not public API. It may change in any release. */
final class H3 extends QuoinComponent
{
    protected function ownAttributes(): array
    {
        return ['align', 'size'];
    }

    protected function build(array $data): object
    {
        return $this->textStyled($this->inlineText($data)->withPreset('h3'), $data);
    }
}
