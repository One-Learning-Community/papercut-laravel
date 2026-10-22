<?php

declare(strict_types=1);

namespace Papercut\Laravel\Console;

use Illuminate\Console\Command;
use Papercut\Backend\PhpPdf\PhpPdfBackend;
use Papercut\Laravel\FontConfig;
use Papercut\Text\{FontRegistry, FontStyle};

final class FontsCommand extends Command
{
    protected $signature = 'papercut:fonts';
    protected $description = 'List the fonts available to Papercut and check that each file loads';

    public function handle(): int
    {
        $backend = new PhpPdfBackend();
        $faces = [...FontRegistry::withDefaults($backend)->faces(), ...FontConfig::faces()];

        $rows = [];
        $failed = false;
        foreach ($faces as $face) {
            $status = $this->status($backend, $face['path']);
            $failed = $failed || $status !== 'ok';
            $rows[] = [$face['family'], $face['weight'], $face['style'] === FontStyle::Italic ? 'italic' : 'normal', $face['path'], $status];
        }
        $this->table(['Family', 'Weight', 'Style', 'Path', 'Status'], $rows);

        $known = array_map(strtolower(...), array_column($faces, 'family'));
        foreach (FontConfig::fallbacks() as $family) {
            if (!in_array(strtolower($family), $known, true)) {
                $this->warn(sprintf('Fallback family "%s" is not registered.', $family));
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function status(PhpPdfBackend $backend, string $path): string
    {
        if (!is_file($path) || !is_readable($path)) {
            return 'missing';
        }
        try {
            $backend->loadFont($path);
        } catch (\Throwable) {
            return 'invalid';
        }

        return 'ok';
    }
}
