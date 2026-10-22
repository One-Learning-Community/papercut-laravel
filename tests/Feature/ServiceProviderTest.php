<?php

declare(strict_types=1);

namespace Papercut\Laravel\Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Papercut\Laravel\Tests\TestCase;

final class ServiceProviderTest extends TestCase
{
    public function testConfigIsMerged(): void
    {
        $this->assertSame('a4', config('papercut.paper'));
        $this->assertSame('20mm', config('papercut.margin'));
        $this->assertSame('_papercut', config('papercut.preview.path'));
    }

    public function testRegistersPapercutComponentNamespace(): void
    {
        $this->assertSame('Papercut\\Laravel\\Components', Blade::getClassComponentNamespaces()['doc'] ?? null);
    }
}
