<?php

declare(strict_types=1);

namespace Papercut\Laravel\Html;

use Papercut\Text\FontFace;

/**
 * Names an invokable class for `papercut.html.font_url`, used when `papercut.html.fonts` is `link`.
 * Return a URL for the face, or null to skip it. Built by the container, so it can take dependencies.
 */
interface FontUrlResolver
{
    public function __invoke(FontFace $face): ?string;
}
