<?php

declare(strict_types=1);

namespace Papercut\Laravel\Tests\Feature;

use Papercut\Laravel\Facades\Papercut;
use Papercut\Laravel\Tests\TestCase;

final class ExamplesTest extends TestCase
{
    private const string EXAMPLES = __DIR__ . '/../../../../examples/laravel';

    /** @var list<string> */
    private array $temps = [];

    protected function tearDown(): void
    {
        foreach ($this->temps as $f) {
            @unlink($f);
        }
        parent::tearDown();
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('view.paths', [self::EXAMPLES . '/views']);
    }

    /**
     * @return array<string, mixed>
     */
    private static function data(string $name): array
    {
        /** @var array<string, array<string, mixed>> $all */
        $all = require self::EXAMPLES . '/data.php';

        return $all[$name];
    }

    private function render(string $view): string
    {
        $pdf = Papercut::view($view, self::data($view))->pdf()->toString();
        $base = (string) tempnam(sys_get_temp_dir(), 'pc-ex');
        $file = $base . '.pdf';
        $this->temps[] = $base;
        $this->temps[] = $file;
        file_put_contents($file, $pdf);
        exec('qpdf --check ' . escapeshellarg($file) . ' 2>&1', $out, $code);
        self::assertSame(0, $code, implode("\n", $out));

        return $file;
    }

    /** @return list<string> text of each page */
    private function pages(string $file): array
    {
        exec('pdftotext -layout ' . escapeshellarg($file) . ' - 2>/dev/null', $out);

        return explode("\f", implode("\n", $out));
    }

    public function testInvoice(): void
    {
        $text = implode("\n", $this->pages($this->render('invoice')));
        self::assertStringContainsString('INVOICE', $text);
        self::assertStringContainsString('Total due', $text);
        self::assertStringContainsString('$', $text);
        $expected = '$' . number_format(self::data('invoice')['total'], 2);
        self::assertStringContainsString($expected, $text);
        self::assertStringContainsString('Page 1 of 1', $text);
    }

    public function testTranscript(): void
    {
        $pages = $this->pages($this->render('transcript'));
        self::assertGreaterThan(2, count($pages));
        self::assertStringContainsString('Page 1 of', $pages[0]);
        self::assertStringContainsString('Official Transcript', $pages[1]);
        self::assertStringContainsString('Course', $pages[1], 'table header repeats on page 2');
    }

    public function testResponses(): void
    {
        $text = implode("\n", $this->pages($this->render('responses')));
        foreach (['Response details', 'Before you continue', 'No response', 'About right', 'Strongly agree', 'Jane Smith, closing round'] as $expected) {
            self::assertStringContainsString($expected, $text);
        }
    }

    public function testReport(): void
    {
        $pages = $this->pages($this->render('report'));
        self::assertStringNotContainsString('Appendix A', $pages[0]);
        $found = null;
        foreach ($pages as $i => $p) {
            if (str_contains($p, 'Appendix A: Glossary')) {
                $found = $i;
            }
        }
        self::assertNotNull($found);
        self::assertGreaterThan(0, $found);
    }
}
