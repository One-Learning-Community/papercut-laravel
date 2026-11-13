<?php

declare(strict_types=1);

namespace Papercut\Laravel\Components;

use Papercut\Laravel\Exception\InvalidStructure;
use Papercut\Laravel\Tag;
use Papercut\Math\Node\Node as MathNode;

/**
 * The shared build of the equation and math components: a `math` attribute (string or node) and no content.
 *
 * @internal Part of Papercut's engine, not its public API. It may change in any release.
 */
trait BuildsEquation
{
    protected function ownAttributes(): array
    {
        return ['math'];
    }

    protected function build(array $data): object
    {
        $tag = Tag::of($this->tag());
        if (trim($this->text($data)) !== '') {
            throw new InvalidStructure("$tag must not have content; use the math attribute");
        }
        ['own' => $own] = $this->attrs($data);
        $math = $own['math'] ?? null;
        if (!is_string($math) && !$math instanceof MathNode) {
            throw new InvalidStructure("$tag requires a math attribute: a string, or a node built with Papercut\\Math\\Math");
        }

        return $this->styled(\Papercut\Equation::of($math), $data);
    }
}
