<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'phone' => ['required', 'string', 'min:8', 'max:25', 'regex:/^[0-9+()\\-\\s.]+$/'],
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

    public function withValidator($validator): void
    {
        $validator->after(function (Validator $validator): void {
            $normalizedPhone = $this->normalizeVietnamPhone((string) $this->input('phone', ''));

            if ($normalizedPhone === '' || preg_match('/^(03|05|07|08|09)\d{8}$/', $normalizedPhone) !== 1) {
                $validator->errors()->add('phone', 'Số điện thoại phải là số di động Việt Nam hợp lệ.');
            }
        });
    }

    protected function failedValidation(ValidatorContract $validator): void
    {
        $errors = $validator->errors()->messages();
        $message = collect($errors)->flatten()->first() ?? 'The given data was invalid.';

        throw new HttpResponseException(response()->json([
            'message' => $message,
            'errors' => $errors,
        ], 422));
    }

    protected function normalizeVietnamPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '84')) {
            $digits = '0'.substr($digits, 2);
        }

        return $digits;
    }
}
