<?php

namespace App\Http\Requests\Smart;

use App\Models\HrdOrgchart;
use App\Models\TbProject;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form Request validating manual consumable stock deduction / request submissions.
 */
class ConsumableManualRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', Rule::exists(User::class, 'id')],
            'request_date' => ['required', 'date'],
            'utilization' => ['required', 'string', Rule::in(['corporate', 'project'])],
            'org_id' => ['required_if:utilization,corporate', 'nullable', Rule::exists(HrdOrgchart::class, 'id')],
            'project_id' => ['required_if:utilization,project', 'nullable', Rule::exists(TbProject::class, 'id_project')],
            'quantity' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'Pemohon wajib dipilih.',
            'user_id.exists' => 'Pemohon tidak ditemukan.',
            'request_date.required' => 'Tanggal permintaan wajib diisi.',
            'request_date.date' => 'Format tanggal permintaan tidak valid.',
            'utilization.required' => 'Pemanfaatan wajib dipilih.',
            'utilization.in' => 'Pemanfaatan tidak valid.',
            'org_id.required_if' => 'Departemen wajib dipilih untuk pemanfaatan Corporate.',
            'org_id.exists' => 'Departemen yang dipilih tidak valid.',
            'project_id.required_if' => 'Project wajib dipilih untuk pemanfaatan Project.',
            'project_id.exists' => 'Project yang dipilih tidak valid.',
            'quantity.required' => 'Jumlah permintaan wajib diisi.',
            'quantity.integer' => 'Jumlah permintaan harus berupa bilangan bulat.',
            'quantity.min' => 'Jumlah permintaan minimal 1.',
            'note.max' => 'Catatan / alasan permintaan maksimal 2000 karakter.',
        ];
    }
}
