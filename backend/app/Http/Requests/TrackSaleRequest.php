<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TrackSaleRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'customerExternalId' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'integer', 'min:0', 'max:100000000000'], // cents
            'currency' => ['nullable', 'string', 'size:3', 'alpha'],
            'eventName' => ['nullable', 'string', 'max:255'],
            'paymentProcessor' => ['nullable', 'in:stripe,shopify,paddle,custom'],
            'invoiceId' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array', 'max:50'],
            'timestamp' => ['nullable', 'date', 'before_or_equal:now'],
        ];
    }
}
