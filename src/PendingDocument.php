<?php

declare(strict_types=1);

namespace Papercut\Laravel;

use Papercut\Document;
use Papercut\Export\Encryption;

/** A resolved document waiting to be exported; each format method returns a {@see PendingOutput}. */
final class PendingDocument
{
    private ?PendingOutput $pdf = null;
    private ?PendingHtml $html = null;

    public function __construct(
        private readonly Document $document,
        private readonly PapercutManager $manager,
        private readonly ?Encryption $encryption = null,
    ) {
    }

    /**
     * A copy whose PDF opens only with $password (printing and copying allowed, editing not),
     * or with the permissions of an {@see Encryption} you build yourself. HTML is never encrypted.
     */
    public function encrypt(#[\SensitiveParameter] string|Encryption $password): self
    {
        return new self($this->document, $this->manager, is_string($password) ? Encryption::password($password) : $password);
    }

    public function document(): Document
    {
        return $this->document;
    }

    public function pdf(): PendingOutput
    {
        return $this->pdf ??= new PendingOutput(
            fn (): \Papercut\Export\Output => $this->document->toPdf($this->manager->pdfOptions()->withEncryption($this->encryption)),
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
