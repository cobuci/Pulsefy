<?php

namespace App\Http\Requests\Library;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateFolderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'parent_id' => ['nullable', 'integer', 'exists:library_folders,id'],
        ];
    }
}
