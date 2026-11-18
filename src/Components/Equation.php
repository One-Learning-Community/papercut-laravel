<?php

declare(strict_types=1);

namespace Quoin\Laravel\Components;

/**
 * A displayed equation: `<x-doc::equation :math="$node" />` takes a node built with Phactorial\Math,
 * `<x-doc::equation math="x = 1" />` takes a string, tokenized as in Equation::of(), and
 * `<x-doc::equation tex="\frac{a}{b}" />` reads TeX (give math or tex, not both). Align it with text-align.
 *
 * @internal Engine detail, not public API. It may change in any release.
 */
final class Equation extends QuoinComponent
{
    use BuildsEquation;
}
