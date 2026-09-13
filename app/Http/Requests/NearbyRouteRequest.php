<?php

namespace App\Http\Requests;

use App\Services\Geo\Data\Coordinates;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NearbyRouteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'from_lat' => ['required', 'numeric', 'between:-90,90'],
            'from_lng' => ['required', 'numeric', 'between:-180,180'],
            'to_lat' => ['required', 'numeric', 'between:-90,90'],
            'to_lng' => ['required', 'numeric', 'between:-180,180'],
            'profile' => ['required', 'string', Rule::in(['driving', 'walking'])],
        ];
    }

    public function origin(): Coordinates
    {
        return Coordinates::make($this->validated('from_lat'), $this->validated('from_lng'));
    }

    public function destination(): Coordinates
    {
        return Coordinates::make($this->validated('to_lat'), $this->validated('to_lng'));
    }

    public function profile(): string
    {
        return (string) $this->validated('profile');
    }
}
