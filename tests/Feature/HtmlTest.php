<?php

declare(strict_types=1);

namespace Quoin\Laravel\Tests\Feature;

use Orchestra\Testbench\Attributes\DefineEnvironment;
use Illuminate\Support\Facades\Route;
use Quoin\Export\FontMode;
use Quoin\Laravel\Facades\Quoin;
use Quoin\Laravel\QuoinManager;
use Quoin\Laravel\PendingHtml;
use Quoin\Laravel\PendingOutput;
use Quoin\Laravel\Tests\Support\{AbstractImageUrl, CdnFontUrl, CdnHost, CdnImageUrl};
use Quoin\Laravel\Tests\TestCase;

final class HtmlTest extends TestCase
{
    /** @param \Illuminate\Foundation\Application $app */
    protected function previewLocal($app): void
    {
        $app['config']->set('quoin.preview.enabled', true);
        $app['env'] = 'local';
    }

    public function test_html_in_blade_renders_fragment(): void
    {
        $doc = Quoin::view('pdf.hello', ['name' => 'Ann'])->document();
        $out = view('pdf.embedded', ['doc' => Quoin::make($doc)])->render();

        $this->assertSame(1, substr_count($out, 'class="quoin'));
        $this->assertStringNotContainsString('<html', $out);
        $this->assertStringContainsString('Hello Ann', $out);
    }

    public function test_page_and_fragment_are_pending_outputs(): void
    {
        $h = Quoin::view('pdf.hello', ['name' => 'Ann'])->html();

        $this->assertInstanceOf(PendingOutput::class, $h->page());
        $this->assertInstanceOf(PendingOutput::class, $h->fragment());
        $this->assertSame($h->page(), $h->page());
        $this->assertStringStartsWith('<!DOCTYPE html>', $h->page()->toString());
        $this->assertStringStartsWith('<style>', $h->fragment()->toString());
        $this->assertStringNotContainsString('<html', $h->fragment()->toString());
        $this->assertSame((string) $h, $h->fragment()->toString());
    }

    public function test_escaped_blade_echo_prints_the_fragment(): void
    {
        $escaped = view('pdf.embedded-escaped', ['doc' => Quoin::view('pdf.hello', ['name' => 'Ann'])])->render();

        $this->assertSame(1, substr_count($escaped, 'class="quoin'));
        $this->assertStringNotContainsString('&lt;', $escaped);
    }

    public function test_page_and_fragment_save_and_download(): void
    {
        $h = Quoin::view('pdf.hello', ['name' => 'Ann'])->html();
        $dir = sys_get_temp_dir() . '/quoin-html-' . uniqid();
        mkdir($dir);

        $h->page()->save("$dir/page.html");
        $h->fragment()->save("$dir/fragment.html");

        $this->assertStringContainsString('<html', (string) file_get_contents("$dir/page.html"));
        $this->assertStringNotContainsString('<html', (string) file_get_contents("$dir/fragment.html"));
        $this->assertStringContainsString('Hello Ann', (string) file_get_contents("$dir/fragment.html"));
        unlink("$dir/page.html");
        unlink("$dir/fragment.html");
        rmdir($dir);

        $r = $h->fragment()->download();
        $this->assertStringContainsString('attachment; filename=document.html', (string) $r->headers->get('Content-Disposition'));
        $this->assertStringNotContainsString('<html', (string) $r->getContent());
    }

    public function test_pending_html_has_no_ambiguous_string_methods(): void
    {
        foreach (['toString', 'save', 'download', 'inline', 'output'] as $method) {
            $this->assertFalse(method_exists(PendingHtml::class, $method), $method);
        }
    }

    public function test_page_and_fragment_render_once(): void
    {
        $doc = Quoin::view('pdf.hello', ['name' => 'Ann'])->document();
        $options = $this->app()->make(QuoinManager::class)->htmlOptions();
        $pages = $fragments = 0;
        $h = new PendingHtml(
            function () use ($doc, $options, &$fragments) {
                $fragments++;

                return $doc->toHtml($options);
            },
            function () use ($doc, $options, &$pages) {
                $pages++;

                return $doc->toHtml($options->withFullPage(true));
            },
        );

        $h->page()->toString();
        $h->page()->toString();
        $h->fragment()->toString();
        $h->fragment()->toString();
        $h->toHtml();
        (string) $h;
        $h->toResponse(request());

        $this->assertSame(1, $pages);
        $this->assertSame(1, $fragments);
    }

