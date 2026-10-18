<?php

namespace Tests\Feature;

use App\Models\Link;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesWorkspaces;
use Tests\Concerns\SeedsEvents;
use Tests\TestCase;

class SharedDashboardTest extends TestCase
{
    use CreatesWorkspaces, RefreshDatabase, SeedsEvents;

    private $user;

    private $workspace;

    private Link $link;

    protected function setUp(): void
    {
        parent::setUp();
        [$this->user, $this->workspace] = $this->ownerWithWorkspace();
        $this->link = Link::factory()->create(['workspace_id' => $this->workspace->id, 'key' => 'shared']);
    }

    private function owner()
    {
        return $this->actingInWorkspace($this->user, $this->workspace);
    }

    private function share(array $body = []): string
    {
        return $this->owner()->putJson("/api/v1/links/{$this->link->id}/share", $body)->json('data.id');
    }

    public function test_owner_enables_updates_and_removes_sharing(): void
    {
        $this->owner()->getJson("/api/v1/links/{$this->link->id}/share")->assertOk()->assertJsonPath('data', null);

        $created = $this->owner()->putJson("/api/v1/links/{$this->link->id}/share", [])->assertCreated()->assertJsonPath('data.passwordProtected', false);
        $this->assertStringStartsWith('dash_', $created->json('data.id'));

        $this->owner()->putJson("/api/v1/links/{$this->link->id}/share", ['password' => 'secret'])
            ->assertOk()->assertJsonPath('data.passwordProtected', true)->assertJsonPath('data.id', $created->json('data.id'));
        $this->owner()->putJson("/api/v1/links/{$this->link->id}/share", ['password' => null])->assertJsonPath('data.passwordProtected', false);

        $this->owner()->deleteJson("/api/v1/links/{$this->link->id}/share")->assertNoContent();
        $this->owner()->getJson("/api/v1/links/{$this->link->id}/share")->assertJsonPath('data', null);
    }

    public function test_cannot_share_another_workspaces_link(): void
    {
        $foreign = Link::factory()->create();

        $this->owner()->putJson("/api/v1/links/{$foreign->id}/share", [])->assertNotFound();
    }

    public function test_public_dashboard_serves_analytics_without_authentication(): void
    {
        $this->seedClick($this->link, ['country' => 'DE'], 3);
        $this->seedClick($this->link, ['country' => 'US']);
        $token = $this->share();

        $this->getJson("/api/v1/public/dashboards/{$token}")->assertOk()->assertJsonPath('data.link.key', 'shared')->assertJsonPath('data.locked', false);
        $this->getJson("/api/v1/public/dashboards/{$token}/analytics?groupBy=count")->assertOk()->assertJsonPath('clicks', 4);
        $this->getJson("/api/v1/public/dashboards/{$token}/analytics?groupBy=country")->assertJsonPath('0.country', 'DE');
    }

    public function test_public_analytics_are_locked_to_the_shared_link(): void
    {
        $other = Link::factory()->create(['workspace_id' => $this->workspace->id]);
        $this->seedClick($this->link, [], 2);
        $this->seedClick($other, [], 9);
        $token = $this->share();

        // Trying to widen the scope via filters must not leak the workspace's other links.
        $this->getJson("/api/v1/public/dashboards/{$token}/analytics?groupBy=count&linkId={$other->id}")->assertJsonPath('clicks', 2);
        $this->getJson("/api/v1/public/dashboards/{$token}/analytics?groupBy=count&domain={$other->domain}&key={$other->key}")->assertJsonPath('clicks', 2);
        $this->getJson("/api/v1/public/dashboards/{$token}/analytics?groupBy=top_links")->assertJsonCount(1)->assertJsonPath('0.link.key', 'shared');
    }

    public function test_password_protected_dashboards_require_the_header(): void
    {
        $this->seedClick($this->link, [], 2);
        $token = $this->share(['password' => 'secret']);

        $this->getJson("/api/v1/public/dashboards/{$token}")->assertOk()->assertJsonPath('data.locked', true)->assertJsonPath('data.link', null);
        $this->getJson("/api/v1/public/dashboards/{$token}/analytics")->assertUnauthorized()->assertJsonPath('error.code', 'unauthorized');
        $this->withHeader('X-Dashboard-Password', 'wrong')->getJson("/api/v1/public/dashboards/{$token}/analytics")->assertUnauthorized();

        $this->withHeader('X-Dashboard-Password', 'secret')->getJson("/api/v1/public/dashboards/{$token}/analytics?groupBy=count")->assertOk()->assertJsonPath('clicks', 2);
        $this->withHeader('X-Dashboard-Password', 'secret')->getJson("/api/v1/public/dashboards/{$token}")->assertJsonPath('data.locked', false)->assertJsonPath('data.link.key', 'shared');
    }

    public function test_unknown_or_malformed_tokens_are_404(): void
    {
        foreach (['dash_doesnotexist', 'nope', 'dash_', $this->link->id] as $token) {
            $this->getJson("/api/v1/public/dashboards/{$token}")->assertNotFound()->assertJsonPath('error.code', 'not_found');
        }
    }

    public function test_unsharing_revokes_access_immediately(): void
    {
        $token = $this->share();
        $this->owner()->deleteJson("/api/v1/links/{$this->link->id}/share")->assertNoContent();

        $this->getJson("/api/v1/public/dashboards/{$token}/analytics")->assertNotFound();
    }
}
