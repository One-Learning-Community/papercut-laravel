<?php

declare(strict_types=1);

namespace Papercut\Laravel\Components;

/**
 * A displayed equation: `<x-doc::equation :math="$node" />` takes a node built with Papercut\Math\Math,
 * `<x-doc::equation math="x = 1" />` takes a string, tokenized as in Equation::of(). Align it with text-align.
 *
 * @internal Part of Papercut's engine, not its public API. It may change in any release.
 */
final class Equation extends PapercutComponent
{
    use BuildsEquation;
}
