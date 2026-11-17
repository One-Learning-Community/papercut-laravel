<?php

declare(strict_types=1);

namespace Quoin\Laravel\Components;

use Quoin\Component;
use Quoin\Exception\InvalidValue;
use Quoin\Laravel\Tag;
use Quoin\Node as QuoinNode;

/**
 * `<x-doc::node :node="$n" />` embeds a PHP-built node or component as is.
 *
 * @internal Engine detail, not public API. It may change in any release.
 */
final class Node extends QuoinComponent
{
    protected function ownAttributes(): array
    {
        return ['node'];
    }

    protected function build(array $data): object
    {
        $node = $this->attrs($data)['own']['node'] ?? null;
        if (!$node instanceof QuoinNode && !$node instanceof Component) {
            throw InvalidValue::for('node', get_debug_type($node))->withContext('node')->within('on ' . Tag::of('node'));
        }

        return $node;
    }
}
