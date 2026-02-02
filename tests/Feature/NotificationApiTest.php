<?php

namespace Tests\Feature;

use App\Enums\NotificationStatus;
use App\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_flow1_create_notification_returns_202_and_persists_to_db(): void
    {
        $response = $this->postJson('/api/notifications', [
            'to' => '+905551234567',
            'channel' => 'sms',
            'content' => 'Hello',
        ]);

        $response->assertStatus(202)
            ->assertJsonStructure(['id', 'status', 'created_at'])
            ->assertJsonPath('status', 'pending')
            ->assertHeader('X-Correlation-ID');

        $this->assertDatabaseHas('notifications', [
            'recipient' => '+905551234567',
            'channel' => 'sms',
            'content' => 'Hello',
            'status' => NotificationStatus::PENDING->value,
        ]);

        $notification = Notification::where('recipient', '+905551234567')->first();
        $this->assertNotNull($notification->uuid);
        $this->assertNotNull($notification->trace_id);
    }

    public function test_flow1_create_notification_response_includes_id_and_iso8601_created_at(): void
    {
        $response = $this->postJson('/api/notifications', [
            'to' => 'user@example.com',
            'channel' => 'email',
            'content' => 'Welcome',
        ]);

        $response->assertStatus(202);
        $data = $response->json();
        $this->assertArrayHasKey('id', $data);
        $this->assertNotEmpty($data['id']);
        $this->assertArrayHasKey('created_at', $data);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $data['created_at']);
    }

    public function test_flow1_create_notification_with_optional_fields(): void
    {
        $response = $this->postJson('/api/notifications', [
            'to' => 'user@example.com',
            'channel' => 'email',
            'content' => 'Welcome',
            'priority' => 'high',
            'idempotency_key' => 'key-123',
        ]);

        $response->assertStatus(202)->assertJsonPath('status', 'pending');

        $this->assertDatabaseHas('notifications', [
            'recipient' => 'user@example.com',
            'channel' => 'email',
            'content' => 'Welcome',
            'priority' => 'high',
            'idempotency_key' => 'key-123',
            'status' => NotificationStatus::PENDING->value,
        ]);
    }

    public function test_flow1_idempotency_key_returns_same_notification_on_duplicate_request(): void
    {
        $key = 'idem-' . uniqid();
        $payload = ['to' => '+905551234567', 'channel' => 'sms', 'content' => 'Hi', 'idempotency_key' => $key];

        $r1 = $this->postJson('/api/notifications', $payload);
        $r2 = $this->postJson('/api/notifications', $payload);

        $r1->assertStatus(202);
        $r2->assertStatus(202);
        $this->assertEquals($r1->json('id'), $r2->json('id'));
        $this->assertDatabaseCount('notifications', 1);
    }

    /** Laravel returns 422 Unprocessable Entity for validation failures (not 400). */
    public function test_flow1_create_validation_requires_to_channel_content(): void
    {
        $response = $this->postJson('/api/notifications', []);
        $response->assertStatus(422)->assertJsonValidationErrors(['to', 'channel', 'content']);
    }

    /** Empty POST (no params) must return 422 even without JSON Content-Type. */
    public function test_flow1_create_validation_empty_post_returns_422(): void
    {
        $response = $this->post('/api/notifications', [], ['Accept' => 'application/json']);
        $response->assertStatus(422)->assertJsonValidationErrors(['to', 'channel', 'content']);
    }

    public function test_flow1_create_validation_rejects_invalid_channel(): void
    {
        $response = $this->postJson('/api/notifications', [
            'to' => '+905551234567',
            'channel' => 'invalid',
            'content' => 'Hi',
        ]);
        $response->assertStatus(422)->assertJsonValidationErrors(['channel']);
    }

    // --- POST /api/notifications/batch ---

    public function test_batch_create_returns_202_and_creates_records_with_same_batch_id(): void
    {
        $items = [
            ['to' => '+905551234567', 'channel' => 'sms', 'content' => 'Hi 1'],
            ['to' => 'user@example.com', 'channel' => 'email', 'content' => 'Hi 2'],
        ];

        $response = $this->postJson('/api/notifications/batch', ['notifications' => $items]);

        $response->assertStatus(202)
            ->assertJsonStructure(['batch_id', 'count', 'notifications'])
            ->assertJsonPath('count', 2);

        $batchId = $response->json('batch_id');
        $this->assertNotNull($batchId);
        $this->assertDatabaseCount('notifications', 2);
        $this->assertEquals(2, Notification::where('batch_id', $batchId)->count());
        foreach ($response->json('notifications') as $n) {
            $this->assertArrayHasKey('id', $n);
            $this->assertArrayHasKey('status', $n);
            $this->assertArrayHasKey('created_at', $n);
        }
    }

    public function test_batch_create_rejects_over_1000(): void
    {
        $items = array_fill(0, 1001, ['to' => '+905551234567', 'channel' => 'sms', 'content' => 'x']);

        $response = $this->postJson('/api/notifications/batch', ['notifications' => $items]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_batch_create_validation_requires_notifications_array(): void
    {
        $response = $this->postJson('/api/notifications/batch', []);
        $response->assertStatus(422)->assertJsonValidationErrors(['notifications']);
    }

    // --- GET /api/notifications (list) ---

    public function test_list_returns_paginated_notifications(): void
    {
        Notification::factory()->count(5)->create();

        $response = $this->getJson('/api/notifications?per_page=2&page=1');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'meta'])
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.current_page', 1);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_list_filter_by_batch_id(): void
    {
        $batchId = \Illuminate\Support\Str::uuid()->toString();
        Notification::factory()->count(2)->create(['batch_id' => $batchId]);
        Notification::factory()->count(1)->create(['batch_id' => null]);

        $response = $this->getJson("/api/notifications?batch_id={$batchId}");

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_list_filter_by_status_and_channel(): void
    {
        Notification::factory()->create(['status' => 'pending', 'channel' => 'sms']);
        Notification::factory()->create(['status' => 'sent', 'channel' => 'sms']);
        Notification::factory()->create(['status' => 'pending', 'channel' => 'email']);

        $response = $this->getJson('/api/notifications?status=pending&channel=sms');
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    // --- GET /api/notifications/{id} ---

    public function test_get_by_id_returns_notification_by_uuid(): void
    {
        $n = Notification::factory()->create([
            'uuid' => $uuid = \Illuminate\Support\Str::uuid()->toString(),
            'recipient' => '+905551234567',
            'channel' => 'sms',
            'content' => 'Hi',
            'status' => NotificationStatus::PENDING->value,
        ]);

        $response = $this->getJson("/api/notifications/{$uuid}");

        $response->assertStatus(200)
            ->assertJsonPath('id', $uuid)
            ->assertJsonPath('recipient', '+905551234567')
            ->assertJsonPath('channel', 'sms')
            ->assertJsonPath('content', 'Hi')
            ->assertJsonPath('status', 'pending');
    }

    public function test_get_by_id_returns_404_when_not_found(): void
    {
        $response = $this->getJson('/api/notifications/non-existent-uuid');
        $response->assertStatus(404)->assertJson(['message' => 'Notification not found']);
    }

    // --- DELETE /api/notifications/{id} (cancel) ---

    public function test_cancel_returns_200_and_updates_status_to_cancelled(): void
    {
        $n = Notification::factory()->create([
            'uuid' => $uuid = \Illuminate\Support\Str::uuid()->toString(),
            'status' => NotificationStatus::PENDING->value,
        ]);

        $response = $this->deleteJson("/api/notifications/{$uuid}");

        $response->assertStatus(200)->assertJsonPath('status', 'cancelled');
        $n->refresh();
        $this->assertEquals(NotificationStatus::CANCELLED->value, $n->status);
    }

    public function test_cancel_returns_404_when_not_found_or_already_sent(): void
    {
        $n = Notification::factory()->create([
            'uuid' => $uuid = \Illuminate\Support\Str::uuid()->toString(),
            'status' => NotificationStatus::SENT->value,
        ]);

        $response = $this->deleteJson("/api/notifications/{$uuid}");
        $response->assertStatus(404);
    }

    public function test_cancel_returns_404_for_nonexistent_id(): void
    {
        $response = $this->deleteJson('/api/notifications/non-existent-uuid');
        $response->assertStatus(404);
    }

    // --- 404 for non-existent route ---

    public function test_nonexistent_route_returns_404_json(): void
    {
        $response = $this->getJson('/api/nonexistent-route');
        $response->assertStatus(404)->assertJson(['message' => 'Not found']);
    }

    // --- Rate limiting: max 100 messages per second per channel ---

    public function test_rate_limit_returns_429_when_channel_exceeds_limit(): void
    {
        config(['notification.rate_limit.max_per_second_per_channel' => 2]);

        // Clear rate limit so previous tests don't leave sms count in cache
        RateLimiter::clear(md5('notification-sms' . 'sms'));

        $payload = ['to' => '+905551234567', 'channel' => 'sms', 'content' => 'Hi'];

        $r1 = $this->postJson('/api/notifications', $payload);
        $r2 = $this->postJson('/api/notifications', $payload);
        $r3 = $this->postJson('/api/notifications', $payload);

        $r1->assertStatus(202);
        $r2->assertStatus(202);
        $r3->assertStatus(429)
            ->assertJsonPath('message', 'Rate limit exceeded. Maximum 2 messages per second per channel.');
    }

    public function test_rate_limit_batch_returns_429_when_channel_exceeds_limit(): void
    {
        config(['notification.rate_limit.max_per_second_per_channel' => 2]);

        $items = [
            ['to' => '+905551234567', 'channel' => 'sms', 'content' => '1'],
            ['to' => '+905551234568', 'channel' => 'sms', 'content' => '2'],
            ['to' => '+905551234569', 'channel' => 'sms', 'content' => '3'],
        ];

        $response = $this->postJson('/api/notifications/batch', ['notifications' => $items]);

        $response->assertStatus(429)
            ->assertJsonPath('message', 'Rate limit exceeded. Maximum 2 messages per second per channel.');
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_rate_limit_batch_under_limit_succeeds(): void
    {
        config(['notification.rate_limit.max_per_second_per_channel' => 10]);

        $items = [
            ['to' => '+905551234567', 'channel' => 'sms', 'content' => '1'],
            ['to' => 'a@b.com', 'channel' => 'email', 'content' => '2'],
        ];

        $response = $this->postJson('/api/notifications/batch', ['notifications' => $items]);
        $response->assertStatus(202)->assertJsonPath('count', 2);
        $this->assertDatabaseCount('notifications', 2);
    }
}
