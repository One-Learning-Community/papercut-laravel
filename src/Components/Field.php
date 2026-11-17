<?php

declare(strict_types=1);

namespace Quoin\Laravel\Components;

use Quoin\Exception\InvalidValue;
use Quoin\Laravel\Tag;

/** @internal Engine detail, not public API. It may change in any release. */
final class Field extends QuoinComponent
{
    /** @return list<string> */
    protected function ownAttributes(): array
    {
        return ['label', 'placeholder', 'lines', 'variant'];
    }

    protected function build(array $data): object
    {
        ['own' => $own] = $this->attrs($data);
        $field = \Quoin\Decorator\Field::make(...$this->children($data));
        $label = $this->slotOrAttribute($data, 'label');
        if ($label !== null) {
            $field = $field->label($label);
        }
        if (isset($own['placeholder'])) {
            $field = $field->placeholder($this->scalarOwn($own['placeholder'], 'placeholder'));
        }
        if (isset($own['lines'])) {
            $raw = $own['lines'];
            if (!is_int($raw) && !(is_string($raw) && preg_match('/^\d+$/', $raw) === 1)) {
                throw InvalidValue::for('lines', is_scalar($raw) ? (string) $raw : get_debug_type($raw))->because('expected a positive integer')->withContext('lines')->within('on ' . Tag::of('field'));
            }
            if ((int) $raw < 1) {
                throw InvalidValue::for('lines', (string) $raw)->because('expected a positive integer')->withContext('lines')->within('on ' . Tag::of('field'));
            }
            $field = $field->lines((int) $raw);
        }

        return $this->styled($field, $data);
    }
}
