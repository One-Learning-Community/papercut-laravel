<?php

return [
    // Blade tag prefix: <x-doc::page>, <x-doc::table>... Lowercase letters, digits and dashes.
    // Read at boot; templates written with another prefix fail with Blade's "Unable to locate a class or view".
    'prefix' => 'doc',
    // Default page size used by <x-doc::page> when no size is given: a0-a6, b4, b5, letter, legal,
    // tabloid, executive, or a width and height such as '8.5in 11in'
    'paper' => 'a4',
    'margin' => '20mm',
    // Font families: name => [weight => ['normal' => path, 'italic' => path]]. Inter is always available.
    'fonts' => [],
    'fallback_fonts' => [],
    // The name Word uses for a family: family => name. Meant for metric-compatible pairs (Cousine => Courier New, the default for Cousine).
    'font_word_names' => [],
    // Theme overrides: font_family, mono_family (what the `monospace` font keyword means; default Cousine), font_size (pt float), color, line_height
    'theme' => [],
    // Named styles usable as style="card": name => attribute array (same keys as tag attributes)
    'styles' => [],
    // throw | clip | null (null = throw in local/testing, clip elsewhere)
    'overflow' => null,
    // Turn headings (and anything with bookmark="…") into the PDF's bookmarks panel
    'bookmarks' => true,
    // Strict export: throw instead of dropping content a format cannot render. Consider true in tests.
    'strict' => false,
    // Base directory for relative <x-doc::image src>
    'image_path' => null, // null = public_path()
    // Parsed-font cache directory: null = storage_path('framework/cache/papercut'), false = disabled, string = path
    'cache_dir' => null,
    'html' => [
        // Fonts in HTML output: none (system-ui fallback stack) | embed (base64 @font-face, large)
        // | link (@font-face with URLs from font_url; needs font_url)
        // | sources (each family goes to the first class in font_sources that accepts it; works as is, via Google)
        'fonts' => 'none',
        // Class name implementing Papercut\Laravel\Html\ImageUrlResolver, built by the container.
        // null embeds images as data URIs. Use a class, not a closure: class names survive `php artisan config:cache`.
        'image_url' => null,
        // Class name implementing Papercut\Laravel\Html\FontUrlResolver, built by the container. Needed when fonts is link.
        'font_url' => null,
        // Used when fonts is sources: an ordered list of classes implementing Papercut\Html\FontSource, built by the container.
        // Each font family goes to the first source that accepts it: list specific sources first, catch-all hosts such as Google last.
        // PRIVACY: with GoogleFonts the visitor's browser fetches the CSS and fonts from the host and sends it their IP address
        // (see core's "Fonts in HTML"). Bunny Fonts (fonts.bunny.net) is a host that states it does not log IP addresses, but the
        // request still reaches a third party. Only local_fonts (your own files, embedded or linked), or fonts = embed or link,
        // keeps it all on your side.
        // Check your CSP: style-src, font-src and, for embedded fonts, font-src data:.
        // Class names only (they survive `php artisan config:cache`). Read only when fonts is sources.
        'font_sources' => [\Papercut\Html\GoogleFonts::class],
        // Settings for the GoogleFonts source. Checked only when GoogleFonts is in font_sources.
        'google_fonts' => [
            'host' => 'https://fonts.googleapis.com', // or https://fonts.bunny.net
            'display' => 'swap', // auto|block|swap|fallback|optional
            'families' => null, // null = every family; or a list of names (allowlist)
        ],
        // Settings for the LocalFonts source (families registered in papercut.fonts). Checked only when LocalFonts is in font_sources.
        'local_fonts' => [
            'mode' => 'embed', // embed | link (link uses font_url)
            'families' => null, // null = every registered family; or a list of names
        ],
    ],
    // <x-doc::markup>: HTML or plain text in, document nodes out.
    'markup' => [
        // Invokable class implementing Papercut\Laravel\Markup\ImageResolver: turns an <img src> into bytes, or null when missing.
        'images' => null,
        // 'theme' (HTML gives structure; the theme styles it) or 'original' (inline style="" applied on top).
        'styles' => 'theme',
        // 'drop' or 'keep' paragraphs that hold only &nbsp; or <br>.
        'blank_paragraphs' => 'drop',
    ],
    'preview' => [
        'enabled' => env('PAPERCUT_PREVIEW', false),
        'path' => '_papercut',
    ],
];
