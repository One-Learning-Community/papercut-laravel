<?php

declare(strict_types=1);

namespace Papercut\Laravel\Components;

use Papercut\Component;
use Papercut\Exception\InvalidValue;
use Papercut\Laravel\Tag;
use Papercut\Node as PapercutNode;

/** `<x-doc::node :node="$n" />` embeds a PHP-built node or component as is. */
final class Node extends PapercutComponent
{
    protected function ownAttributes(): array
    {
        return ['node'];
    }

    protected function build(array $data): object
    {
        $node = $this->attrs($data)['own']['node'] ?? null;
        if (!$node instanceof PapercutNode && !$node instanceof Component) {
            throw InvalidValue::for('node', get_debug_type($node))->withContext('node')->within('on ' . Tag::of('node'));
        }

        return $node;
    }
}
