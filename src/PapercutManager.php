<?php

declare(strict_types=1);

namespace Papercut\Laravel;

use Papercut\Document;
use Papercut\Exception\{InvalidValue, MissingExporter, UnknownAttribute};
use Papercut\Laravel\Exception\InvalidStructure;
use Papercut\Laravel\Preview\PreviewRegistry;
use Papercut\Laravel\Rendering\NodeRegistry;
use Papercut\OverflowMode;
use Papercut\Export\{Exporter, ExportOptions, FidelityLevel, FidelityReport, FontMode, HtmlOptions, PdfOptions};
use Papercut\Html\FontSource;
use Papercut\Markup\{BlankParagraphs, MarkupOptions, StyleMode};
use Papercut\Style\{Style, Theme};
use Papercut\Text\{FontFace, FontSet};
use Papercut\Value\Color;

/** Not final only so tests can stand in a missing exporter; see docxExporterClass(). */
class PapercutManager
{
    private ?ExportOptions $export = null;
    private ?PdfOptions $pdf = null;
    private ?HtmlOptions $html = null;
    private ?MarkupOptions $markup = null;

    /** @var array<string, \Closure> markup image resolvers by class name without a leading backslash, one instance per class */
    private array $imageResolvers = [];

    public function __construct(private readonly NodeRegistry $registry, private readonly PreviewRegistry $previews)
    {
    }

    /** @param \Closure|array<string, mixed> $data */
    public function preview(string $view, \Closure|array $data = []): void
    {
        $this->previews->register($view, $data);
    }

    /** @param array<string, mixed> $data */
    public function view(string $view, array $data = []): PendingDocument
    {
        $document = $this->registry->scope(function () use ($view, $data): object {
            $html = view($view, $data)->render();
            $segments = array_values(array_filter(
                $this->registry->segments($html),
                static fn (object|string $s): bool => !is_string($s) || trim($s) !== '',
            ));

            if (count($segments) !== 1 || !$segments[0] instanceof Document) {
                throw new InvalidStructure('A Papercut view must have exactly one ' . Tag::of('document') . ' root; found ' . $this->describe($segments) . " in view [$view]");
            }
            if ($this->registry->remaining() > 0) {
                if (preg_match('/\[\[pc:[0-9a-f]{16}:\d+\]\]/i', $html) === 1) {
                    throw new InvalidStructure('A slot containing ' . Tag::any() . " tags was modified as a string (e.g. strtoupper(\$slot)) in view [$view]");
                }
                throw new InvalidStructure('Some ' . Tag::any() . " elements were rendered outside the document in view [$view]");
            }

            return $segments[0];
        }, $view);
        assert($document instanceof Document);

        return $this->make($document);
    }

    public function make(Document $doc): PendingDocument
    {
        return new PendingDocument($doc, $this);
    }

    /** Options shared by every format: theme, fonts, warning sink and strict mode. */
    public function exportOptions(): ExportOptions
    {
        return $this->export ??= $this->buildExportOptions();
    }

    public function pdfOptions(): PdfOptions
    {
        return $this->pdf ??= $this->buildPdfOptions();
    }

    public function htmlOptions(): HtmlOptions
    {
        return $this->html ??= $this->buildHtmlOptions();
    }

    /**
     * The Word exporter from papercut/docx, which is optional: the class is named as a string, never imported.
     *
     * @throws MissingExporter when papercut/docx is not installed
     */
    public function docxExporter(): Exporter
    {
        $class = static::docxExporterClass();
        if (!class_exists($class) || !is_subclass_of($class, Exporter::class)) {
            throw MissingExporter::for('docx', 'papercut/docx');
        }

        return new $class();
    }

    protected static function docxExporterClass(): string
    {
        return 'Papercut\\Docx\\DocxExporter';
    }

    /** Options for `<x-doc::markup>`, built from `papercut.markup` on first use. */
    public function markupOptions(): MarkupOptions
    {
        return $this->markup ??= $this->buildMarkupOptions();
    }

