<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PspWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'event_id' => ['required', 'string'],
            'payment_reference' => ['required', 'string'],
            'status' => ['required', 'string', 'in:succeeded,failed'],
            'failure_reason' => ['nullable', 'string'],
        ];
    }
}
