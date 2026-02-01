<?php

namespace Tests\Feature;

use App\Enums\NotificationStatus;
use App\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
