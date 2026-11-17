<?php

declare(strict_types=1);

namespace Quoin\Laravel\Components;

use Quoin\Laravel\Rendering\NodeRegistry;

/**
 * An equation inside running text: `<x-doc::math :math="$node" />`, `<x-doc::math math="x²" />` or
 * `<x-doc::math tex="\frac{a}{b}" />` within a text tag such as `<x-doc::p>` becomes an inline equation (Span::math). Outside a text tag it is the same as the block
 * `<x-doc::equation>`, so a lone `<x-doc::math>` is a displayed equation. Inside a text tag it takes no style
 * attributes; set size and color on the surrounding text.
 *
 * @internal Engine detail, not public API. It may change in any release.
 */
final class Math extends QuoinComponent
{
    use BuildsEquation {
        build as private buildEquation;
    }

    protected function build(array $data): object
    {
        $node = $this->buildEquation($data);
        app(NodeRegistry::class)->markInline($node);

        return $node;
    }
}
