<?php

declare(strict_types=1);

namespace Papercut\Laravel\Tests\Feature;

use Papercut\Laravel\Tests\TestCase;
use Papercut\{Image, Link, QrCode};

/**
 * Blade escapes bound values on a component's attribute bag; tags must see what was bound.
 */
final class BoundAttributesTest extends TestCase
{
    private const string URL = 'https://acme.test/pay?invoice=142&name="Ann O\'Neil"<>';

    public function test_bound_image_bytes_arrive_unchanged(): void
    {
        // A 1x1 PNG: its header and compressed data are not valid UTF-8, which escaping would replace.
        $png = (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        $n = $this->nodeFrom('<x-doc::image :data="$png" />', ['png' => $png]);

        self::assertInstanceOf(Image::class, $n);
        self::assertSame(['kind' => 'bytes', 'value' => $png], $n->source());
    }

    public function test_a_bound_qr_value_is_encoded_as_given(): void
    {
        $n = $this->nodeFrom('<x-doc::qr-code :value="$url" />', ['url' => self::URL]);

        self::assertInstanceOf(QrCode::class, $n);
        self::assertSame(self::URL, $n->value());
    }

    public function test_an_echo_in_an_attribute_is_unescaped_too(): void
    {
        $n = $this->nodeFrom('<x-doc::qr-code value="{{ $url }}" />', ['url' => self::URL]);

        self::assertInstanceOf(QrCode::class, $n);
        self::assertSame(self::URL, $n->value());
    }

    public function test_a_literal_ampersand_is_kept(): void
    {
        $n = $this->nodeFrom('<x-doc::qr-code value="https://acme.test/?a=1&b=2" />');

        self::assertInstanceOf(QrCode::class, $n);
        self::assertSame('https://acme.test/?a=1&b=2', $n->value());
    }

    public function test_a_bound_link_keeps_its_query_string(): void
    {
        $n = $this->nodeFrom('<x-doc::link :href="$u">Pay</x-doc::link>', ['u' => 'https://acme.test/?a=1&b=2']);

        self::assertInstanceOf(Link::class, $n);
        self::assertSame('https://acme.test/?a=1&b=2', $n->uri());
    }
}
