<?php

namespace App\Http\Requests;

use App\Models\Webhook;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveWebhookRequest extends FormRequest
{
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:100'],
            'url' => [$required, 'url:https,http', 'max:2048'],
            'triggers' => [$required, 'array', 'min:1'],
            'triggers.*' => ['string', Rule::in(Webhook::TRIGGERS), 'distinct'],
        ];
    }
}
