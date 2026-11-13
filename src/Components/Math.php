<?php

declare(strict_types=1);

namespace Papercut\Laravel\Components;

use Papercut\Laravel\Rendering\NodeRegistry;

/**
 * An equation inside running text: `<x-doc::math :math="$node" />` or `<x-doc::math math="x²" />` within a text
 * tag such as `<x-doc::p>` becomes an inline equation (Span::math). Outside a text tag it is the same as the block
 * `<x-doc::equation>`, so a lone `<x-doc::math>` is a displayed equation. Inside a text tag it takes no style
 * attributes; set size and colour on the surrounding text.
 *
 * @internal Part of Papercut's engine, not its public API. It may change in any release.
 */
final class Math extends PapercutComponent
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
