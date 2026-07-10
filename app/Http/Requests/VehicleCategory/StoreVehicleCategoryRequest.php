<?php

namespace App\Http\Requests\VehicleCategory;

use Illuminate\Foundation\Http\FormRequest;

class StoreVehicleCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->isSuperAdmin();
    }

    public function rules(): array
    {
        return [
            'vehicle_category_name' => 'required|string|max:100|unique:vehicle_categories,vehicle_category_name',
        ];
    }
}
