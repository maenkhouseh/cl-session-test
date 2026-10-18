<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LinkFilterRequest extends FormRequest
{
    public const SORTS = ['createdAt', 'clicks', 'saleAmount', 'lastClicked'];

    public function rules(): array
    {
        return [
            'domain' => ['sometimes', 'string', 'max:255'],
            'search' => ['sometimes', 'string', 'max:255'],
            'tagIds' => ['sometimes', 'array', 'max:50'],
            'tagIds.*' => ['string'],
            'tagNames' => ['sometimes', 'array', 'max:50'],
            'tagNames.*' => ['string'],
            'folderId' => ['sometimes', 'string'],
            'userId' => ['sometimes', 'integer'],
            'tenantId' => ['sometimes', 'string', 'max:255'],
            'externalId' => ['sometimes', 'string', 'max:255'],
            'showArchived' => ['sometimes', 'boolean'],
            'groupBy' => ['sometimes', Rule::in(['domain', 'folderId', 'userId', 'tagId'])],
            'sortBy' => ['sometimes', Rule::in(self::SORTS)],
            'sortOrder' => ['sometimes', Rule::in(['asc', 'desc'])],
            'page' => ['sometimes', 'integer', 'min:1', 'max:10000'],
            'pageSize' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // Query strings carry booleans as the text "true"/"false".
        if ($this->has('showArchived')) {
            $this->merge(['showArchived' => filter_var($this->query('showArchived'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)]);
        }

        // Accept `tagIds=a,b` as well as `tagIds[]=a&tagIds[]=b`.
        foreach (['tagIds', 'tagNames'] as $key) {
            if (is_string($this->query($key))) {
                $this->merge([$key => array_filter(explode(',', $this->query($key)))]);
            }
        }
    }
}
