<?php

declare(strict_types=1);

namespace Quoin\Laravel\Preview;

use Illuminate\Http\Response as HttpResponse;
use Quoin\Exception\MissingExporter;
use Quoin\Laravel\QuoinManager;
use Symfony\Component\HttpFoundation\Response;

/** @internal Engine detail, not public API. It may change in any release. */
final class PreviewController
{
    public function __construct(private readonly PreviewRegistry $registry, private readonly QuoinManager $quoin)
    {
    }

    public function index(): Response
    {
        abort_unless(app()->isLocal(), 404);
        $prefix = '/' . trim((string) config('quoin.preview.path', '_quoin'), '/');
        $items = '';
        foreach ($this->registry->names() as $name) {
            $e = e($name);
            $items .= '<li><a href="' . e($prefix . '/' . rawurlencode($name)) . '">' . $e . '</a></li>';
        }

        return new HttpResponse(
            '<!doctype html><html><head><meta charset="utf-8"><title>Previews</title></head><body>'
            . '<h1>Previews</h1><ul>' . $items . '</ul></body></html>',
            200,
            ['Content-Type' => 'text/html; charset=UTF-8'],
        );
    }

    public function show(string $view): Response
    {
        abort_unless(app()->isLocal(), 404);
        abort_unless($this->registry->has($view), 404);

        $format = request()->query('format', 'pdf');
        abort_unless(in_array($format, ['pdf', 'html', 'docx'], true), 404);

        if ($format === 'docx') {
            try {
                $this->quoin->docxExporter();
            } catch (MissingExporter $e) {
                // Not found, with the install hint: the preview is local-only, so the reason is worth showing.
                return new HttpResponse($e->getMessage(), 404, ['Content-Type' => 'text/plain; charset=UTF-8']);
            }
        }

        $pending = $this->quoin->view($view, $this->registry->data($view));

        return match ($format) {
            'html' => $pending->html()->toResponse(request()),
            'docx' => $pending->docx()->inline(),
            default => $pending->pdf()->inline(),
        };
    }
}