    /**
     * The resolver for a markup tag's `images` attribute: the class is built through the container once per manager and
     * shared by every markup tag naming it, and by `papercut.markup.images` when that names the same class.
     *
     * @return \Closure(string): ?string
     */
    public function markupImageResolver(mixed $class): \Closure
    {
        if (!is_string($class) || !is_subclass_of($class, Markup\ImageResolver::class)) {
            throw self::invalidMarkupImages($class);
        }

        return $this->sharedImageResolver($class, 'markup.images', Tag::of('markup') . ' images');
    }

    /** The error for a bad `images` attribute on a markup tag. */
    private static function invalidMarkupImages(mixed $value): \InvalidArgumentException
    {
        return new \InvalidArgumentException('Invalid ' . Tag::of('markup') . ' images: ' . (is_scalar($value) ? var_export($value, true) : get_debug_type($value)) . '; expected an invokable class name implementing ' . Markup\ImageResolver::class . " or 'none'");
    }

    /** @return \Closure(string): ?string */
    private function sharedImageResolver(string $class, string $key, ?string $what = null): \Closure
    {
        $class = ltrim($class, '\\');
        if (!isset($this->imageResolvers[$class])) {
            /** @var Markup\ImageResolver $instance */
            $instance = self::build($key, $class, $what);
            $this->imageResolvers[$class] = $instance(...);
        }

        return $this->imageResolvers[$class];
    }

    private function buildMarkupOptions(): MarkupOptions
    {
        $styles = config('papercut.markup.styles', 'theme');
        $blank = config('papercut.markup.blank_paragraphs', 'drop');
        $mode = is_string($styles) ? StyleMode::tryFrom($styles) : null;
        if ($mode === null) {
            throw new \InvalidArgumentException('Invalid papercut.markup.styles: ' . (is_scalar($styles) ? var_export($styles, true) : get_debug_type($styles)) . "; expected 'theme' or 'original'");
        }
        $paragraphs = is_string($blank) ? BlankParagraphs::tryFrom($blank) : null;
        if ($paragraphs === null) {
            throw new \InvalidArgumentException('Invalid papercut.markup.blank_paragraphs: ' . (is_scalar($blank) ? var_export($blank, true) : get_debug_type($blank)) . "; expected 'drop' or 'keep'");
        }

        $images = self::configuredClass('markup.images', Markup\ImageResolver::class);

        return new MarkupOptions(styles: $mode, images: $images === null ? null : $this->sharedImageResolver($images, 'markup.images'), blankParagraphs: $paragraphs);
    }

    private function buildExportOptions(): ExportOptions
    {
        $fonts = (new FontSet(array_map(
            static fn (array $face): FontFace => new FontFace($face['family'], $face['path'], $face['weight'], $face['style']),
            FontConfig::faces(),
        )))->withFallbacks(...FontConfig::fallbacks());
        $families = array_map(static fn (FontFace $face): string => $face->family, $fonts->effectiveFaces());
        foreach (FontConfig::wordNames($families) as $family => $wordName) {
            $fonts = $fonts->withWordName($family, $wordName);
        }

        $cfg = (array) config('papercut.theme', []);
        try {
            $color = isset($cfg['color']) ? Color::parse((string) $cfg['color']) : null;
        } catch (InvalidValue $e) {
            throw $e->within('in config papercut.theme.color');
        }
        $mono = $cfg['mono_family'] ?? null;
        if ($mono !== null && !is_string($mono)) {
            throw new \InvalidArgumentException('Invalid papercut.theme.mono_family: ' . (is_scalar($mono) ? var_export($mono, true) : get_debug_type($mono)) . '; expected a font family name');
        }
        if ($mono !== null && (trim($mono) === '' || strtolower(trim($mono)) === 'monospace')) {
            throw new \InvalidArgumentException('Invalid papercut.theme.mono_family: ' . var_export($mono, true) . '; the keyword `monospace` stands for it, so name a real font family');
        }
        if ($mono !== null && !in_array(strtolower(trim($mono)), array_map('strtolower', $families), true)) {
            throw new \InvalidArgumentException("Invalid papercut.theme.mono_family: '$mono' is neither bundled nor registered in papercut.fonts");
        }
        $theme = Theme::default()->with(
            fontFamily: isset($cfg['font_family']) ? (string) $cfg['font_family'] : null,
            fontSize: self::positive($cfg, 'font_size'),
            color: $color,
            lineHeight: self::positive($cfg, 'line_height'),
            monoFamily: $mono,
        );
        /** @var array<string, array<string, mixed>> $styles */
        $styles = (array) config('papercut.styles', []);
        foreach ($styles as $name => $attrs) {
            try {
                $theme = $theme->withStyle((string) $name, Style::fromAttributes($attrs));
            } catch (InvalidValue|UnknownAttribute $e) {
                throw $e->within("in config papercut.styles.$name");
            }
        }

        return new ExportOptions(
            fonts: $fonts,
            theme: $theme,
            onWarning: static function (string $msg, ?FidelityReport $report = null): void {
                if ($report?->level === FidelityLevel::Approximated) {
                    logger()->debug('[papercut] ' . $msg);
                } else {
                    logger()->warning('[papercut] ' . $msg);
                }
            },
            strict: self::strict(),
        );
    }

