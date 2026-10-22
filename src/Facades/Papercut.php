<?php

declare(strict_types=1);

namespace Papercut\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use Papercut\Laravel\PapercutManager;

/**
 * @method static \Papercut\Laravel\PendingDocument view(string $view, array<string, mixed> $data = [])
 * @method static \Papercut\Laravel\PendingDocument make(\Papercut\Document $doc)
 * @method static \Papercut\Export\PdfOptions pdfOptions()
 * @method static \Papercut\Markup\MarkupOptions markupOptions()
 * @method static void preview(string $view, \Closure|array<string, mixed> $data = [])
 *
 * @see PapercutManager
 */
final class Papercut extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return PapercutManager::class;
    }
}
