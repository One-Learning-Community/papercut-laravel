<?php

declare(strict_types=1);

namespace Papercut\Laravel;

use Papercut\Document;

/** A resolved document waiting to be exported; each format method returns a {@see PendingOutput}. */
final class PendingDocument
{
    private ?PendingOutput $pdf = null;
    private ?PendingHtml $html = null;

    public function __construct(private readonly Document $document, private readonly PapercutManager $manager)
    {
    }

    public function document(): Document
    {
        return $this->document;
    }

    public function pdf(): PendingOutput
    {
        return $this->pdf ??= new PendingOutput(
            fn (): \Papercut\Export\Output => $this->document->toPdf($this->manager->pdfOptions()),
        );
    }

    public function html(): PendingHtml
    {
        return $this->html ??= new PendingHtml(
            fn (): \Papercut\Export\Output => $this->document->toHtml($this->manager->htmlOptions()),
            fn (): \Papercut\Export\Output => $this->document->toHtml($this->manager->htmlOptions()->withFullPage(true)),
        );
    }
}