    private function buildPdfOptions(): PdfOptions
    {
        $overflow = config('papercut.overflow');
        $key = is_string($overflow) ? strtolower($overflow) : $overflow;
        if ($key !== null && $key !== 'throw' && $key !== 'clip') {
            throw new \InvalidArgumentException('Invalid papercut.overflow: ' . (is_scalar($overflow) ? var_export($overflow, true) : get_debug_type($overflow)) . '; expected throw, clip or null');
        }
        $mode = match ($key) {
            'throw' => OverflowMode::Throw,
            'clip' => OverflowMode::Clip,
            default => app()->environment('local', 'testing') ? OverflowMode::Throw : OverflowMode::Clip,
        };

        $bookmarks = config('papercut.bookmarks', true);
        if (!is_bool($bookmarks)) {
            throw new \InvalidArgumentException('Invalid papercut.bookmarks: ' . (is_scalar($bookmarks) ? var_export($bookmarks, true) : get_debug_type($bookmarks)) . '; expected true or false');
        }

        $base = $this->exportOptions();

        return new PdfOptions(
            theme: $base->theme(),
            fonts: $base->fonts(),
            onWarning: $base->onWarning(),
            strict: $base->strict(),
            overflow: $mode,
            cacheDir: self::cacheDir(),
            bookmarks: $bookmarks,
        );
    }

    private function buildHtmlOptions(): HtmlOptions
    {
        $fonts = config('papercut.html.fonts', 'none');
        $mode = match (is_string($fonts) ? strtolower($fonts) : null) {
            'none' => FontMode::None,
            'embed' => FontMode::Embed,
            'link' => FontMode::Link,
            'sources' => FontMode::Sources,
            default => throw new \InvalidArgumentException('Invalid papercut.html.fonts: ' . (is_scalar($fonts) ? var_export($fonts, true) : get_debug_type($fonts)) . '; expected none, embed, link or sources'),
        };

        $imageUrl = self::resolver('html.image_url', Html\ImageUrlResolver::class);
        $fontUrl = self::fontUrlResolver();
        if ($mode === FontMode::Link && $fontUrl === null) {
            throw new \InvalidArgumentException('papercut.html.fonts is link, which needs papercut.html.font_url');
        }

        $sources = $mode === FontMode::Sources ? self::fontSources() : [];

        $base = $this->exportOptions();

        return new HtmlOptions(
            theme: $base->theme(),
            fonts: $base->fonts(),
            onWarning: $base->onWarning(),
            strict: $base->strict(),
            fontMode: $mode,
            imageUrl: $imageUrl,
            fontUrl: $fontUrl,
            fontSources: $sources,
        );
    }

    /** The `html.font_url` resolver, or null when unset. Also used by the LocalFonts binding. */
    public static function fontUrlResolver(): ?\Closure
    {
        return self::resolver('html.font_url', Html\FontUrlResolver::class);
    }

