<?php

namespace Tests\Unit;

use App\Enums\Plan;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PlanTest extends TestCase
{
    public static function linkLimits(): array
    {
        return [['free', 25], ['business', 10_000], ['advanced', 50_000], ['enterprise', null]];
    }

    #[DataProvider('linkLimits')]
    public function test_monthly_link_quota_per_plan(string $plan, ?int $expected): void
    {
        $this->assertSame($expected, Plan::from($plan)->limit('links_per_month'));
    }

    public function test_feature_gating(): void
    {
        $this->assertFalse(Plan::Free->allows('ab_testing'));
        $this->assertTrue(Plan::Business->allows('ab_testing'));
        $this->assertTrue(Plan::Enterprise->allows('password'));
    }

    public function test_limits_exclude_the_feature_list(): void
    {
        $this->assertArrayNotHasKey('features', Plan::Business->limits());
        $this->assertSame(1_200, Plan::Business->limits()['rate_limit']);
    }
}
