<?php

namespace App\Http\Requests\Smart;

use Illuminate\Foundation\Http\FormRequest;

class SubmitConsumableCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'items'        => 'required|array|min:1',
            'items.*.id'   => 'required|integer',
            'pemanfaatan'  => 'required|string|in:corporate,project',
            'departemen'   => 'required_if:pemanfaatan,corporate|nullable|exists:hrd_orgcharts,id',
            'project'      => 'required_if:pemanfaatan,project|nullable|exists:tb_projects,id',
            'alasan'       => 'required|string|max:2000',
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Barang yang dipilih wajib ada.',
            'items.min' => 'Pilih minimal satu barang.',
            'pemanfaatan.required' => 'Pemanfaatan wajib dipilih.',
            'departemen.required_if' => 'Departemen wajib dipilih untuk pemanfaatan corporate.',
            'departemen.exists' => 'Departemen yang dipilih tidak valid.',
            'project.required_if' => 'Project wajib dipilih untuk pemanfaatan project.',
            'project.exists' => 'Project yang dipilih tidak valid.',
            'alasan.required' => 'Alasan permintaan wajib diisi.',
            'alasan.max' => 'Alasan permintaan maksimal 2000 karakter.',
        ];
    }
}
