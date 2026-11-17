<?php

declare(strict_types=1);

namespace Quoin\Laravel\Tests\Support;

use Quoin\Html\{FontAnswer, FontRequest, FontSource};

/** A custom source: answers https://fonts.example.test/<family>.css for Acme only. */
final class RegistryFonts implements FontSource
{
    public function __invoke(FontRequest $request): ?FontAnswer
    {
        return strcasecmp($request->family, 'Acme') === 0
            ? FontAnswer::stylesheet('https://fonts.example.test/' . rawurlencode($request->family) . '.css')
            : null;
    }
}
