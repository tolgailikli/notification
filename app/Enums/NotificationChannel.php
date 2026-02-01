<?php

namespace App\Enums;

enum NotificationChannel: string
{
    case SMS = 'sms';
    case EMAIL = 'email';
    case PUSH = 'push';

    public function contentMaxLength(): int
    {
        return match ($this) {
            self::SMS => 1600,   // 160 * 10 segments typical
            self::EMAIL => 10000,
            self::PUSH => 256,
        };
    }

    public function contentMinLength(): int
    {
        return 1;
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
