<?php

namespace Tests\Feature;

use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResetWorkspaceUsageTest extends TestCase
{
    use RefreshDatabase;

    public function test_resets_only_workspaces_past_their_cycle_and_advances_the_date(): void
    {
        $this->travelTo('2026-06-15 10:00:00');
        $due = Workspace::factory()->create(['links_usage' => 40, 'events_usage' => 9000, 'usage_reset_at' => '2026-06-10 00:00:00']);
        $notDue = Workspace::factory()->create(['links_usage' => 5, 'events_usage' => 100, 'usage_reset_at' => '2026-06-20 00:00:00']);
        $longOverdue = Workspace::factory()->create(['links_usage' => 1, 'usage_reset_at' => '2026-01-31 00:00:00']);

        $this->artisan('usage:reset')->expectsOutput('Reset usage for 2 workspace(s).')->assertSuccessful();

        $this->assertSame([0, 0], [$due->refresh()->links_usage, $due->events_usage]);
        $this->assertSame('2026-07-10', $due->usage_reset_at->toDateString());
        $this->assertSame([5, 100], [$notDue->refresh()->links_usage, $notDue->events_usage]);
        $this->assertTrue($longOverdue->refresh()->usage_reset_at->isFuture());
        $this->assertSame('2026-06-30', $longOverdue->usage_reset_at->toDateString()); // Jan 31 → … → Jun 30, no overflow
    }
}
