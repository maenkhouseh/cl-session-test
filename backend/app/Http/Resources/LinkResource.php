<?php

namespace App\Http\Resources;

use App\Models\Link;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Link */
class LinkResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'domain' => $this->domain,
            'key' => $this->key,
            'url' => $this->url,
            'shortLink' => $this->short_link,
            'qrCode' => url('/api/v1/qr').'?'.http_build_query(['url' => $this->short_link]),
            'externalId' => $this->external_id,
            'tenantId' => $this->tenant_id,
            'trackConversion' => $this->track_conversion,
            'archived' => $this->archived,
            'expiresAt' => $this->expires_at,
            'expiredUrl' => $this->expired_url,
            'disabledAt' => $this->disabled_at,
            'hasPassword' => $this->password !== null,
            'proxy' => $this->proxy,
            'rewrite' => $this->rewrite,
            'doIndex' => $this->do_index,
            'publicStats' => $this->public_stats,
            'title' => $this->title,
            'description' => $this->description,
            'image' => $this->image,
            'video' => $this->video,
            'ios' => $this->ios,
            'android' => $this->android,
            'geo' => $this->geo,
            'comments' => $this->comments,
            'folderId' => $this->folder_id,
            'tags' => $this->whenLoaded('tags', fn () => $this->tags->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'color' => $t->color])->all()),
            'utm_source' => $this->utm_source,
            'utm_medium' => $this->utm_medium,
            'utm_campaign' => $this->utm_campaign,
            'utm_term' => $this->utm_term,
            'utm_content' => $this->utm_content,
            'ref' => $this->ref,
            'testVariants' => $this->test_variants,
            'testStartedAt' => $this->test_started_at,
            'testCompletedAt' => $this->test_completed_at,
            'clicks' => $this->clicks,
            'leads' => $this->leads,
            'sales' => $this->sales,
            'saleAmount' => $this->sale_amount,
            'lastClicked' => $this->last_clicked_at,
            'userId' => $this->user_id,
            'workspaceId' => $this->workspace_id,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }
}
