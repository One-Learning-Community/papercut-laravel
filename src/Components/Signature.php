<?php

declare(strict_types=1);

namespace Papercut\Laravel\Components;

/**
 * `<x-doc::signature name="…" role="…" date="…">`: the slot sits on the line (an image, a typed name, or nothing).
 *
 * @internal Part of Papercut's engine, not its public API. It may change in any release.
 */
final class Signature extends PapercutComponent
{
    /** @return list<string> */
    protected function ownAttributes(): array
    {
        return ['name', 'role', 'date', 'variant'];
    }

    protected function build(array $data): object
    {
        $signature = \Papercut\Decorator\Signature::make(...$this->children($data));
        foreach (['name', 'role', 'date'] as $caption) {
            $value = $this->slotOrAttribute($data, $caption);
            if ($value !== null) {
                $signature = $signature->{$caption}($value);
            }
        }

        return $this->styled($signature, $data);
    }
}
