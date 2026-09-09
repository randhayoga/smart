<?php

namespace App\Http\Requests\Smart;

use Illuminate\Foundation\Http\FormRequest;

class AssignLotsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->isAdmin;
    }

    public function rules(): array
    {
        return [
            'lot_allocations' => 'present|array',
            'lot_allocations.*.lot_id' => 'required|integer|exists:lots,id',
            'lot_allocations.*.quantity' => 'required|integer|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'lot_allocations.present' => 'Data alokasi LOT harus disertakan.',
            'lot_allocations.array' => 'Format data alokasi LOT tidak valid.',
            'lot_allocations.*.lot_id.exists' => 'Salah satu LOT yang dipilih tidak terdaftar di sistem.',
            'lot_allocations.*.quantity.min' => 'Jumlah alokasi stok minimal 0.',
        ];
    }
}
