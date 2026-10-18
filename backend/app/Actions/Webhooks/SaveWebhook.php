<?php

namespace App\Actions\Webhooks;

use App\Exceptions\ApiException;
use App\Models\Webhook;
use App\Models\Workspace;
use App\Services\WebhookDispatcher;
use App\Support\SafeUrl;
use Illuminate\Support\Str;

class SaveWebhook
{
    public function __construct(private WebhookDispatcher $dispatcher) {}

    /** @param array{name?: string, url?: string, triggers?: array<int, string>} $data  @return array{0: Webhook, 1: ?string} webhook and the plain secret (creation only) */
    public function __invoke(Workspace $workspace, array $data, ?Webhook $webhook = null): array
    {
        if (! $workspace->plan->allows('webhooks')) {
            throw ApiException::forbidden('Webhooks are not available on your current plan. Upgrade to use them.');
        }

        if (isset($data['url']) && ! SafeUrl::isSafeWebhookTarget($data['url'])) {
            throw ApiException::unprocessable('The webhook URL must be a public https URL.');
        }

        $secret = null;

        if (! $webhook) {
            if ($workspace->webhooks()->count() >= Webhook::MAX_PER_WORKSPACE) {
                throw ApiException::limitExceeded('You can create up to '.Webhook::MAX_PER_WORKSPACE.' webhooks per workspace.');
            }

            $webhook = $workspace->webhooks()->create([...$data, 'secret' => $secret = 'whsec_'.Str::random(32)]);
        } else {
            // Re-enabling is implicit when the owner edits a webhook that was auto-disabled.
            $webhook->update([...$data, 'disabled_at' => null, 'consecutive_failures' => 0]);
        }

        $this->dispatcher->forget($workspace->id);

        return [$webhook, $secret];
    }
}
