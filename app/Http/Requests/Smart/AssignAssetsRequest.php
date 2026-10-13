<?php

namespace App\Http\Requests\Smart;

use Illuminate\Foundation\Http\FormRequest;

class AssignAssetsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->isAdmin;
    }

    public function rules(): array
    {
        return [
            'asset_ids' => 'sometimes|array',
            'asset_ids.*' => 'integer|exists:assets,id',
            'unit_ids' => 'sometimes|array',
            'unit_ids.*' => 'integer|exists:assets,id',
        ];
    }

    public function messages(): array
    {
        return [
            'asset_ids.array' => 'Format data alokasi aset tidak valid.',
            'asset_ids.*.exists' => 'Salah satu aset yang dipilih tidak terdaftar di sistem.',
            'unit_ids.array' => 'Format data alokasi aset tidak valid.',
            'unit_ids.*.exists' => 'Salah satu aset yang dipilih tidak terdaftar di sistem.',
        ];
    }
}
