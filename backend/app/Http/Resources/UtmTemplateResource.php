<?php

namespace App\Http\Resources;

use App\Models\UtmTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin UtmTemplate */
class UtmTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'name' => $this->name,
            'utm_source' => $this->utm_source, 'utm_medium' => $this->utm_medium, 'utm_campaign' => $this->utm_campaign,
            'utm_term' => $this->utm_term, 'utm_content' => $this->utm_content, 'ref' => $this->ref,
            'createdAt' => $this->created_at, 'updatedAt' => $this->updated_at,
        ];
    }
}
