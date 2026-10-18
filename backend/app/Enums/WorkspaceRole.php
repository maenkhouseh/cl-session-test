<?php

namespace App\Enums;

enum WorkspaceRole: string
{
    case Owner = 'owner';
    case Member = 'member';

    public function canManageWorkspace(): bool
    {
        return $this === self::Owner;
    }
}
