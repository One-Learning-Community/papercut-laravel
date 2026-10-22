<?php

declare(strict_types=1);

namespace Papercut\Laravel\Preview;

use Illuminate\Http\Response as HttpResponse;
use Papercut\Laravel\PapercutManager;
use Symfony\Component\HttpFoundation\Response;

final class PreviewController
{
    public function __construct(private readonly PreviewRegistry $registry, private readonly PapercutManager $papercut)
    {
    }

    public function index(): Response
    {
        abort_unless(app()->isLocal(), 404);
        $prefix = '/' . trim((string) config('papercut.preview.path', '_papercut'), '/');
        $items = '';
        foreach ($this->registry->names() as $name) {
            $e = e($name);
            $items .= '<li><a href="' . e($prefix . '/' . rawurlencode($name)) . '">' . $e . '</a></li>';
        }

        return new HttpResponse(
            '<!doctype html><html><head><meta charset="utf-8"><title>Papercut previews</title></head><body>'
            . '<h1>Papercut previews</h1><ul>' . $items . '</ul></body></html>',
            200,
            ['Content-Type' => 'text/html; charset=UTF-8'],
        );
    }

    public function show(string $view): Response
    {
        abort_unless(app()->isLocal(), 404);
        abort_unless($this->registry->has($view), 404);

        $format = request()->query('format', 'pdf');
        abort_unless($format === 'pdf' || $format === 'html', 404);

        $pending = $this->papercut->view($view, $this->registry->data($view));

        return $format === 'html' ? $pending->html()->toResponse(request()) : $pending->pdf()->inline();
    }
}
