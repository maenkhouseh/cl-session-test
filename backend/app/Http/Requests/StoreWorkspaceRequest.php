<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkspaceRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:48', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:workspaces,slug'],
        ];
    }
}