    /** @return list<FontSource> */
    private static function fontSources(): array
    {
        $classes = config('papercut.html.font_sources', [\Papercut\Html\GoogleFonts::class]);
        if (!is_array($classes) || !array_is_list($classes)) {
            throw new \InvalidArgumentException('Invalid papercut.html.font_sources: ' . (is_scalar($classes) ? var_export($classes, true) : get_debug_type($classes)) . '; expected a list of class names implementing ' . FontSource::class);
        }
        if ($classes === []) {
            throw new \InvalidArgumentException('papercut.html.fonts is sources, which needs at least one class in papercut.html.font_sources');
        }
        $sources = [];
        foreach ($classes as $i => $class) {
            $key = "html.font_sources.$i";
            if (!is_string($class) || !is_subclass_of($class, FontSource::class)) {
                throw new \InvalidArgumentException("Invalid papercut.$key: " . (is_scalar($class) ? var_export($class, true) : get_debug_type($class)) . '; expected an invokable class name implementing ' . FontSource::class);
            }
            /** @var FontSource $instance */
            $instance = self::build($key, $class);
            $sources[] = $instance;
        }

        return $sources;
    }

    /** Builds a class through the container; a factory's own InvalidArgumentException passes through unchanged. */
    private static function build(string $key, string $class, ?string $what = null): object
    {
        try {
            return app($class);
        } catch (\Illuminate\Contracts\Container\BindingResolutionException $e) {
            $inner = $e->getPrevious();
            if ($inner instanceof \InvalidArgumentException) {
                throw $inner;
            }
            throw new \InvalidArgumentException('Cannot build ' . ($what ?? "papercut.$key") . ' resolver ' . var_export($class, true) . ': ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * @param string $key config path under `papercut.`, e.g. `html.image_url`
     * @param class-string<Html\ImageUrlResolver|Html\FontUrlResolver|Markup\ImageResolver> $interface
     */
    private static function resolver(string $key, string $interface): ?\Closure
    {
        $class = self::configuredClass($key, $interface);
        if ($class === null) {
            return null;
        }
        /** @var Html\ImageUrlResolver|Html\FontUrlResolver|Markup\ImageResolver $instance */
        $instance = self::build($key, $class);

        return $instance(...);
    }

    /**
     * The class name set at `papercut.$key`, or null when unset.
     *
     * @param class-string $interface the interface the class must implement
     * @return class-string|null
     */
    private static function configuredClass(string $key, string $interface): ?string
    {
        $class = config('papercut.' . $key);
        if ($class === null) {
            return null;
        }
        if (!is_string($class) || !is_subclass_of($class, $interface)) {
            throw new \InvalidArgumentException("Invalid papercut.$key: " . (is_scalar($class) ? var_export($class, true) : get_debug_type($class)) . "; expected an invokable class name implementing $interface or null");
        }

        return $class;
    }

    private static function strict(): bool
    {
        $strict = config('papercut.strict', false);
        if (!is_bool($strict)) {
            throw new \InvalidArgumentException('Invalid papercut.strict: ' . (is_scalar($strict) ? var_export($strict, true) : get_debug_type($strict)) . '; expected true or false');
        }

        return $strict;
    }

    private static function cacheDir(): string|false
    {
        $dir = config('papercut.cache_dir');
        if ($dir === null) {
            // not the temp dir: PHP-FPM and the CLI can each see a different one
            return storage_path('framework/cache/papercut');
        }
        if ($dir === false || (is_string($dir) && $dir !== '')) {
            return $dir;
        }
        throw new \InvalidArgumentException('Invalid papercut.cache_dir: ' . (is_scalar($dir) ? var_export($dir, true) : get_debug_type($dir)) . '; expected a directory path, false or null');
    }

    /** @param array<mixed> $cfg */
    private static function positive(array $cfg, string $key): ?float
    {
        if (!isset($cfg[$key])) {
            return null;
        }
        $v = $cfg[$key];
        if (!is_numeric($v) || (float) $v <= 0) {
            throw new \InvalidArgumentException("Invalid papercut.theme.$key: " . (is_scalar($v) ? var_export($v, true) : get_debug_type($v)) . '; expected a number greater than 0');
        }

        return (float) $v;
    }

    /** @param list<object|string> $segments */
    private function describe(array $segments): string
    {
        if ($segments === []) {
            return 'nothing';
        }
        if (count($segments) > 1) {
            return count($segments) . ' nodes';
        }
        $only = $segments[0];
        if (is_string($only)) {
            return 'text';
        }
        $short = substr((string) strrchr('\\' . $only::class, '\\'), 1);

        return 'a <' . strtolower((string) preg_replace('/(?<!^)[A-Z]/', '-$0', $short)) . '>';
    }
}
