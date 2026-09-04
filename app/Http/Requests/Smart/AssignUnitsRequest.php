<?php

namespace App\Http\Requests\Smart;

use Illuminate\Foundation\Http\FormRequest;

class AssignUnitsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->isAdmin;
    }

    public function rules(): array
    {
        return [
            'unit_ids' => 'required|array',
            'unit_ids.*' => 'integer|exists:units,id',
        ];
    }

    public function messages(): array
    {
        return [
            'unit_ids.required' => 'Pilih setidaknya satu unit aset.',
            'unit_ids.array' => 'Format data alokasi unit tidak valid.',
            'unit_ids.*.exists' => 'Salah satu unit aset yang dipilih tidak terdaftar di sistem.',
        ];
    }
}
