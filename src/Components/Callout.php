<?php

declare(strict_types=1);

namespace Quoin\Laravel\Components;

use Quoin\Exception\InvalidValue;
use Quoin\Laravel\Tag;

/** @internal Engine detail, not public API. It may change in any release. */
final class Callout extends QuoinComponent
{
    /** @return list<string> */
    protected function ownAttributes(): array
    {
        return ['type', 'title', 'variant'];
    }

    protected function build(array $data): object
    {
        ['own' => $own] = $this->attrs($data);
        $callout = \Quoin\Decorator\Callout::make(...$this->children($data));
        if (isset($own['type'])) {
            $type = $this->scalarOwn($own['type'], 'type');
            try {
                $callout = $callout->tone($type);
            } catch (\InvalidArgumentException $e) {
                throw InvalidValue::for('type', $type)->because('expected a tone name')->withContext('type')->within('on ' . Tag::of('callout'));
            }
            $this->requireThemeStyles(['callout.' . $callout->toneName()]);
        }
        $title = $this->slotOrAttribute($data, 'title');
        if ($title !== null) {
            $callout = $callout->title($title);
            $this->requireThemeStyles(['callout.title.' . $callout->toneName()]);
        }

        return $this->styled($callout, $data);
    }
}
