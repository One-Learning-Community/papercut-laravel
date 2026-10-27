<?php

declare(strict_types=1);

namespace Papercut\Laravel\Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Papercut\Backend\PhpPdf\PhpPdfBackend;
use Papercut\Laravel\FontConfig;
use Papercut\Laravel\Tests\TestCase;

final class InstallFontTest extends TestCase
{
    /** A few glyphs of Inter (SIL OFL), small enough that loading it costs almost nothing. */
    private const string INTER = __DIR__ . '/../Fixtures/fonts/InterSubset.ttf';
    private const string DIR = 'storage/framework/testing/papercut-fonts';

    protected function tearDown(): void
    {
        File::deleteDirectory(base_path(self::DIR));
        parent::tearDown();
    }

    /**
     * Fakes Google: each listed "weight,ital" pair has a TrueType file; everything else answers 400.
     *
     * @param list<string> $faces
     */
    private function fakeGoogle(string $family, array $faces, ?string $bytes = null): void
    {
        $bytes ??= (string) file_get_contents(self::INTER);
        Http::fake(function (Request $request) use ($family, $faces, $bytes) {
            $url = $request->url();
            if (str_starts_with($url, 'https://fonts.gstatic.com/')) {
                return Http::response($bytes);
            }
            foreach ($faces as $face) {
                [$weight, $ital] = explode(',', $face);
                if ($url === 'https://fonts.googleapis.com/css2?family=' . str_replace(' ', '+', $family) . ":ital,wght@$ital,$weight") {
                    return Http::response("@font-face {\n  src: url(https://fonts.gstatic.com/s/x/v1/$ital-$weight.ttf) format('truetype');\n}");
                }
            }

            return Http::response('<html>', 400);
        });
    }

    /**
     * @param array<string, mixed> $args
     * @return array{int, string}
     */
    private function install(array $args): array
    {
        $code = Artisan::call('papercut:fonts:install', $args + ['--path' => self::DIR]);

        return [$code, Artisan::output()];
    }

    public function test_downloads_each_face_and_prints_the_config(): void
    {
        $this->fakeGoogle('Open Sans', ['400,0', '400,1', '700,0', '700,1']);

        [$code, $out] = $this->install(['family' => 'Open Sans', '--italic' => true]);

        $this->assertSame(0, $code, $out);
        foreach (['Regular', 'Italic', 'Bold', 'BoldItalic'] as $name) {
            $this->assertFileExists(base_path(self::DIR . "/OpenSans/OpenSans-$name.ttf"));
        }
        $this->assertStringContainsString("'Open Sans' => [", $out);
        $this->assertStringContainsString("400 => ['normal' => '" . self::DIR . "/OpenSans/OpenSans-Regular.ttf', 'italic' => '" . self::DIR . "/OpenSans/OpenSans-Italic.ttf'],", $out);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'googleapis') && $r->header('User-Agent') === ['Papercut']);
    }

    public function test_the_printed_config_loads(): void
    {
        $this->fakeGoogle('Brand', ['400,0']);
        $this->install(['family' => 'Brand', '--weights' => '400']);

        config(['papercut.fonts' => ['Brand' => [400 => ['normal' => self::DIR . '/Brand/Brand-Regular.ttf']]]]);
        $faces = FontConfig::faces();

        $this->assertCount(1, $faces);
        $this->assertSame('Brand', $faces[0]['family']);
        (new PhpPdfBackend(false))->loadFont($faces[0]['path']);
    }

    public function test_weights_google_lacks_are_skipped_with_a_warning(): void
    {
        $this->fakeGoogle('Brand', ['400,0']);

        [$code, $out] = $this->install(['family' => 'Brand', '--weights' => '400,950']);

        $this->assertSame(0, $code, $out);
        $this->assertStringContainsString("doesn't offer Brand at 950 normal", $out);
        $this->assertFileDoesNotExist(base_path(self::DIR . '/Brand/Brand-950.ttf'));
    }

    public function test_an_unknown_family_fails(): void
    {
        $this->fakeGoogle('Merriweather', ['400,0']);

        [$code, $out] = $this->install(['family' => 'merriweather']);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('no family called "merriweather"', $out);
        $this->assertDirectoryDoesNotExist(base_path(self::DIR . '/merriweather'));
    }

    public function test_existing_files_are_kept_unless_forced(): void
    {
        $this->fakeGoogle('Brand', ['400,0']);
        $path = base_path(self::DIR . '/Brand/Brand-Regular.ttf');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, 'keep me');

        [, $out] = $this->install(['family' => 'Brand', '--weights' => '400']);
        $this->assertSame('keep me', File::get($path));
        $this->assertStringContainsString('exists', $out);

        $this->install(['family' => 'Brand', '--weights' => '400', '--force' => true]);
        $this->assertSame(file_get_contents(self::INTER), File::get($path));
    }

    public function test_a_file_that_is_not_truetype_is_rejected(): void
    {
        $this->fakeGoogle('Brand', ['400,0'], 'wOF2 not a ttf');

        [$code, $out] = $this->install(['family' => 'Brand', '--weights' => '400']);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('something other than a TrueType file', $out);
        $this->assertFileDoesNotExist(base_path(self::DIR . '/Brand/Brand-Regular.ttf'));
    }

    public function test_a_stylesheet_pointing_off_gstatic_is_ignored(): void
    {
        Http::fake(['fonts.googleapis.com/*' => Http::response("src: url(https://evil.example/x.ttf) format('truetype');")]);

        [$code] = $this->install(['family' => 'Brand', '--weights' => '400']);

        $this->assertSame(1, $code);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'evil.example'));
    }

    public function test_invalid_input_is_rejected(): void
    {
        Http::fake();

        $this->assertSame(1, $this->install(['family' => '../etc'])[0]);
        $this->assertSame(1, $this->install(['family' => 'Brand', '--weights' => '400,bold'])[0]);
        $this->assertSame(1, $this->install(['family' => 'Brand', '--weights' => '0'])[0]);
        Http::assertNothingSent();
    }
}
