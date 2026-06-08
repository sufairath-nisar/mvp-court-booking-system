<?php

namespace App\Http\Requests\Court;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCourtRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
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
            'name'        => ['sometimes', 'required', 'string', 'max:255'],
            'location'    => ['sometimes', 'required', 'string', 'max:255'],
            'sport_type'  => ['sometimes', 'required', 'string', 'max:100'],
            'hourly_rate' => ['sometimes', 'required', 'numeric', 'min:0', 'max:999999.99'],
            'is_active'   => ['sometimes', 'boolean'],
        ];
    }
}
