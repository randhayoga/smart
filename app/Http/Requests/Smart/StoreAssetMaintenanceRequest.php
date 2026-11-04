<?php

namespace App\Http\Requests\Smart;

use Illuminate\Foundation\Http\FormRequest;

class StoreAssetMaintenanceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('inventory.maintenance') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'source' => 'required|string|in:SMART,TiSu',
            'reference' => 'nullable|required_if:source,TiSu|string|max:255',
            'type' => 'required|string|in:Preventive,Corrective',
            'user_id' => 'required|integer',
            'date' => 'required|date',
            'cost' => 'required|numeric|min:0|max:999999999999',
            'problem' => 'required|string',
            'solution' => 'required|string',
            'condition' => 'required|string|in:Tidak Berubah,Bagus,Rusak',
            'proof_file' => 'nullable|file|mimes:jpeg,jpg,png,webp,pdf|max:2048',
        ];
    }

    /**
     * Custom validation messages in Indonesian.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'source.required' => 'Sumber perawatan wajib dipilih.',
            'source.in' => 'Sumber perawatan harus SMART atau TiSu.',
            'reference.required_if' => 'Nomor referensi wajib diisi jika sumber adalah TiSu.',
            'reference.max' => 'Nomor referensi maksimal 255 karakter.',
            'type.required' => 'Tipe perawatan wajib dipilih.',
            'type.in' => 'Tipe perawatan harus Preventive atau Corrective.',
            'user_id.required' => 'User wajib dipilih.',
            'date.required' => 'Tanggal perawatan wajib diisi.',
            'date.date' => 'Format tanggal perawatan tidak valid.',
            'cost.required' => 'Biaya perawatan wajib diisi.',
            'cost.numeric' => 'Biaya perawatan harus berupa angka.',
            'cost.min' => 'Biaya perawatan minimal 0.',
            'problem.required' => 'Permasalahan wajib diisi.',
            'solution.required' => 'Solusi wajib diisi.',
            'condition.required' => 'Kondisi setelah perawatan wajib dipilih.',
            'condition.in' => 'Kondisi setelah perawatan tidak valid.',
            'proof_file.file' => 'Bukti perawatan harus berupa file.',
            'proof_file.mimes' => 'Format bukti perawatan salah! Hanya diperbolehkan file .pdf, .jpg, .jpeg, .png, atau .webp.',
            'proof_file.max' => 'Ukuran file bukti perawatan maksimal 2MB.',
        ];
    }
}
