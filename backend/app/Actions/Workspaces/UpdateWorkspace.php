<?php

namespace App\Actions\Workspaces;

use App\Models\Workspace;

class UpdateWorkspace
{
    /** @param array{name?: string, slug?: string, logo?: ?string} $data */
    public function __invoke(Workspace $workspace, array $data): Workspace
    {
        $workspace->update($data);

        return $workspace->refresh();
    }
}
