<?php

namespace App\Http\Requests\VehicleCategory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVehicleCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->isSuperAdmin();
    }

    public function rules(): array
    {
        return [
            'vehicle_category_name' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('vehicle_categories', 'vehicle_category_name')->ignore($this->route('vehicle_category')),
            ],
        ];
    }
}
