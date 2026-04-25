<?php

namespace App\Http\Requests\Discovery;

use Illuminate\Foundation\Http\FormRequest;

final class IgnoreTrackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'spotify_id' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9]{22}$/'],
        ];
    }
}
