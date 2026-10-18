<?php

namespace Tests\Feature;

use App\Models\Link;
use App\Models\Workspace;
use App\Services\ClickBuffer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OperatorCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_set_plan_changes_the_plan_and_validates_input(): void
    {
        $workspace = Workspace::factory()->create(['slug' => 'acme']);

        $this->artisan('workspace:set-plan', ['slug' => 'acme', 'plan' => 'advanced'])->expectsOutputToContain('advanced plan')->assertSuccessful();
        $this->assertSame('advanced', $workspace->refresh()->plan->value);

        $this->artisan('workspace:set-plan', ['slug' => 'acme', 'plan' => 'platinum'])->assertFailed();
        $this->artisan('workspace:set-plan', ['slug' => 'ghost', 'plan' => 'free'])->expectsOutputToContain('not found')->assertFailed();
        $this->assertSame('advanced', $workspace->refresh()->plan->value);
    }

    public function test_clicks_flush_drains_the_whole_buffer_in_batches(): void
    {
        $buffer = app(ClickBuffer::class);
        $buffer->clear();
        $link = Link::factory()->create();
        foreach (range(1, 7) as $i) {
            $buffer->push(['occurred_at' => now()->utc()->format('Y-m-d H:i:s.u'), 'workspace_id' => $link->workspace_id, 'link_id' => $link->id, 'click_id' => "c{$i}", 'is_unique' => true, 'url' => 'https://a.com']);
        }

        $this->artisan('clicks:flush', ['--batch' => 3])->expectsOutput('Flushed 7 click event(s).')->assertSuccessful();

        $this->assertSame(7, DB::table('click_events')->count());
        $this->assertSame(0, $buffer->size());
        $buffer->clear();
    }
}
