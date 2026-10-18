<?php

namespace App\Http\Resources;

use App\Models\Folder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Folder */
class FolderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'name' => $this->name, 'description' => $this->description,
            'accessLevel' => $this->access_level, 'linkCount' => $this->whenCounted('links'),
            'createdAt' => $this->created_at, 'updatedAt' => $this->updated_at,
        ];
    }
}
