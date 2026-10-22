<?php

declare(strict_types=1);

namespace Papercut\Laravel\Components;

final class H3 extends PapercutComponent
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
