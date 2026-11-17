<?php

declare(strict_types=1);

namespace Quoin\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use Quoin\Laravel\QuoinManager;

/**
 * @method static \Quoin\Laravel\PendingDocument view(string $view, array<string, mixed> $data = [])
 * @method static \Quoin\Laravel\PendingDocument make(\Quoin\Document $doc)
 * @method static \Quoin\Export\PdfOptions pdfOptions()
 * @method static \Quoin\Markup\MarkupOptions markupOptions()
 * @method static void preview(string $view, \Closure|array<string, mixed> $data = [])
 *
 * @see QuoinManager
 */
final class Quoin extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return QuoinManager::class;
    }
}
