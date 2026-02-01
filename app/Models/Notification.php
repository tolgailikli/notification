<?php

namespace App\Models;

use App\Enums\NotificationChannel;
use App\Enums\NotificationPriority;
use App\Enums\NotificationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'batch_id',
        'recipient',
        'channel',
        'content',
        'priority',
        'status',
        'idempotency_key',
        'scheduled_at',
        'sent_at',
        'provider_message_id',
        'retry_count',
        'trace_id'
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Notification $notification) {
            if (empty($notification->uuid)) {
                $notification->uuid = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    public function getChannelEnum(): NotificationChannel
    {
        return NotificationChannel::from($this->channel);
    }

    public function getPriorityEnum(): NotificationPriority
    {
        return NotificationPriority::from($this->priority);
    }

    public function getStatusEnum(): NotificationStatus
    {
        return NotificationStatus::from($this->status);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', NotificationStatus::PENDING);
    }

    public function scopeByBatch(Builder $query, string $batchId): Builder
    {
        return $query->where('batch_id', $batchId);
    }

    public function scopeForChannel(Builder $query, string $channel): Builder
    {
        return $query->where('channel', $channel);
    }

    public function scopeInStatus(Builder $query, array|string $status): Builder
    {
        $status = is_array($status) ? $status : [$status];
        return $query->whereIn('status', $status);
    }

    public function isPending(): bool
    {
        return $this->status === NotificationStatus::PENDING->value;
    }
}
