<?php

namespace App\Http\Requests;

use App\Http\Requests\Rules\LinkRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreLinkRequest extends FormRequest
{
    public function rules(): array
    {
        return LinkRules::store();
    }
}
