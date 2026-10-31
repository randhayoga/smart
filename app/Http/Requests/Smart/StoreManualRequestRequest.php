<?php

namespace App\Http\Requests\Smart;

use App\Models\HrdOrgchart;
use App\Models\TbProject;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request validating manual borrowing (ST), assignment (LT), and consumable requests.
 */
class StoreManualRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $requestType = $this->input('request_type');

        $rules = [
            'request_type' => ['required', 'string', 'in:consumable,st,lt,rent'],
            'user_id' => [
                'required',
                'integer',
                function ($attribute, $value, $fail) {
                    if (!User::query()->toBase()->where('id', $value)->where('active', 1)->exists()) {
                        $fail('Pemohon yang dipilih tidak valid atau sudah tidak aktif.');
                    }
                },
            ],
            'utilization' => ['required', 'string', 'in:corporate,project'],
            'org_id' => [
                'nullable',
                'required_if:utilization,corporate',
                'integer',
                function ($attribute, $value, $fail) {
                    if ($this->input('utilization') === 'corporate' && $value) {
                        if (!HrdOrgchart::query()->toBase()->where('id', $value)->exists()) {
                            $fail('Departemen yang dipilih tidak valid.');
                        }
                    }
                },
            ],
            'project_id' => [
                'nullable',
                'required_if:utilization,project',
                'integer',
                function ($attribute, $value, $fail) {
                    if ($this->input('utilization') === 'project' && $value) {
                        if (!TbProject::validForSelection()->toBase()->where('id_project', $value)->exists()) {
                            $fail('Project yang dipilih tidak valid atau sudah ditutup.');
                        }
                    }
                },
            ],
            'approver_id' => [
                'required',
                'integer',
                function ($attribute, $value, $fail) {
                    if (!User::query()->toBase()->where('id', $value)->where('active', 1)->exists()) {
                        $fail('DM/PM yang dipilih tidak valid atau sudah tidak aktif.');
                    }
                },
            ],
            'form_file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:2048'],
            'reasoning' => ['nullable', 'string', 'max:2000'],
            'date' => ['nullable', 'required_if:request_type,consumable', 'date'],
            'start_date' => ['nullable', 'required_if:request_type,st,lt,rent', 'date'],
            'end_date' => ['nullable', 'required_if:request_type,st,rent', 'date', 'after_or_equal:start_date'],
            'items' => ['required', 'array', 'min:1'],
        ];

        if ($requestType === 'consumable') {
            $rules['items.*.model_id'] = ['required', 'integer', 'distinct', 'exists:models,id'];
            $rules['items.*.quantity'] = ['required', 'integer', 'min:1'];
            $rules['items.*.reasoning'] = ['nullable', 'string', 'max:1000'];
        } else {
            $rules['items.*.asset_id'] = ['required', 'integer', 'distinct', 'exists:assets,id'];
            $rules['items.*.reasoning'] = ['nullable', 'string', 'max:1000'];
        }

        return $rules;
    }
}
