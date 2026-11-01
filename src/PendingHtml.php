<?php

declare(strict_types=1);

namespace Papercut\Laravel;

use Illuminate\Contracts\Mail\Attachable;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Mail\Attachment;
use Illuminate\Support\Traits\Macroable;
use Papercut\Export\Output;
use Symfony\Component\HttpFoundation\Response;

/**
 * HTML output. `(string)`, `{{ }}` and `{!! !!}` print the embeddable fragment; `page()` and
 * `fragment()` give a PendingOutput for explicit strings, files and downloads.
 */
final class PendingHtml implements Attachable, Htmlable, \Stringable, Responsable
{
    use Macroable;

    private const string MIME = 'text/html; charset=UTF-8';

    private ?PendingOutput $pageOutput = null;
    private ?PendingOutput $fragmentOutput = null;

    /**
     * @param \Closure(): Output $fragment the embeddable `.papercut` fragment
     * @param \Closure(): Output $page the full HTML page
     */
    public function __construct(private readonly \Closure $fragment, private readonly \Closure $page)
    {
    }

    /** The full HTML page. */
    public function page(): PendingOutput
    {
        return $this->pageOutput ??= new PendingOutput($this->page, 'document', 'html', self::MIME);
    }

    /** The embeddable `.papercut` fragment. */
    public function fragment(): PendingOutput
    {
        return $this->fragmentOutput ??= new PendingOutput($this->fragment, 'document', 'html', self::MIME);
    }

    /** The fragment, for `{!! $doc->html() !!}` inside another Blade view. */
    public function toHtml(): string
    {
        return $this->fragment()->toString();
    }

    /** The fragment; lets `{{ $doc->html() }}` and `(string) $doc->html()` print it. */
    public function __toString(): string
    {
        return $this->toHtml();
    }

    /** The full page as `document.html`, for `$mailable->attach($doc->html())`. */
    public function toMailAttachment(): Attachment
    {
        return $this->page()->toMailAttachment();
    }

    /** The full page as a bare 200 response, with no Content-Disposition. @param \Illuminate\Http\Request $request */
    public function toResponse($request): Response
    {
        $output = $this->page()->output();

        return new Response($output->bytes(), 200, [
            'Content-Type' => $output->mime(),
            'Content-Length' => (string) strlen($output->bytes()),
        ]);
    }
}
