<?php

declare(strict_types=1);

namespace Papercut\Laravel\Tests\Feature;

use Illuminate\Support\Facades\Blade;
use InvalidArgumentException;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use Papercut\Laravel\Tag;
use Papercut\Laravel\Tests\TestCase;

final class PrefixTest extends TestCase
{
    protected function usePaperPrefix(mixed $app): void
    {
        $app['config']->set('papercut.prefix', 'paper');
    }

    public function test_default_prefix_is_doc(): void
    {
        $namespaces = Blade::getClassComponentNamespaces();

        $this->assertSame('Papercut\\Laravel\\Components', $namespaces['doc'] ?? null);
        $this->assertArrayNotHasKey('pdf', $namespaces);
        $this->assertSame('<x-doc::page>', Tag::of('page'));
        $this->assertSame('<x-doc::*>', Tag::any());
    }

    #[DefineEnvironment('usePaperPrefix')]
    public function test_custom_prefix_renders_and_errors_use_it(): void
    {
        $this->assertArrayNotHasKey('doc', Blade::getClassComponentNamespaces());
        $this->assertArrayHasKey('paper', Blade::getClassComponentNamespaces());
        $this->assertNotNull($this->nodeFrom('<x-paper::document><x-paper::page><x-paper::p>Hi</x-paper::p></x-paper::page></x-paper::document>'));

        try {
            $this->nodeFrom('<x-paper::page bogus-attribute="1"></x-paper::page>');
            $this->fail('Expected an exception');
        } catch (\Throwable $e) {
            $this->assertStringContainsString('on <x-paper::page>', $e->getMessage());
        }
    }

    public function test_old_x_pdf_tags_fail_loudly(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unable to locate a class or view for component [pdf::document]');
        Blade::render('<x-' . 'pdf::document></x-' . 'pdf::document>');
    }

    public function test_invalid_prefix_is_rejected(): void
    {
        config(['papercut.prefix' => 'Bad Prefix']);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid papercut.prefix');
        Tag::prefix();
    }

    public function test_prefix_cache_follows_config_changes(): void
    {
        $this->assertSame('doc', Tag::prefix());
        config(['papercut.prefix' => 'other']);
        $this->assertSame('other', Tag::prefix());
        config(['papercut.prefix' => 'Bad Prefix']);
        $this->expectException(InvalidArgumentException::class);
        Tag::prefix();
    }

    public function test_app_components_under_doc_folder_do_not_conflict(): void
    {
        $node = $this->nodeFrom('<x-doc::document><x-doc::page><x-doc::text><x-doc.badge>Gold</x-doc.badge></x-doc::text></x-doc::page></x-doc::document>');
        $this->assertStringContainsString('Badge Gold', \Papercut\Laravel\Facades\Papercut::make($node)->html()->page()->toString());
    }
}
