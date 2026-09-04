<?php

namespace App\Http\Requests\Smart;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmFulfillmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->isAdmin;
    }

    public function rules(): array
    {
        return [
            'allow_partial' => 'nullable|boolean',
            'note' => 'nullable|string|max:1000',
        ];
    }
}
