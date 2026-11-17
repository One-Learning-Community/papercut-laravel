<?php

declare(strict_types=1);

namespace Quoin\Laravel\Components;

use Quoin\Exception\InvalidValue;
use Quoin\Laravel\Exception\InvalidStructure;
use Quoin\Laravel\Tag;

/** @internal Engine detail, not public API. It may change in any release. */
final class Toc extends QuoinComponent
{
    protected function ownAttributes(): array
    {
        return ['levels', 'title', 'leaders'];
    }

    protected function build(array $data): object
    {
        $where = 'on ' . Tag::of('toc');
        if (trim((string) ($data['slot'] ?? '')) !== '') {
            throw new InvalidStructure(Tag::of('toc') . ' must not have content');
        }
        $own = $this->attrs($data)['own'];
        $toc = \Quoin\Toc::make();
        if (isset($own['levels'])) {
            $raw = $this->scalarOwn($own['levels'], 'levels');
            if (preg_match('/^\s*(\d+)\s*(?:-\s*(\d+)\s*)?$/', $raw, $m) !== 1) {
                throw InvalidValue::for('levels', $raw)->because('expected a level like "2" or a range like "1-3"')->withContext('levels')->within($where);
            }
            try {
                $toc = $toc->levels((int) $m[1], (int) ($m[2] ?? $m[1]));
            } catch (InvalidValue $e) {
                throw InvalidValue::for('levels', $raw)->because('levels run from 1 to 4, and the first cannot pass the last')->withContext('levels')->within($where);
            }
        }
        if (isset($own['title'])) {
            $toc = $toc->title($this->scalarOwn($own['title'], 'title'));
        }
        if (array_key_exists('leaders', $own)) {
            $toc = $toc->leaders($this->boolOwn($own['leaders'], 'leaders'));
        }

        return $this->styled($toc, $data);
    }
}
