<?php

namespace App\Http\Requests\Court;

use Illuminate\Foundation\Http\FormRequest;

class StoreCourtRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Role is already enforced by the `role:admin` middleware on the route group.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:255'],
            'location'    => ['required', 'string', 'max:255'],
            'sport_type'  => ['required', 'string', 'max:100'],
            'hourly_rate' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'is_active'   => ['sometimes', 'boolean'],
        ];
    }
}
