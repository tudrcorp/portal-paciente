<?php

namespace App\Http\Requests;

use App\Services\Geo\Data\Coordinates;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NearbyPlacesRequest extends FormRequest
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
            'radius' => ['required', 'integer', 'min:500', 'max:'.(int) config('geolocation.max_radius', 25000)],
            'categories' => ['required', 'array', 'min:1', 'max:8'],
            'categories.*' => ['string', Rule::in(array_keys((array) config('geolocation.categories', [])))],
        ];
    }

    public function messages(): array
    {
        return [
            'lat.required' => 'Falta la ubicación del dispositivo.',
            'lng.required' => 'Falta la ubicación del dispositivo.',
            'categories.required' => 'Selecciona al menos un tipo de centro de salud.',
        ];
    }

    public function center(): Coordinates
    {
        return Coordinates::make($this->validated('lat'), $this->validated('lng'));
    }

    public function radius(): int
    {
        return (int) $this->validated('radius');
    }

    /**
     * @return array<int, string>
     */
    public function categories(): array
    {
        return array_values(array_unique((array) $this->validated('categories')));
    }
}
