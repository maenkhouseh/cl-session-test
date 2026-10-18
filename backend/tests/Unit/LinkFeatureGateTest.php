<?php

namespace Tests\Unit;

use App\Enums\Plan;
use App\Exceptions\ApiException;
use App\Models\Workspace;
use App\Services\LinkFeatureGate;
use Tests\TestCase;

class LinkFeatureGateTest extends TestCase
{
    private function workspace(Plan $plan): Workspace
    {
        return new Workspace(['plan' => $plan]);
    }

    public function test_free_plan_allows_basic_links(): void
    {
        app(LinkFeatureGate::class)->assert($this->workspace(Plan::Free), ['url' => 'https://a.com', 'archived' => false, 'password' => null, 'geo' => []]);
        $this->addToAssertionCount(1);
    }

    public function test_free_plan_rejects_each_paid_option(): void
    {
        foreach (['password' => 'x', 'expires_at' => '2030-01-01', 'geo' => ['US' => 'u'], 'ios' => 'https://i.com', 'rewrite' => true, 'proxy' => true, 'title' => 'T', 'test_variants' => [['url' => 'a']], 'track_conversion' => true] as $attr => $value) {
            try {
                app(LinkFeatureGate::class)->assert($this->workspace(Plan::Free), [$attr => $value]);
                $this->fail("{$attr} should be gated on the free plan");
            } catch (ApiException $e) {
                $this->assertSame(403, $e->status);
            }
        }
    }

    public function test_paid_plans_allow_everything(): void
    {
        app(LinkFeatureGate::class)->assert($this->workspace(Plan::Business), ['password' => 'x', 'geo' => ['US' => 'u'], 'rewrite' => true, 'test_variants' => [['url' => 'a']]]);
        $this->addToAssertionCount(1);
    }
}
