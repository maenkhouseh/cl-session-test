<?php

namespace App\Http\Requests;

use App\Models\Folder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveFolderRequest extends FormRequest
{
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:190'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'accessLevel' => ['sometimes', Rule::in(Folder::ACCESS_LEVELS)],
        ];
    }
}
