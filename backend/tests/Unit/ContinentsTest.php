<?php

namespace Tests\Unit;

use App\Support\Continents;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ContinentsTest extends TestCase
{
    public static function countries(): array
    {
        return [['US', 'NA'], ['de', 'EU'], ['JP', 'AS'], ['BR', 'SA'], ['NG', 'AF'], ['AU', 'OC'], ['AQ', 'AN'], ['ZZ', null]];
    }

    #[DataProvider('countries')]
    public function test_maps_countries_to_continents(string $country, ?string $continent): void
    {
        $this->assertSame($continent, Continents::for($country));
    }

    public function test_null_country_has_no_continent(): void
    {
        $this->assertNull(Continents::for(null));
    }
}
