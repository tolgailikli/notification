<?php

namespace App\Http\Requests;

use App\Enums\NotificationChannel;
use App\Enums\NotificationPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $channel = $this->input('channel', 'sms');
        $enum = NotificationChannel::tryFrom($channel) ?? NotificationChannel::SMS;
        $maxLen = $enum->contentMaxLength();

        return [
            'to' => ['required', 'string', 'max:255'],
            'channel' => ['required', 'string', Rule::in(NotificationChannel::values())],
            'content' => ['required', 'string', "max:{$maxLen}", 'min:1'],
            'priority' => ['sometimes', 'string', Rule::in(NotificationPriority::values())],
            'idempotency_key' => ['sometimes', 'string', 'max:128'],
            'scheduled_at' => ['sometimes', 'date', 'after:now'],
        ];
    }

    public function messages(): array
    {
        return [
            'to.required' => 'Recipient (to) is required.',
            'channel.required' => 'Channel is required.',
            'content.required' => 'Content is required.',
        ];
    }
}
