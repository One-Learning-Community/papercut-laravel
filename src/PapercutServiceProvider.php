<?php

declare(strict_types=1);

namespace Papercut\Laravel;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Papercut\Html\{GoogleFonts, LocalFonts};
use Papercut\Laravel\Console\{FontsCommand, InstallFontCommand};
use Papercut\Laravel\Preview\{PreviewController, PreviewRegistry};
use Papercut\Laravel\Rendering\NodeRegistry;

final class PapercutServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/papercut.php', 'papercut');

        $this->app->singleton(NodeRegistry::class);

        $this->app->singleton(PreviewRegistry::class);

        $this->app->scoped(PapercutManager::class);

        // Config is read and checked when a source is first built (the first sources-mode export), not at boot.
        $this->app->bind(GoogleFonts::class, static function (): GoogleFonts {
            $host = config('papercut.html.google_fonts.host', 'https://fonts.googleapis.com');
            if (!is_string($host) || !GoogleFonts::validHost($host)) {
                throw new \InvalidArgumentException('Invalid papercut.html.google_fonts.host: ' . self::show($host) . '; expected an http or https URL with no query or fragment');
            }
            $display = config('papercut.html.google_fonts.display', 'swap');
            if (!is_string($display) || !in_array($display, GoogleFonts::DISPLAYS, true)) {
                throw new \InvalidArgumentException('Invalid papercut.html.google_fonts.display: ' . self::show($display) . '; expected ' . self::joinOr(GoogleFonts::DISPLAYS));
            }

            return new GoogleFonts($host, $display, self::families('google_fonts'));
        });

        $this->app->bind(LocalFonts::class, static function (): LocalFonts {
            $mode = config('papercut.html.local_fonts.mode', 'embed');
            if ($mode !== 'embed' && $mode !== 'link') {
                throw new \InvalidArgumentException('Invalid papercut.html.local_fonts.mode: ' . self::show($mode) . '; expected embed or link');
            }
            $families = self::families('local_fonts');
            if ($mode === 'embed') {
                return LocalFonts::embed($families);
            }
            $fontUrl = PapercutManager::fontUrlResolver();
            if ($fontUrl === null) {
                throw new \InvalidArgumentException('papercut.html.local_fonts.mode is link, which needs papercut.html.font_url');
            }

            return LocalFonts::link($fontUrl, $families);
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/papercut.php' => $this->app->configPath('papercut.php'),
        ], 'papercut-config');

        if ($this->app->runningInConsole()) {
            $this->commands([FontsCommand::class, InstallFontCommand::class]);
        }

        Blade::componentNamespace('Papercut\\Laravel\\Components', Tag::prefix());

        if (config('papercut.preview.enabled') && $this->app->environment('local')) {
            Route::prefix((string) config('papercut.preview.path', '_papercut'))->group(static function (): void {
                Route::get('/', [PreviewController::class, 'index']);
                Route::get('/{view}', [PreviewController::class, 'show']);
            });
        }
    }

    private static function show(mixed $v): string
    {
        return is_scalar($v) ? var_export($v, true) : get_debug_type($v);
    }

    /** @param list<string> $items */
    private static function joinOr(array $items): string
    {
        $last = array_pop($items);

        return implode(', ', $items) . ' or ' . $last;
    }

    /** @return list<string>|null */
    private static function families(string $block): ?array
    {
        $families = config("papercut.html.$block.families");
        if ($families === null) {
            return null;
        }
        if (!is_array($families) || !array_is_list($families) || array_filter($families, static fn (mixed $f): bool => !is_string($f)) !== []) {
            throw new \InvalidArgumentException("Invalid papercut.html.$block.families: " . self::show($families) . '; expected null or a list of family names');
        }

        return $families;
    }
}
