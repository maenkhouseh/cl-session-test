<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TrackLeadRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'clickId' => ['required', 'string', 'max:32'],
            'eventName' => ['required', 'string', 'max:255'],
            'customerExternalId' => ['required', 'string', 'max:255'],
            'customerName' => ['nullable', 'string', 'max:255'],
            'customerEmail' => ['nullable', 'email:rfc', 'max:255'],
            'customerAvatar' => ['nullable', 'url', 'max:2048'],
            'metadata' => ['nullable', 'array', 'max:50'],
            'timestamp' => ['nullable', 'date', 'before_or_equal:now'],
        ];
    }
}
