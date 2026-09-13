<?php

namespace App\Http\Requests;

use App\Services\Geo\Data\Coordinates;
use Illuminate\Foundation\Http\FormRequest;

class NearbyAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ];
    }

    public function coordinates(): Coordinates
    {
        return Coordinates::make($this->validated('lat'), $this->validated('lng'));
    }
}
