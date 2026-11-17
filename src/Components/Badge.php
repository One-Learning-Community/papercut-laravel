<?php

declare(strict_types=1);

namespace Quoin\Laravel\Components;

use Quoin\Exception\InvalidValue;
use Quoin\Laravel\Tag;

/** @internal Engine detail, not public API. It may change in any release. */
final class Badge extends QuoinComponent
{
    /** @return list<string> */
    protected function ownAttributes(): array
    {
        return ['type', 'variant'];
    }

    protected function build(array $data): object
    {
        ['own' => $own] = $this->attrs($data);
        $badge = \Quoin\Decorator\Badge::make($this->inlineText($data));
        if (isset($own['type'])) {
            $type = $this->scalarOwn($own['type'], 'type');
            try {
                $badge = $badge->tone($type);
            } catch (\InvalidArgumentException $e) {
                throw InvalidValue::for('type', $type)->because('expected a tone name')->withContext('type')->within('on ' . Tag::of('badge'));
            }
            $this->requireThemeStyles(['badge.' . $badge->toneName()]);
        }

        return $this->styled($badge, $data);
    }
}
