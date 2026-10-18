<?php

namespace App\Actions\Auth;

use App\Actions\Workspaces\CreateWorkspace;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RegisterUser
{
    public function __construct(private CreateWorkspace $createWorkspace) {}

    public function __invoke(string $name, string $email, string $password, ?string $workspaceName = null): User
    {
        return DB::transaction(function () use ($name, $email, $password, $workspaceName) {
            $user = User::create(['name' => $name, 'email' => $email, 'password' => $password]);
            ($this->createWorkspace)($user, $workspaceName ?: "{$name}'s Workspace");

            return $user;
        });
    }
}
