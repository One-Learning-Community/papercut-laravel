<?php

declare(strict_types=1);

namespace Quoin\Laravel\Tests\Feature;

use Quoin\Exception\{InvalidStructure, PdfUnreadable};
use Quoin\Laravel\Tests\TestCase;

/** Developer mistakes in Blade raise the library's exceptions, never vendor or PHP ones. */
final class DeveloperMistakesTest extends TestCase
{
    public function test_a_bound_null_style_attribute_renders(): void
    {
        $node = $this->nodeFrom('<x-doc::column :width="$w">x</x-doc::column>', ['w' => null]);

        self::assertIsObject($node);
    }

    public function test_page_template_data_that_is_not_a_pdf_is_pdf_unreadable(): void
    {
        $this->expectException(PdfUnreadable::class);
        $this->nodeFrom('<x-doc::page template-data="garbage">Body</x-doc::page>');
    }

    public function test_page_template_that_is_not_a_pdf_is_pdf_unreadable(): void
    {
        $dir = $this->tempDir('dm');
        file_put_contents($dir . '/hosts.txt', "127.0.0.1 localhost\n");
        config(['quoin.image_path' => $dir]);

        $this->expectException(PdfUnreadable::class);
        $this->nodeFrom('<x-doc::page template="hosts.txt">Body</x-doc::page>');
    }

    public function test_a_document_inside_a_page_is_invalid_structure(): void
    {
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('document');
        $this->nodeFrom('<x-doc::page><x-doc::document><x-doc::page>a</x-doc::page></x-doc::document></x-doc::page>');
    }
}
