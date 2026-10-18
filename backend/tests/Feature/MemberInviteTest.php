<?php

namespace Tests\Feature;

use App\Enums\Plan;
use App\Models\User;
use App\Models\WorkspaceInvite;
use App\Notifications\WorkspaceInvitation;
use App\Support\Hash;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesWorkspaces;
use Tests\TestCase;

class MemberInviteTest extends TestCase
{
    use CreatesWorkspaces, RefreshDatabase;

    public function test_lists_members_with_roles(): void
    {
        [$owner, $workspace] = $this->ownerWithWorkspace();
        $member = $this->memberOf($workspace);

        $this->actingInWorkspace($member, $workspace)->getJson('/api/v1/members')
            ->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonFragment(['id' => $owner->id, 'role' => 'owner'])
            ->assertJsonFragment(['id' => $member->id, 'role' => 'member']);
    }

    public function test_owner_invites_by_email_and_notification_is_sent(): void
    {
        Notification::fake();
        [$owner, $workspace] = $this->ownerWithWorkspace();

        $this->actingInWorkspace($owner, $workspace)->postJson('/api/v1/invites', ['email' => 'New@Example.com'])
            ->assertCreated()->assertJsonPath('data.email', 'new@example.com')->assertJsonPath('data.role', 'member');

        Notification::assertSentOnDemand(WorkspaceInvitation::class);
        $this->assertDatabaseHas('workspace_invites', ['workspace_id' => $workspace->id, 'email' => 'new@example.com']);
    }

    public function test_reinviting_rotates_the_token_instead_of_duplicating(): void
    {
        Notification::fake();
        [$owner, $workspace] = $this->ownerWithWorkspace();

        $this->actingInWorkspace($owner, $workspace)->postJson('/api/v1/invites', ['email' => 'a@example.com'])->assertCreated();
        $hash = WorkspaceInvite::first()->token_hash;
        $this->actingInWorkspace($owner, $workspace)->postJson('/api/v1/invites', ['email' => 'a@example.com'])->assertCreated();

        $this->assertSame(1, WorkspaceInvite::count());
        $this->assertNotSame($hash, WorkspaceInvite::first()->token_hash);
    }

    public function test_cannot_invite_an_existing_member(): void
    {
        [$owner, $workspace] = $this->ownerWithWorkspace();
        $member = $this->memberOf($workspace);

        $this->actingInWorkspace($owner, $workspace)->postJson('/api/v1/invites', ['email' => $member->email])
            ->assertStatus(409)->assertJsonPath('error.code', 'conflict');
    }

    public function test_invites_respect_the_plan_user_limit(): void
    {
        Notification::fake();
        [$owner, $workspace] = $this->ownerWithWorkspace(Plan::Free); // 1 user allowed

        $this->actingInWorkspace($owner, $workspace)->postJson('/api/v1/invites', ['email' => 'a@example.com'])
            ->assertForbidden()->assertJsonPath('error.code', 'exceeded_limit');
    }

    public function test_members_cannot_invite(): void
    {
        [, $workspace] = $this->ownerWithWorkspace();
        $member = $this->memberOf($workspace);

        $this->actingInWorkspace($member, $workspace)->postJson('/api/v1/invites', ['email' => 'a@example.com'])->assertForbidden();
        $this->actingInWorkspace($member, $workspace)->getJson('/api/v1/invites')->assertForbidden();
    }

    private function inviteFor(User $invitee, $workspace, ?string $token = 'tok-123', $expiresAt = null): WorkspaceInvite
    {
        return $workspace->invites()->create([
            'email' => $invitee->email, 'role' => 'member',
            'token_hash' => Hash::secret($token), 'expires_at' => $expiresAt ?? now()->addDay(),
        ]);
    }

    public function test_invitee_accepts_and_joins_with_the_invited_role(): void
    {
        [, $workspace] = $this->ownerWithWorkspace();
        $invitee = User::factory()->create();
        $this->inviteFor($invitee, $workspace);

        $this->actingAs($invitee, 'sanctum')->postJson('/api/v1/invites/accept', ['token' => 'tok-123'])
            ->assertOk()->assertJsonPath('data.id', $workspace->id);

        $this->assertSame('member', $workspace->roleFor($invitee)->value);
        $this->assertSame(0, WorkspaceInvite::count());
    }

    public function test_accept_fails_for_expired_unknown_or_foreign_tokens(): void
    {
        [, $workspace] = $this->ownerWithWorkspace();
        $invitee = User::factory()->create();
        $this->inviteFor($invitee, $workspace, 'expired', now()->subMinute());

        $this->actingAs($invitee, 'sanctum')->postJson('/api/v1/invites/accept', ['token' => 'expired'])
            ->assertStatus(410)->assertJsonPath('error.code', 'invite_expired');
        $this->actingAs($invitee, 'sanctum')->postJson('/api/v1/invites/accept', ['token' => 'nope'])->assertNotFound();

        $this->inviteFor(User::factory()->create(), $workspace, 'someone-elses');
        $this->actingAs($invitee, 'sanctum')->postJson('/api/v1/invites/accept', ['token' => 'someone-elses'])->assertForbidden();
    }

    public function test_owner_revokes_invite_but_not_from_another_workspace(): void
    {
        [$owner, $workspace] = $this->ownerWithWorkspace();
        [, $other] = $this->ownerWithWorkspace();
        $mine = $this->inviteFor(User::factory()->create(), $workspace, 'a');
        $theirs = $this->inviteFor(User::factory()->create(), $other, 'b');

        $this->actingInWorkspace($owner, $workspace)->deleteJson("/api/v1/invites/{$theirs->id}")->assertNotFound();
        $this->actingInWorkspace($owner, $workspace)->deleteJson("/api/v1/invites/{$mine->id}")->assertNoContent();
        $this->assertModelMissing($mine);
        $this->assertModelExists($theirs);
    }

    public function test_owner_removes_member_and_their_api_keys(): void
    {
        [$owner, $workspace] = $this->ownerWithWorkspace();
        $member = $this->memberOf($workspace);
        $workspace->apiKeys()->create(['user_id' => $member->id, 'name' => 'k', 'key_hash' => 'h', 'key_preview' => 'p']);

        $this->actingInWorkspace($owner, $workspace)->deleteJson("/api/v1/members/{$member->id}")->assertNoContent();

        $this->assertNull($workspace->roleFor($member));
        $this->assertSame(0, $workspace->apiKeys()->count());
    }

    public function test_member_can_leave_but_not_remove_others_and_owner_cannot_be_removed(): void
    {
        [$owner, $workspace] = $this->ownerWithWorkspace();
        $a = $this->memberOf($workspace);
        $b = $this->memberOf($workspace);

        $this->actingInWorkspace($a, $workspace)->deleteJson("/api/v1/members/{$b->id}")->assertForbidden();
        $this->actingInWorkspace($owner, $workspace)->deleteJson("/api/v1/members/{$owner->id}")
            ->assertStatus(400)->assertJsonPath('error.code', 'bad_request');
        $this->actingInWorkspace($a, $workspace)->deleteJson("/api/v1/members/{$a->id}")->assertNoContent();
    }
}
