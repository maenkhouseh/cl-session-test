<?php

namespace Tests\Unit;

use App\Exceptions\ApiException;
use App\Support\UrlNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UrlNormalizerTest extends TestCase
{
    public static function valid(): array
    {
        return [
            'adds https' => ['example.com', 'https://example.com'],
            'keeps http' => ['http://example.com/a?b=1', 'http://example.com/a?b=1'],
            'trims' => ['  example.com/x  ', 'https://example.com/x'],
            'protocol-relative style' => ['//example.com', 'https://example.com'],
            'subdomain & port' => ['https://a.b.example.com:8080/p', 'https://a.b.example.com:8080/p'],
            'ipv4' => ['http://127.0.0.1:3000', 'http://127.0.0.1:3000'],
        ];
    }

    #[DataProvider('valid')]
    public function test_normalises_valid_urls(string $input, string $expected): void
    {
        $this->assertSame($expected, UrlNormalizer::normalize($input));
    }

    public static function invalid(): array
    {
        return [['ftp://example.com'], ['javascript:alert(1)'], ['https://'], ['http://bad host.com'], ['mailto:a@b.com'], [''], ['data:text/html;base64,AAAA'], ['https://-bad.com']];
    }

    #[DataProvider('invalid')]
    public function test_rejects_invalid_urls(string $input): void
    {
        $this->expectException(ApiException::class);
        UrlNormalizer::normalize($input);
    }

    public function test_host_is_lowercased(): void
    {
        $this->assertSame('example.com', UrlNormalizer::host('https://EXAMPLE.com/Path'));
        $this->assertNull(UrlNormalizer::host('not a url'));
    }
}
