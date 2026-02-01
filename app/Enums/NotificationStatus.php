<?php

namespace App\Enums;

enum NotificationStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case SENT = 'sent';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::SENT, self::FAILED, self::CANCELLED], true);
    }

    public function canCancel(): bool
    {
        return $this === self::PENDING;
    }
}
