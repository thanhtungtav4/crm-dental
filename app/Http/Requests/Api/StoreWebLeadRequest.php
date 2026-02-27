<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWebLeadRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'idempotency_key' => $this->header('X-Idempotency-Key') ?: $this->header('Idempotency-Key'),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'string', 'max:120'],
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'min:8', 'max:20', 'regex:/^[0-9+()\\-\\s.]+$/'],
            'branch_code' => [
                'nullable',
                'string',
                'max:64',
                Rule::exists('branches', 'code')
                    ->where(fn ($query) => $query->where('active', true)->whereNull('deleted_at')),
            ],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'idempotency_key.required' => 'Thiếu X-Idempotency-Key.',
            'full_name.required' => 'Họ tên là bắt buộc.',
            'phone.required' => 'Số điện thoại là bắt buộc.',
            'phone.regex' => 'Số điện thoại không đúng định dạng.',
            'branch_code.exists' => 'Chi nhánh không hợp lệ hoặc không hoạt động.',
        ];
    }
}
