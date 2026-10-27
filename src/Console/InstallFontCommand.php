<?php

declare(strict_types=1);

namespace Papercut\Laravel\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Papercut\Backend\PhpPdf\PhpPdfBackend;

/**
 * Downloads a family's TrueType files from Google Fonts once, so PDFs can embed them like any local font.
 * Rendering never touches the network: the files live in the app and are registered in papercut.fonts.
 */
final class InstallFontCommand extends Command
{
    protected $signature = 'papercut:fonts:install
        {family : The family name as Google Fonts spells it, e.g. "Open Sans"}
        {--weights=400,700 : Comma-separated weights to download}
        {--italic : Download the italic of each weight as well}
        {--path=resources/fonts : Directory to save into, relative to the app root}
        {--force : Overwrite files that already exist}';

    protected $description = 'Download a font family from Google Fonts for use in PDFs';

    private const string CSS = 'https://fonts.googleapis.com/css2';
    private const string FILES = 'https://fonts.gstatic.com/';

    private const array NAMES = [
        100 => 'Thin', 200 => 'ExtraLight', 300 => 'Light', 400 => 'Regular', 500 => 'Medium',
        600 => 'SemiBold', 700 => 'Bold', 800 => 'ExtraBold', 900 => 'Black',
    ];

    public function handle(): int
    {
        $family = trim(self::str($this->argument('family')));
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9 ]*$/', $family) !== 1) {
            $this->error("Invalid family name \"$family\". Use letters, digits and spaces, as Google Fonts spells it.");

            return self::FAILURE;
        }
        $weights = $this->weights(self::str($this->option('weights')));
        if ($weights === null) {
            $this->error('Invalid --weights. Use whole numbers from 1 to 1000, separated by commas, e.g. --weights=400,700');

            return self::FAILURE;
        }
        $styles = $this->option('italic') ? ['normal', 'italic'] : ['normal'];

        $relative = trim(self::str($this->option('path')), '/') . '/' . str_replace(' ', '', $family);
        $dir = base_path($relative);

        $faces = [];
        $missing = [];
        foreach ($weights as $weight) {
            foreach ($styles as $style) {
                $url = $this->fileUrl($family, $weight, $style);
                if ($url === null) {
                    $missing[] = "$weight $style";
                    continue;
                }
                $faces[] = ['weight' => $weight, 'style' => $style, 'url' => $url];
            }
        }

        if ($faces === []) {
            $this->error("Google Fonts has no family called \"$family\" with those weights. Names are case-sensitive, e.g. \"Open Sans\".");

            return self::FAILURE;
        }
        foreach ($missing as $face) {
            $this->warn("Google Fonts doesn't offer $family at $face; skipped.");
        }

        if (!is_dir($dir) && !mkdir($dir, 0o755, true) && !is_dir($dir)) {
            $this->error("Could not create $relative");

            return self::FAILURE;
        }

        $config = [];
        foreach ($faces as $face) {
            $file = $this->fileName($family, $face['weight'], $face['style']);
            $path = "$dir/$file";
            $config[$face['weight']][$face['style']] = "$relative/$file";

            if (is_file($path) && !$this->option('force')) {
                $this->line("  <comment>exists</comment>  $relative/$file");
                continue;
            }
            $bytes = Http::timeout(30)->get($face['url'])->throw()->body();
            if (!self::isTrueType($bytes)) {
                $this->error("Google Fonts returned something other than a TrueType file for $family {$face['weight']} {$face['style']}.");

                return self::FAILURE;
            }
            file_put_contents($path, $bytes);
            try {
                // Only checks that the font loads; caching it is left to the first render.
                (new PhpPdfBackend(cacheDir: false))->loadFont($path);
            } catch (\Throwable $e) {
                unlink($path);
                $this->error("The downloaded $file could not be loaded: {$e->getMessage()}");

                return self::FAILURE;
            }
            $this->line("  <info>saved</info>   $relative/$file");
        }

        $this->newLine();
        $this->line('Add this to <comment>fonts</comment> in config/papercut.php:');
        $this->newLine();
        $this->line($this->snippet($family, $config));
        $this->newLine();
        $this->line('Check the family\'s licence on fonts.google.com before you ship it. Most are under the SIL Open Font License.');

        return self::SUCCESS;
    }

    /** @return list<int>|null */
    private function weights(string $option): ?array
    {
        $weights = [];
        foreach (explode(',', $option) as $part) {
            $part = trim($part);
            if (preg_match('/^\d{1,4}$/', $part) !== 1 || (int) $part < 1 || (int) $part > 1000) {
                return null;
            }
            $weights[(int) $part] = (int) $part;
        }
        ksort($weights);

        return array_values($weights);
    }

    /** The TrueType URL for one face, or null when Google does not offer it. */
    private function fileUrl(string $family, int $weight, string $style): ?string
    {
        // A non-browser user agent gets one complete TrueType file per face instead of WOFF2 subsets.
        $response = Http::timeout(30)
            ->withUserAgent('Papercut')
            ->get(self::CSS . '?family=' . str_replace('%20', '+', rawurlencode($family)) . ':ital,wght@' . ($style === 'italic' ? 1 : 0) . ',' . $weight);
        if (!$response->successful()) {
            return null;
        }
        if (preg_match('/src:\s*url\((https:\/\/[^)\s]+\.ttf)\)/', $response->body(), $m) !== 1 || !str_starts_with($m[1], self::FILES)) {
            return null;
        }

        return $m[1];
    }

    private function fileName(string $family, int $weight, string $style): string
    {
        $name = self::NAMES[$weight] ?? (string) $weight;
        if ($style === 'italic') {
            $name = $name === 'Regular' ? 'Italic' : $name . 'Italic';
        }

        return str_replace(' ', '', $family) . '-' . $name . '.ttf';
    }

    private static function str(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private static function isTrueType(string $bytes): bool
    {
        return in_array(substr($bytes, 0, 4), ["\x00\x01\x00\x00", 'true'], true);
    }

    /** @param array<int, array<string, string>> $config */
    private function snippet(string $family, array $config): string
    {
        $lines = ["'" . $family . "' => ["];
        foreach ($config as $weight => $styles) {
            $pairs = [];
            foreach ($styles as $style => $path) {
                $pairs[] = "'$style' => '$path'";
            }
            $lines[] = "    $weight => [" . implode(', ', $pairs) . '],';
        }
        $lines[] = '],';

        return implode("\n", $lines);
    }
}
