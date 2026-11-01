<?php

declare(strict_types=1);

namespace Papercut\Laravel;

use Illuminate\Contracts\Filesystem\Factory as Filesystem;
use Illuminate\Contracts\Mail\Attachable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Mail\Attachment;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Macroable;
use Papercut\Export\Output;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PendingOutput implements Attachable, Responsable
{
    use Macroable;

    private ?Output $output = null;

    /**
     * @param \Closure(): Output $render
     * @param string|null $extension the format's file extension, so a default name needs no render; null asks the output
     * @param string|null $mime the format's MIME type, so an attachment needs no render to be described; null asks the output
     */
    public function __construct(
        private readonly \Closure $render,
        private readonly string $basename = 'document',
        private readonly ?string $extension = null,
        private readonly ?string $mime = null,
    ) {
    }

    public function output(): Output
    {
        return $this->output ??= ($this->render)();
    }

    public function toString(): string
    {
        return $this->output()->bytes();
    }

    public function save(string $path): static
    {
        $this->output()->save($path);

        return $this;
    }

    /**
     * Writes the output to a Laravel filesystem disk.
     *
     * @param string|null $disk the disk name; null uses the default disk
     * @param array<string, mixed>|string $options passed to the disk's put(), e.g. 'public' for visibility
     * @throws \RuntimeException when the disk reports a failed write
     */
    public function store(string $path, ?string $disk = null, array|string $options = []): static
    {
        if (!app(Filesystem::class)->disk($disk)->put($path, $this->output()->bytes(), $options)) {
            throw new \RuntimeException("Could not write '$path' to the " . ($disk === null ? 'default' : "'$disk'") . ' disk');
        }

        return $this;
    }

    public function download(?string $filename = null): Response
    {
        return $this->respond(HeaderUtils::DISPOSITION_ATTACHMENT, $filename);
    }

    public function inline(?string $filename = null): Response
    {
        return $this->respond(HeaderUtils::DISPOSITION_INLINE, $filename);
    }

    /**
     * A download sent as a streamed response, named like {@see download()}. The document renders when the
     * response is sent, not before. Livewire actions (and so Filament actions) send a file only from a
     * streamed or file response, so use this there.
     */
    public function streamDownload(?string $filename = null): StreamedResponse
    {
        $default = $this->defaultName();
        $filename = self::cleanFilename($filename ?? $default);

        return new StreamedResponse(function (): void {
            echo $this->output()->bytes();
        }, 200, [
            'Content-Type' => $this->mime ?? $this->output()->mime(),
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $filename, self::asciiFallback($filename, $default)),
        ]);
    }

    /**
     * For `$mailable->attach($pdf)`: named `document.pdf` (or `.docx`, `.html`) with the output's MIME type.
     * Naming the attachment does not render; the document renders when it is attached, once.
     */
    public function toMailAttachment(): Attachment
    {
        return $this->attachment();
    }

    /** A mail attachment with your own name, cleaned the way {@see download()} cleans it. */
    public function attachment(?string $filename = null): Attachment
    {
        $attachment = Attachment::fromData(fn (): string => $this->output()->bytes(), self::cleanFilename($filename ?? $this->defaultName()));

        return $this->mime === null ? $attachment->withMime($this->output()->mime()) : $attachment->withMime($this->mime);
    }

    /** @param \Illuminate\Http\Request $request */
    public function toResponse($request): Response
    {
        return $this->inline();
    }

    private function respond(string $disposition, ?string $filename): Response
    {
        $output = $this->output();
        $default = $this->defaultName();
        $filename = self::cleanFilename($filename ?? $default);

        return new Response($output->bytes(), 200, [
            'Content-Type' => $output->mime(),
            'Content-Disposition' => HeaderUtils::makeDisposition($disposition, $filename, self::asciiFallback($filename, $default)),
            'Content-Length' => (string) strlen($output->bytes()),
        ]);
    }

    private function defaultName(): string
    {
        return $this->basename . '.' . ($this->extension ?? $this->output()->extension());
    }

    private static function cleanFilename(string $filename): string
    {
        return str_replace(['/', '\\'], '-', $filename);
    }

    private static function asciiFallback(string $filename, string $default): string
    {
        $fallback = str_replace('%', '_', (string) preg_replace('/[^\x20-\x7e]/', '_', Str::ascii($filename)));

        return $fallback === '' ? $default : $fallback;
    }
}
