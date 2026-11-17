<?php

declare(strict_types=1);

namespace Quoin\Laravel\Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Quoin\Laravel\Tests\TestCase;

final class ServiceProviderTest extends TestCase
{
    public function testConfigIsMerged(): void
    {
        $this->assertSame('a4', config('quoin.paper'));
        $this->assertSame('20mm', config('quoin.margin'));
        $this->assertSame('_quoin', config('quoin.preview.path'));
    }

    public function testRegistersQuoinComponentNamespace(): void
    {
        $this->assertSame('Quoin\\Laravel\\Components', Blade::getClassComponentNamespaces()['doc'] ?? null);
    }
}
