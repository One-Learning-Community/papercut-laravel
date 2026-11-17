<?php

declare(strict_types=1);

namespace Quoin\Laravel\Html;

use Quoin\Text\FontFace;

/**
 * Names an invokable class for `quoin.html.font_url`, used when `quoin.html.fonts` is `link`.
 * Return a URL for the face, or null to skip it. Built by the container, so it can take dependencies.
 */
interface FontUrlResolver
{
    public function __invoke(FontFace $face): ?string;
}
