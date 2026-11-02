<?php

declare(strict_types=1);

namespace Papercut\Laravel\Components;

/** @internal Part of Papercut's engine, not its public API. It may change in any release. */
final class H2 extends PapercutComponent
{
    protected function ownAttributes(): array
    {
        return ['align', 'size'];
    }

    protected function build(array $data): object
    {
        return $this->textStyled($this->inlineText($data)->withPreset('h2'), $data);
    }
}
