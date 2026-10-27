<?php

declare(strict_types=1);

namespace Papercut\Laravel\Tests\Feature;

use Papercut\Laravel\Facades\Papercut;
use Papercut\Laravel\Tests\TestCase;

final class BookmarksTest extends TestCase
{
    /** @return array<int, mixed> each top-level entry as [title => children], decoded from qpdf's JSON */
    private function outline(string $blade): array
    {
        $view = 'bookmarks-' . md5($blade);
        $path = sys_get_temp_dir() . "/$view.blade.php";
        file_put_contents($path, $blade);
        \Illuminate\Support\Facades\View::addLocation(sys_get_temp_dir());
        try {
            $pdf = Papercut::view($view)->pdf()->toString();
        } finally {
            unlink($path);
        }
        $file = tempnam(sys_get_temp_dir(), 'pc') . '.pdf'; // qpdf's AppArmor profile wants .pdf
        rename(substr($file, 0, -4), $file);
        file_put_contents($file, $pdf);
        exec(sprintf('qpdf --json --json-key=outlines %s 2>&1', escapeshellarg($file)), $out);
        unlink($file);
        $walk = static function (array $items) use (&$walk): array {
            return array_map(static fn (array $e): array => [$e['title'] => $walk($e['kids'])], $items);
        };

        return $walk(json_decode(implode("\n", $out), true, flags: JSON_THROW_ON_ERROR)['outlines']);
    }

    public function test_headings_and_bookmark_attributes_build_the_outline(): void
    {
        $outline = $this->outline(<<<'BLADE'
            <x-doc::document>
              <x-doc::h1>Report</x-doc::h1>
              <x-doc::h2 bookmark="Short">A much longer heading</x-doc::h2>
              <x-doc::h2 :bookmark="false">Not listed</x-doc::h2>
              <x-doc::card title="Terms" bookmark="Terms" bookmark-level="2">Text</x-doc::card>
              <x-doc::section number="3" title="Search terms" bookmark="3. Search terms" bookmark-level="2">Body</x-doc::section>
            </x-doc::document>
            BLADE);

        $this->assertSame([['Report' => [['Short' => []], ['Terms' => []], ['3. Search terms' => []]]]], $outline);
    }

    public function test_the_config_turns_bookmarks_off(): void
    {
        config(['papercut.bookmarks' => false]);

        $this->assertSame([], $this->outline('<x-doc::document><x-doc::h1>Report</x-doc::h1></x-doc::document>'));
    }

    public function test_an_invalid_config_value_is_rejected(): void
    {
        config(['papercut.bookmarks' => 'yes']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid papercut.bookmarks');
        Papercut::pdfOptions();
    }
}
