<?php

declare(strict_types=1);

namespace Papercut\Laravel\Tests\Feature;

use Papercut\Laravel\Facades\Papercut;
use Papercut\Laravel\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Pins the exact PDF bytes of v0.1 for the Laravel example views. Phase 0 must not change them.
 *
 * Record (only on unchanged v0.1 code): UPDATE_GOLDEN=1 vendor/bin/phpunit --filter GoldenBytesTest
 * A php-pdf upgrade can change the font objects alone (php-pdf 1.26: subset tags, CIDSet, checksums).
 * Re-record then, after checking that the drawing itself is unchanged: delete the changed keys and record.
 */
final class GoldenBytesTest extends TestCase
{
    private const string EXAMPLES = __DIR__ . '/../../../../examples/laravel';
    private const string GOLDEN = __DIR__ . '/golden/pdf-v0.1.json';

    /**
     * @param \Illuminate\Foundation\Application $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('view.paths', [self::EXAMPLES . '/views']);
        // v0.1 had no bookmarks; with them off, everything drawn must still match its bytes.
        $app['config']->set('papercut.bookmarks', false);
    }

    /** @return iterable<string, array{string}> */
    public static function cases(): iterable
    {
        foreach (['invoice', 'transcript', 'report'] as $view) {
            yield $view => [$view];
        }
    }

    #[DataProvider('cases')]
    public function test_pdf_bytes_match_v01(string $name): void
    {
        /** @var array<string, array<string, mixed>> $all */
        $all = require self::EXAMPLES . '/data.php';
        $hash = hash('sha256', $this->pdfBytes($name, $all[$name]));
        $golden = self::golden();
        if (getenv('UPDATE_GOLDEN') === '1' && !array_key_exists($name, $golden)) {
            self::recordGolden($name, $hash);
            $golden[$name] = $hash;
        }
        self::assertArrayHasKey($name, $golden, "No golden hash for $name; this phase must not add cases");
        self::assertSame($golden[$name], $hash, "PDF bytes for $name changed; Phase 0 must keep PDF output byte-identical");
    }

    /** @param array<string, mixed> $data */
    private function pdfBytes(string $view, array $data): string
    {
        // The example views set no createdAt, so the PDF would embed the current time. Pin it by rebuilding the
        // resolved document with a fixed date; only the test does this, the examples stay as they are.
        $document = Papercut::view($view, $data)->document()->createdAt(new \DateTimeImmutable('2026-01-01T00:00:00Z'));

        return Papercut::make($document)->pdf()->toString();
    }

    public function test_golden_keys_match_the_cases_exactly(): void
    {
        $names = array_keys(iterator_to_array(self::cases()));
        sort($names);
        $keys = array_keys(self::golden());
        sort($keys);
        self::assertSame($names, $keys, 'Golden file keys must equal the test cases exactly');
    }

    /** @return array<string, string> */
    private static function golden(): array
    {
        if (!is_file(self::GOLDEN)) {
            return [];
        }
        /** @var array<string, string> $golden */
        $golden = json_decode((string) file_get_contents(self::GOLDEN), true, flags: JSON_THROW_ON_ERROR);

        return $golden;
    }

    private static function recordGolden(string $name, string $hash): void
    {
        $golden = self::golden();
        $golden[$name] = $hash;
        ksort($golden);
        file_put_contents(self::GOLDEN, json_encode($golden, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
    }
}
