<?php

namespace Tests\Unit;

use App\Support\CaseConverter;
use PHPUnit\Framework\TestCase;

class CaseConverterTest extends TestCase
{
    public function test_converts_top_level_keys_only(): void
    {
        $this->assertSame(
            ['external_id' => 1, 'utm_source' => 2, 'test_variants' => [['percentageValue' => 50]]],
            CaseConverter::toSnake(['externalId' => 1, 'utm_source' => 2, 'testVariants' => [['percentageValue' => 50]]]),
        );
    }
}
