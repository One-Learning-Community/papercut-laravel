<?php

declare(strict_types=1);

namespace Papercut\Laravel\Components;

use Papercut\Exception\InvalidValue;
use Papercut\Laravel\Tag;

final class Badge extends PapercutComponent
{
    /** @return list<string> */
    protected function ownAttributes(): array
    {
        return ['type'];
    }

    protected function build(array $data): object
    {
        ['own' => $own] = $this->attrs($data);
        $badge = \Papercut\Decorator\Badge::make($this->inlineText($data));
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
