<?php

declare(strict_types=1);

namespace Papercut\Laravel\Tests\Feature;

use Papercut\{Document, Page, Text};
use Papercut\Export\Encryption;
use Papercut\Laravel\Facades\Papercut;
use Papercut\Laravel\PendingDocument;
use Papercut\Laravel\Tests\TestCase;

final class EncryptTest extends TestCase
{
    private static function info(string $pdf, string $password = ''): string
    {
        $file = tempnam(sys_get_temp_dir(), 'pc') . '.pdf'; // qpdf's AppArmor profile wants .pdf
        rename(substr($file, 0, -4), $file);
        file_put_contents($file, $pdf);
        exec(sprintf('qpdf --show-encryption --password=%s %s 2>&1 < /dev/null', escapeshellarg($password), escapeshellarg($file)), $out);
        unlink($file);

        return implode("\n", $out);
    }

    private function document(): PendingDocument
    {
        return Papercut::make(Document::make(Page::a4()->body(Text::make('Private'))));
    }

    public function test_encrypt_with_a_password(): void
    {
        $pdf = $this->document()->encrypt('open-sesame')->pdf()->toString();

        $this->assertStringContainsString('Supplied password is user password', self::info($pdf, 'open-sesame'));
        $this->assertStringContainsString('Incorrect password supplied', self::info($pdf, 'wrong'));
    }

    public function test_encrypt_with_custom_permissions(): void
    {
        $pdf = $this->document()->encrypt(new Encryption(copy: false))->pdf()->toString();

        $this->assertStringContainsString('extract for any purpose: not allowed', self::info($pdf));
    }

    public function test_encrypt_returns_a_copy_and_leaves_the_original_unencrypted(): void
    {
        $plain = $this->document();
        $locked = $plain->encrypt('pw');

        $this->assertNotSame($plain, $locked);
        $this->assertStringContainsString('File is not encrypted', self::info($plain->pdf()->toString()));
    }

    public function test_html_is_not_encrypted(): void
    {
        $html = $this->document()->encrypt('pw')->html()->page()->toString();

        $this->assertStringContainsString('Private', $html);
    }
}
