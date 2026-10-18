<?php

namespace Tests\Unit;

use App\Models\Workspace;
use App\Support\UniqueSlug;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UniqueSlugTest extends TestCase
{
    use RefreshDatabase;

    public function test_slugifies_and_increments_on_collision(): void
    {
        $this->assertSame('hello-world', UniqueSlug::for(Workspace::class, 'Hello World!'));

        Workspace::factory()->create(['slug' => 'hello-world']);
        Workspace::factory()->create(['slug' => 'hello-world-2']);

        $this->assertSame('hello-world-3', UniqueSlug::for(Workspace::class, 'Hello World'));
    }

    public function test_falls_back_for_unslugifiable_input(): void
    {
        $this->assertSame('workspace', UniqueSlug::for(Workspace::class, '!!!'));
    }
}
