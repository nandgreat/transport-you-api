<?php

namespace App\Http\Requests\State;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->isSuperAdmin();
    }

    public function rules(): array
    {
        $stateId = $this->route('state');

        return [
            'name' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('states', 'name')->ignore($stateId),
            ],
            'code' => [
                'sometimes',
                'string',
                'max:10',
                Rule::unique('states', 'code')->ignore($stateId),
            ],
        ];
    }
}
