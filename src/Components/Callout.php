<?php

declare(strict_types=1);

namespace Papercut\Laravel\Components;

use Papercut\Exception\InvalidValue;
use Papercut\Laravel\Tag;

final class Callout extends PapercutComponent
{
    /** @return list<string> */
    protected function ownAttributes(): array
    {
        return ['type', 'title'];
    }

    protected function build(array $data): object
    {
        ['own' => $own] = $this->attrs($data);
        $callout = \Papercut\Decorator\Callout::make(...$this->children($data));
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
