<?php

declare(strict_types=1);

namespace Papercut\Laravel;

use Illuminate\Support\Traits\Macroable;
use Papercut\Document;
use Papercut\Export\Encryption;

/** A resolved document waiting to be exported; each format method returns a {@see PendingOutput}. */
final class PendingDocument
{
    use Macroable;

    private ?PendingOutput $pdf = null;
    private ?PendingHtml $html = null;
    private ?PendingOutput $docx = null;

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

    /**
     * A copy whose document has these theme values merged on top of the view's own (see {@see ThemeConfig::KEYS}).
     * It runs after the view rendered: it can change values and restyle existing style names, but a new style
     * name a view uses must come from config or the view's `:theme`.
     *
     * @param array<mixed> $values
     */
    public function theme(array $values): self
    {
        $override = ThemeConfig::override($values, 'in ->theme()', $this->manager->fontFamilies());

        return new self($this->document->theme($override), $this->manager, $this->encryption);
    }

    public function document(): Document
    {
        return $this->document;
    }

    public function pdf(): PendingOutput
    {
        return $this->pdf ??= new PendingOutput(
            fn (): \Papercut\Export\Output => $this->document->toPdf($this->manager->pdfOptions()->withEncryption($this->encryption)),
            'document',
            'pdf',
            'application/pdf',
        );
    }

    /** Word export; needs papercut/docx, and throws MissingExporter on output without it. */
    public function docx(): PendingOutput
    {
        return $this->docx ??= new PendingOutput(
            fn (): \Papercut\Export\Output => $this->document->export($this->manager->docxExporter(), $this->manager->exportOptions()),
            'document',
            'docx',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
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
