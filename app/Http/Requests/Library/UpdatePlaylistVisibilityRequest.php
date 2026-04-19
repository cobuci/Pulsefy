<?php

namespace App\Http\Requests\Library;

use Illuminate\Foundation\Http\FormRequest;

final class UpdatePlaylistVisibilityRequest extends FormRequest
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
            'hidden' => ['required', 'boolean'],
        ];
    }
}