    public function test_route_returning_html_is_a_full_page(): void
    {
        Route::get('/h', fn () => Quoin::view('pdf.hello', ['name' => 'Ann'])->html());

        $r = $this->get('/h');

        $r->assertOk();
        $this->assertSame('text/html; charset=UTF-8', $r->headers->get('Content-Type'));
        $this->assertNull($r->headers->get('Content-Disposition'));
        $this->assertSame((string) strlen((string) $r->getContent()), $r->headers->get('Content-Length'));
        $this->assertStringContainsString('<html', (string) $r->getContent());
        $this->assertStringContainsString('Hello Ann', (string) $r->getContent());
    }

    public function test_download_html_has_attachment_headers(): void
    {
        $r = Quoin::view('pdf.hello', ['name' => 'Ann'])->html()->page()->download();

        $this->assertSame('text/html; charset=UTF-8', $r->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename=document.html', (string) $r->headers->get('Content-Disposition'));
        $this->assertStringContainsString('<html', (string) $r->getContent());
    }

    public function test_html_fonts_config_none_embed_and_invalid(): void
    {
        $this->assertSame(FontMode::None, $this->app()->make(QuoinManager::class)->htmlOptions()->fontMode());

        config(['quoin.html.fonts' => 'embed']);
        $this->app()->forgetInstance(QuoinManager::class); // @phpstan-ignore method.notFound
        $this->assertSame(FontMode::Embed, $this->app()->make(QuoinManager::class)->htmlOptions()->fontMode());

        config(['quoin.html.fonts' => 'LINK', 'quoin.html.font_url' => CdnFontUrl::class]);
        $this->app()->forgetInstance(QuoinManager::class); // @phpstan-ignore method.notFound
        $this->assertSame(FontMode::Link, $this->app()->make(QuoinManager::class)->htmlOptions()->fontMode());

        config(['quoin.html.fonts' => 'bogus']);
        $this->app()->forgetInstance(QuoinManager::class); // @phpstan-ignore method.notFound
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid quoin.html.fonts: 'bogus'; expected none, embed, link or sources");
        $this->app()->make(QuoinManager::class)->htmlOptions();
    }

    public function test_html_inline_svgs_config(): void
    {
        $this->assertFalse($this->app()->make(QuoinManager::class)->htmlOptions()->inlineSvgs());

        config(['quoin.html.inline_svgs' => true]);
        $this->assertTrue($this->fresh()->htmlOptions()->inlineSvgs());
    }

    private function fresh(): QuoinManager
    {
        $this->app()->forgetInstance(QuoinManager::class); // @phpstan-ignore method.notFound

        return $this->app()->make(QuoinManager::class);
    }

    private function useLogo(): void
    {
        $dir = $this->tempDir('quoin-logo');
        file_put_contents($dir . '/logo.png', (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
        config(['quoin.image_path' => $dir]);
    }

    public function test_image_url_resolver_class_is_used(): void
    {
        $this->useLogo();
        config(['quoin.html.image_url' => CdnImageUrl::class]);

        $out = Quoin::view('pdf.logo')->html()->fragment()->toString();

        $this->assertStringContainsString('src="https://cdn.test/x.png"', $out);
        $this->assertStringNotContainsString('data:image', $out);
    }

    public function test_resolver_is_built_by_the_container(): void
    {
        $this->useLogo();
        $host = new CdnHost();
        $host->base = 'https://bound.test';
        $this->app()->instance(CdnHost::class, $host);
        config(['quoin.html.image_url' => CdnImageUrl::class]);

        $out = Quoin::view('pdf.logo')->html()->fragment()->toString();

        $this->assertStringContainsString('src="https://bound.test/x.png"', $out);
    }

    public function test_link_fonts_use_font_url_resolver(): void
    {
        config(['quoin.html.fonts' => 'link', 'quoin.html.font_url' => CdnFontUrl::class]);

        $out = Quoin::view('pdf.hello', ['name' => 'Ann'])->html()->fragment()->toString();

        $this->assertStringContainsString('@font-face', $out);
        $this->assertStringContainsString('https://cdn.test/fonts/400.ttf', $out);
    }

    public function test_link_without_font_url_throws(): void
    {
        config(['quoin.html.fonts' => 'link']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('quoin.html.fonts is link, which needs quoin.html.font_url');
        $this->fresh()->htmlOptions();
    }

    public function test_closure_in_config_is_rejected_by_name(): void
    {
        config(['quoin.html.image_url' => fn () => 'x']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid quoin.html.image_url: Closure; expected an invokable class name implementing Quoin\Laravel\Html\ImageUrlResolver or null');
        $this->fresh()->htmlOptions();
    }

    public function test_font_url_closure_is_rejected_by_name(): void
    {
        config(['quoin.html.font_url' => fn () => 'x']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid quoin.html.font_url: Closure; expected an invokable class name implementing Quoin\Laravel\Html\FontUrlResolver or null');
        $this->fresh()->htmlOptions();
    }

    public function test_class_without_interface_is_rejected(): void
    {
        config(['quoin.html.image_url' => \stdClass::class]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid quoin.html.image_url: 'stdClass'; expected an invokable class name implementing Quoin\Laravel\Html\ImageUrlResolver or null");
        $this->fresh()->htmlOptions();
    }

    public function test_unbuildable_resolver_names_the_key(): void
    {
        config(['quoin.html.image_url' => AbstractImageUrl::class]);

        try {
            $this->fresh()->htmlOptions();
            $this->fail('Expected InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Cannot build quoin.html.image_url resolver ' . var_export(AbstractImageUrl::class, true) . ': ', $e->getMessage());
            $this->assertInstanceOf(\Illuminate\Contracts\Container\BindingResolutionException::class, $e->getPrevious());
        }
    }

    public function test_nonexistent_resolver_class_is_rejected(): void
    {
        config(['quoin.html.image_url' => 'App\\Nope']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid quoin.html.image_url: 'App\\\\Nope'; expected an invokable class name implementing Quoin\\Laravel\\Html\\ImageUrlResolver or null");
        $this->fresh()->htmlOptions();
    }

    public function test_non_string_resolver_value_is_rejected(): void
    {
        config(['quoin.html.font_url' => 42]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid quoin.html.font_url: 42; expected an invokable class name implementing Quoin\\Laravel\\Html\\FontUrlResolver or null');
        $this->fresh()->htmlOptions();
    }

    public function test_config_with_class_names_survives_var_export(): void
    {
        $this->useLogo();
        $cfg = ['image_url' => CdnImageUrl::class, 'font_url' => CdnFontUrl::class, 'fonts' => 'link'];
        /** @var array<string, string> $round */
        $round = eval('return ' . var_export($cfg, true) . ';');
        config(['quoin.html' => $round]);

        $m = $this->fresh();
        $this->assertSame(FontMode::Link, $m->htmlOptions()->fontMode());
        $this->assertStringContainsString('https://cdn.test/x.png', Quoin::view('pdf.logo')->html()->fragment()->toString());
    }

    public function test_shared_options_reach_both_formats(): void
    {
        config(['quoin.theme' => ['color' => '#ff0000']]);
        $m = $this->app()->make(QuoinManager::class);

        $this->assertSame($m->exportOptions()->theme(), $m->pdfOptions()->theme());
        $this->assertSame($m->exportOptions()->theme(), $m->htmlOptions()->theme());
        $this->assertStringContainsString('#ff0000', Quoin::view('pdf.hello', ['name' => 'A'])->html()->page()->toString());
    }

    #[DefineEnvironment('previewLocal')]
    public function test_preview_format_html_and_unknown(): void
    {
        Quoin::preview('pdf.hello', ['name' => 'World']);

        $html = $this->get('/_quoin/pdf.hello?format=html');
        $html->assertOk();
        $this->assertSame('text/html; charset=UTF-8', $html->headers->get('Content-Type'));
        $this->assertStringContainsString('<html', (string) $html->getContent());

        $this->get('/_quoin/pdf.hello?format=pdf')->assertHeader('Content-Type', 'application/pdf');
        $this->get('/_quoin/pdf.hello')->assertHeader('Content-Type', 'application/pdf');
        $this->get('/_quoin/pdf.hello?format=odt')->assertNotFound();
    }
}
