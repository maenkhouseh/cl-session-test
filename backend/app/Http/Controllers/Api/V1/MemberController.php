<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Workspaces\AcceptInvite;
use App\Actions\Workspaces\InviteMember;
use App\Actions\Workspaces\RemoveMember;
use App\Enums\WorkspaceRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\AcceptInviteRequest;
use App\Http\Requests\InviteMemberRequest;
use App\Http\Resources\InviteResource;
use App\Http\Resources\MemberResource;
use App\Http\Resources\WorkspaceResource;
use App\Models\User;
use App\Models\WorkspaceInvite;
use App\Support\CurrentWorkspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class MemberController extends Controller
{
    public function __construct(private CurrentWorkspace $current) {}

    public function index(): AnonymousResourceCollection
    {
        return MemberResource::collection($this->current->workspace()->users()->orderBy('users.name')->get());
    }

    public function destroy(User $user, RemoveMember $remove): JsonResponse
    {
        $workspace = $this->current->workspace();
        // Members may leave on their own; only owners remove others.
        if ($user->id !== $this->current->user->id) {
            Gate::authorize('manage', $workspace);
        }

        $member = $workspace->users()->whereKey($user->id)->firstOrFail();
        $remove($workspace, $member);

        return response()->json(null, 204);
    }

    public function invites(): AnonymousResourceCollection
    {
        Gate::authorize('manage', $this->current->workspace());

        return InviteResource::collection($this->current->workspace()->invites()->latest()->get());
    }

    public function invite(InviteMemberRequest $request, InviteMember $invite): JsonResponse
    {
        Gate::authorize('manage', $this->current->workspace());

        [$model] = $invite(
            $this->current->workspace(),
            $request->string('email'),
            WorkspaceRole::from($request->input('role', 'member')),
        );

        return (new InviteResource($model))->response()->setStatusCode(201);
    }

    public function revokeInvite(WorkspaceInvite $invite): JsonResponse
    {
        Gate::authorize('manage', $this->current->workspace());
        abort_unless($invite->workspace_id === $this->current->workspace()->id, 404);
        $invite->delete();

        return response()->json(null, 204);
    }

    /** Authenticated (no workspace context): accept an emailed invitation. */
    public function accept(AcceptInviteRequest $request, AcceptInvite $accept): WorkspaceResource
    {
        return new WorkspaceResource($accept($request->user(), $request->string('token')));
    }
}
