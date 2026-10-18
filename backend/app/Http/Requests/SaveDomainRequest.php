<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveDomainRequest extends FormRequest
{
    public function rules(): array
    {
        $create = $this->isMethod('POST');

        return [
            'slug' => $create
                ? ['required', 'string', 'max:253', 'regex:/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9\-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i']
                : ['prohibited'],
            'primary' => ['sometimes', 'boolean'],
            'archived' => ['sometimes', 'boolean'],
            'placeholder' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'notFoundUrl' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'expiredUrl' => ['sometimes', 'nullable', 'string', 'max:2048'],
        ];
    }
}
