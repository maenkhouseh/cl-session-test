<?php

namespace App\Http\Requests;

use App\Http\Requests\Rules\LinkRules;
use Illuminate\Foundation\Http\FormRequest;

/** Handles both bulk create (`links`) and bulk update (`linkIds` + `data`). */
class BulkLinksRequest extends FormRequest
{
    public function rules(): array
    {
        if ($this->isMethod('POST')) {
            return ['links' => ['required', 'array', 'min:1', 'max:100'], 'links.*' => ['array']];
        }

        $data = collect(LinkRules::update())->except(['url', 'key', 'domain', 'externalId'])
            ->mapWithKeys(fn ($rules, $key) => ["data.{$key}" => $rules])->all();

        return [
            'linkIds' => ['required', 'array', 'min:1', 'max:100'],
            'linkIds.*' => ['string', 'distinct'],
            'data' => ['required', 'array'],
            ...$data,
        ];
    }
}
