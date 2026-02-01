<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateBatchNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'notifications' => ['required', 'array', 'max:1000'],
            'notifications.*.to' => ['required', 'string', 'max:255'],
            'notifications.*.channel' => ['required', 'string', 'in:sms,email,push'],
            'notifications.*.content' => ['required', 'string', 'max:10000', 'min:1'],
            'notifications.*.priority' => ['sometimes', 'string', 'in:high,normal,low'],
            'notifications.*.idempotency_key' => ['sometimes', 'string', 'max:128'],
            'notifications.*.scheduled_at' => ['sometimes', 'date', 'after:now'],
        ];
    }
}
