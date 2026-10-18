<?php

namespace App\Http\Requests;

use App\Support\CurrentWorkspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkspaceRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:100'],
            'slug' => ['sometimes', 'string', 'max:48', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('workspaces', 'slug')->ignore(app(CurrentWorkspace::class)->workspace()->id)],
            'logo' => ['sometimes', 'nullable', 'url', 'max:2048'],
        ];
    }
}
