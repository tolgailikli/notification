<?php

namespace App\Enums;

enum NotificationPriority: string
{
    case HIGH = 'high';
    case NORMAL = 'normal';
    case LOW = 'low';

    public function queueName(): string
    {
        return 'notifications-' . $this->value;
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
