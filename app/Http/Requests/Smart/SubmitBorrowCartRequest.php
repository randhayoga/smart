<?php

namespace App\Http\Requests\Smart;

class SubmitBorrowCartRequest extends SubmitConsumableCartRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'start_date' => 'required|date|after_or_equal:today',
            'end_date'   => 'nullable|date|after_or_equal:start_date',
        ]);
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'start_date.required' => 'Tanggal mulai peminjaman wajib diisi.',
            'start_date.date' => 'Format tanggal mulai peminjaman tidak valid.',
            'start_date.after_or_equal' => 'Tanggal mulai peminjaman tidak boleh di masa lalu.',
            'end_date.date' => 'Format tanggal selesai peminjaman tidak valid.',
            'end_date.after_or_equal' => 'Tanggal selesai peminjaman harus sama dengan atau setelah tanggal mulai peminjaman.',
            'alasan.required' => 'Alasan peminjaman wajib diisi.',
            'alasan.max' => 'Alasan peminjaman maksimal 2000 karakter.',
        ]);
    }
}
