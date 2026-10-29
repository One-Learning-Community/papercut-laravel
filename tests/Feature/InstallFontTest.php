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
    private string $dir = '';

    protected function setUp(): void
    {
        parent::setUp();
        // per process, so parallel workers don't share a directory
        $this->dir = 'storage/framework/testing/papercut-fonts-' . getmypid();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(base_path($this->dir));
        parent::tearDown();
    }

    /**
     * Fakes Google: each listed "weight,ital" pair has a TrueType file, and $licences maps paths under the
     * google/fonts repository (such as "ofl/opensans/OFL.txt") to their text; everything else answers 400.
     *
     * @param list<string> $faces
     * @param array<string, string> $licences
     */
    private function fakeGoogle(string $family, array $faces, ?string $bytes = null, array $licences = []): void
    {
        $bytes ??= (string) file_get_contents(self::INTER);
        Http::fake(function (Request $request) use ($family, $faces, $bytes, $licences) {
            $url = $request->url();
            foreach ($licences as $path => $text) {
                if ($url === 'https://raw.githubusercontent.com/google/fonts/main/' . $path) {
                    return Http::response($text);
                }
            }
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
        $code = Artisan::call('papercut:fonts:install', $args + ['--path' => $this->dir]);

        return [$code, Artisan::output()];
    }

    public function test_downloads_each_face_and_prints_the_config(): void
    {
        $this->fakeGoogle('Open Sans', ['400,0', '400,1', '700,0', '700,1']);

        [$code, $out] = $this->install(['family' => 'Open Sans', '--italic' => true]);

        $this->assertSame(0, $code, $out);
        foreach (['Regular', 'Italic', 'Bold', 'BoldItalic'] as $name) {
            $this->assertFileExists(base_path($this->dir . "/OpenSans/OpenSans-$name.ttf"));
        }
        $this->assertStringContainsString("'Open Sans' => [", $out);
        $this->assertStringContainsString("400 => ['normal' => '" . $this->dir . "/OpenSans/OpenSans-Regular.ttf', 'italic' => '" . $this->dir . "/OpenSans/OpenSans-Italic.ttf'],", $out);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'googleapis') && $r->header('User-Agent') === ['Papercut']);
    }

    public function test_saves_the_licence_beside_the_fonts(): void
    {
        $this->fakeGoogle('Open Sans', ['400,0'], licences: ['ofl/opensans/OFL.txt' => "SIL OPEN FONT LICENSE Version 1.1\n"]);

        [$code, $out] = $this->install(['family' => 'Open Sans', '--weights' => '400']);

        $this->assertSame(0, $code, $out);
        $this->assertSame("SIL OPEN FONT LICENSE Version 1.1\n", file_get_contents(base_path($this->dir . '/OpenSans/OFL.txt')));
        $this->assertStringContainsString('Keep ' . $this->dir . '/OpenSans/OFL.txt with the fonts', $out);
    }

    public function test_finds_an_apache_licence_when_there_is_no_ofl(): void
    {
        $this->fakeGoogle('Roboto Slab', ['400,0'], licences: ['ofl/robotoslab/OFL.txt' => '<html>not found</html>', 'apache/robotoslab/LICENSE.txt' => "Apache License\n"]);

        [$code, $out] = $this->install(['family' => 'Roboto Slab', '--weights' => '400']);

        $this->assertSame(0, $code, $out);
        $this->assertFileDoesNotExist(base_path($this->dir . '/RobotoSlab/OFL.txt'));
        $this->assertSame("Apache License\n", file_get_contents(base_path($this->dir . '/RobotoSlab/LICENSE.txt')));
    }

    public function test_a_missing_licence_warns_but_installs(): void
    {
        $this->fakeGoogle('Brand', ['400,0']);

        [$code, $out] = $this->install(['family' => 'Brand', '--weights' => '400']);

        $this->assertSame(0, $code, $out);
        $this->assertFileExists(base_path($this->dir . '/Brand/Brand-Regular.ttf'));
        $this->assertStringContainsString("Couldn't download Brand's licence", $out);
    }

    public function test_an_existing_licence_is_kept_unless_forced(): void
    {
        $this->fakeGoogle('Brand', ['400,0'], licences: ['ofl/brand/OFL.txt' => "new\n"]);
        File::ensureDirectoryExists(base_path($this->dir . '/Brand'));
        file_put_contents(base_path($this->dir . '/Brand/OFL.txt'), "old\n");

        $this->install(['family' => 'Brand', '--weights' => '400']);
        $this->assertSame("old\n", file_get_contents(base_path($this->dir . '/Brand/OFL.txt')));

        $this->install(['family' => 'Brand', '--weights' => '400', '--force' => true]);
        $this->assertSame("new\n", file_get_contents(base_path($this->dir . '/Brand/OFL.txt')));
    }

    public function test_the_printed_config_loads(): void
    {
        $this->fakeGoogle('Brand', ['400,0']);
        $this->install(['family' => 'Brand', '--weights' => '400']);

        config(['papercut.fonts' => ['Brand' => [400 => ['normal' => $this->dir . '/Brand/Brand-Regular.ttf']]]]);
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
        $this->assertFileDoesNotExist(base_path($this->dir . '/Brand/Brand-950.ttf'));
    }

    public function test_an_unknown_family_fails(): void
    {
        $this->fakeGoogle('Merriweather', ['400,0']);

        [$code, $out] = $this->install(['family' => 'merriweather']);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('no family called "merriweather"', $out);
        $this->assertDirectoryDoesNotExist(base_path($this->dir . '/merriweather'));
    }

    public function test_existing_files_are_kept_unless_forced(): void
    {
        $this->fakeGoogle('Brand', ['400,0']);
        $path = base_path($this->dir . '/Brand/Brand-Regular.ttf');
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
        $this->assertFileDoesNotExist(base_path($this->dir . '/Brand/Brand-Regular.ttf'));
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
