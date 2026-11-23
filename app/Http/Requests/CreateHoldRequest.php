<?php

namespace App\Http\Requests;

use App\Enums\SupplierMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateHoldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fare_id' => ['required', 'integer', 'exists:fares,id'],
            'passenger_name' => ['nullable', 'string', 'max:255'],
            'supplier_mode' => ['nullable', Rule::enum(SupplierMode::class)],
        ];
    }
}
