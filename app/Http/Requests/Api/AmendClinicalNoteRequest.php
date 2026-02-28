<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class AmendClinicalNoteRequest extends FormRequest
{
    public function prepareForValidation(): void
    {
        $this->merge([
            'idempotency_key' => $this->header('X-Idempotency-Key') ?: $this->header('Idempotency-Key'),
        ]);
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
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
            'expected_version' => ['required', 'integer', 'min:1'],
            'examining_doctor_id' => ['nullable', 'integer', 'exists:users,id'],
            'treating_doctor_id' => ['nullable', 'integer', 'exists:users,id'],
            'general_exam_notes' => ['nullable', 'string'],
            'treatment_plan_note' => ['nullable', 'string'],
            'other_diagnosis' => ['nullable', 'string'],
            'indications' => ['nullable', 'array'],
            'indications.*' => ['string', 'max:120'],
            'indication_images' => ['nullable', 'array'],
            'tooth_diagnosis_data' => ['nullable', 'array'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $mutableFields = [
                'examining_doctor_id',
                'treating_doctor_id',
                'general_exam_notes',
                'treatment_plan_note',
                'other_diagnosis',
                'indications',
                'indication_images',
                'tooth_diagnosis_data',
            ];

            $hasAnyMutationField = collect($mutableFields)
                ->contains(fn (string $field): bool => $this->has($field));

            if (! $hasAnyMutationField) {
                $validator->errors()->add('payload', 'Cần truyền ít nhất một trường cập nhật lâm sàng.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'idempotency_key.required' => 'Thiếu X-Idempotency-Key.',
            'expected_version.required' => 'Thiếu expected_version để optimistic lock.',
            'expected_version.min' => 'expected_version phải lớn hơn hoặc bằng 1.',
        ];
    }
}
