<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveUtmTemplateRequest extends FormRequest
{
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:190'],
            ...collect(['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'ref'])
                ->mapWithKeys(fn ($k) => [$k => ['sometimes', 'nullable', 'string', 'max:190']])->all(),
        ];
    }
}
