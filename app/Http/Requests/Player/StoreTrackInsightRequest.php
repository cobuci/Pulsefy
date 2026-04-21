<?php

namespace App\Http\Requests\Player;

use Illuminate\Foundation\Http\FormRequest;

class StoreTrackInsightRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'track_id' => ['required', 'string'],
            'track_name' => ['required', 'string'],
            'artist_name' => ['required', 'string'],
            'album_name' => ['nullable', 'string'],
        ];
    }
}
