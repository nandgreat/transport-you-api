<?php

namespace App\Http\Requests\State;

use Illuminate\Foundation\Http\FormRequest;

class StoreStateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->isSuperAdmin();
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:states,name',
            'code' => 'required|string|max:10|unique:states,code',
        ];
    }
}
