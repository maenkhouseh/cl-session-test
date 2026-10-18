<?php

namespace Tests\Unit;

use App\Models\Link;
use App\Services\KeyGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class KeyGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_keys_of_the_requested_length_and_prefix(): void
    {
        $key = app(KeyGenerator::class)->generate('shortie.link', 9, 'p-');

        $this->assertSame(11, strlen($key));
        $this->assertStringStartsWith('p-', $key);
        $this->assertMatchesRegularExpression('/^p-[A-Za-z0-9]{9}$/', $key);
    }

    public function test_generated_keys_are_unique(): void
    {
        $generator = app(KeyGenerator::class);
        $keys = collect(range(1, 50))->map(fn () => $generator->generate('shortie.link'));

        $this->assertCount(50, $keys->unique());
    }

    public function test_avoids_taken_keys_case_insensitively(): void
    {
        // Length 1: occupy 18 of the 36 case-insensitive keys, so ~42% of candidates are free.
        $taken = str_split('abcdefghijklmnopqr');
        foreach ($taken as $char) {
            Link::factory()->create(['key' => $char]);
        }

        $generator = app(KeyGenerator::class);
        foreach (range(1, 20) as $_) {
            $this->assertNotContains(strtolower($generator->generate('shortie.link', 1)), $taken);
        }
    }

    public function test_gives_up_with_a_conflict_when_no_key_is_free(): void
    {
        foreach (str_split('abcdefghijklmnopqrstuvwxyz0123456789') as $char) {
            Link::factory()->create(['key' => $char]);
        }

        $this->expectExceptionMessage('Could not generate a unique short link key');
        app(KeyGenerator::class)->generate('shortie.link', 1);
    }

    public static function keys(): array
    {
        return [['abc', true], ['A1', true], ['a-b_c', true], ['a/b', true], ['-a', false], ['a b', false], ['', false], ['é', false], [str_repeat('a', 191), false]];
    }

    #[DataProvider('keys')]
    public function test_key_syntax(string $key, bool $valid): void
    {
        $this->assertSame($valid, app(KeyGenerator::class)->isValid($key));
    }

    public function test_reserved_keys_are_case_insensitive(): void
    {
        $generator = app(KeyGenerator::class);

        $this->assertTrue($generator->isReserved('API'));
        $this->assertTrue($generator->isReserved('stats'));
        $this->assertFalse($generator->isReserved('apiary'));
    }
}
