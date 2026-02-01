<?php

namespace Database\Factories;

use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use App\Models\Notification;
use Illuminate\Database\Eloquent\Factories\Factory;

class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    public function definition(): array
    {
        $channel = fake()->randomElement(NotificationChannel::cases());

        return [
            'uuid' => fake()->uuid(),
            'recipient' => $channel === NotificationChannel::EMAIL ? fake()->email() : '+90555' . fake()->numerify('######'),
            'channel' => $channel->value,
            'content' => fake()->sentence(),
            'priority' => 'normal',
            'status' => NotificationStatus::PENDING->value,
            'retry_count' => 0,
        ];
    }

    public function sent(): static
    {
        return $this->state(fn (array $attrs) => [
            'status' => NotificationStatus::SENT->value,
            'sent_at' => now(),
        ]);
    }
}
