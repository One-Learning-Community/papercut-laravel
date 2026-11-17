<?php

declare(strict_types=1);

namespace Papercut\Laravel\Components;

use Papercut\Laravel\Exception\InvalidStructure;
use Papercut\Laravel\Tag;
use Papercut\Math\Math;
use Papercut\Math\Node\Node as MathNode;

/**
 * The shared build of the equation and math components: a `math` attribute (string or node) or a `tex` attribute (a TeX string), and no content.
 *
 * @internal Part of Papercut's engine, not its public API. It may change in any release.
 */
trait BuildsEquation
{
    protected function ownAttributes(): array
    {
        return ['math', 'tex'];
    }

    protected function build(array $data): object
    {
        $tag = Tag::of($this->tag());
        if (trim($this->text($data)) !== '') {
            throw new InvalidStructure("$tag must not have content; use the math or tex attribute");
        }
        ['own' => $own] = $this->attrs($data);
        $math = $own['math'] ?? null;
        $tex = $own['tex'] ?? null;
        if ($math !== null && $tex !== null) {
            throw new InvalidStructure("$tag takes either math or tex, not both");
        }
        if ($tex !== null) {
            if (!is_string($tex)) {
                throw new InvalidStructure("$tag needs the tex attribute to be a string of TeX");
            }

            return $this->styled(\Papercut\Equation::of(Math::tex($tex)), $data);
        }
        if (!is_string($math) && !$math instanceof MathNode) {
            throw new InvalidStructure("$tag requires a math attribute (a string, or a node built with Papercut\\Math\\Math) or a tex attribute (a TeX string)");
        }

        if (is_string($math) && self::isMathml($math)) {
            $math = Math::mathml($math);
        }

        return $this->styled(\Papercut\Equation::of($math), $data);
    }
}
