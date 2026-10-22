<?php

declare(strict_types=1);

namespace Papercut\Laravel;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Support\Str;
use Papercut\Export\Output;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

final class PendingOutput implements Responsable
{
    private ?Output $output = null;

    /** @param \Closure(): Output $render */
    public function __construct(private readonly \Closure $render, private readonly string $basename = 'document')
    {
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

    public function download(?string $filename = null): Response
    {
        return $this->respond(HeaderUtils::DISPOSITION_ATTACHMENT, $filename);
    }

    public function inline(?string $filename = null): Response
    {
        return $this->respond(HeaderUtils::DISPOSITION_INLINE, $filename);
    }

    /** @param \Illuminate\Http\Request $request */
    public function toResponse($request): Response
    {
        return $this->inline();
    }

    private function respond(string $disposition, ?string $filename): Response
    {
        $output = $this->output();
        $default = $this->basename . '.' . $output->extension();
        $filename = str_replace(['/', '\\'], '-', $filename ?? $default);

        return new Response($output->bytes(), 200, [
            'Content-Type' => $output->mime(),
            'Content-Disposition' => HeaderUtils::makeDisposition($disposition, $filename, self::asciiFallback($filename, $default)),
            'Content-Length' => (string) strlen($output->bytes()),
        ]);
    }

    private static function asciiFallback(string $filename, string $default): string
    {
        $fallback = str_replace('%', '_', (string) preg_replace('/[^\x20-\x7e]/', '_', Str::ascii($filename)));

        return $fallback === '' ? $default : $fallback;
    }
}
