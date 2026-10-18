<?php

namespace App\Http\Requests;

use App\Data\AnalyticsFilters;
use App\Services\Analytics\AnalyticsQuery;
use DateTimeZone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Shared by analytics, events, export and the public share dashboard. */
class AnalyticsRequest extends FormRequest
{
    public function rules(): array
    {
        $dimensions = collect(AnalyticsFilters::DIMENSIONS)->keys()->mapWithKeys(fn ($k) => [$k => ['sometimes', 'string', 'max:1000']])->all();

        return [
            'event' => ['sometimes', Rule::in(['clicks', 'leads', 'sales'])],
            'groupBy' => ['sometimes', Rule::in(AnalyticsQuery::GROUP_BYS)],
            'interval' => ['sometimes', Rule::in(['1h', '24h', '7d', '30d', '90d', '1y', 'mtd', 'qtd', 'ytd', 'all'])],
            'start' => ['sometimes', 'date'],
            'end' => ['sometimes', 'date', 'after_or_equal:start'],
            'timezone' => ['sometimes', Rule::in(DateTimeZone::listIdentifiers())],
            'linkId' => ['sometimes', 'string'],
            'domain' => ['string', 'max:255', 'required_with:key'],
            'key' => ['string', 'max:190', 'required_with:domain'],
            'tagIds' => ['sometimes', 'array', 'max:50'],
            'tagIds.*' => ['string'],
            'folderId' => ['sometimes', 'string'],
            'trigger' => ['sometimes', Rule::in(['qr', 'link'])],
            'unique' => ['sometimes', 'boolean'],
            'cursor' => ['sometimes', 'string', 'max:255'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:200'],
            ...$dimensions,
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('unique')) {
            $this->merge(['unique' => filter_var($this->query('unique'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)]);
        }
        if (is_string($this->query('tagIds'))) {
            $this->merge(['tagIds' => array_filter(explode(',', $this->query('tagIds')))]);
        }
    }
}
