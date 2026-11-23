<?php

namespace Tests\Feature;

class HoldCreationTest extends FeatureTestCase
{
    public function test_creates_a_hold_and_decrements_the_seat_count(): void
    {
        [$partner, $key] = $this->partnerWithKey();
        $fare = $this->createFare(['seats_left' => 5]);

        $response = $this->postJson('/api/v1/holds', [
            'fare_id' => $fare->id,
            'passenger_name' => 'Ali Raza',
        ], $this->authHeaders($key));

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'hold');
        $response->assertJsonPath('data.pricing.total_paisa', $fare->totalPaisa());
        $this->assertNotEmpty($response->json('customer_token'));

        $this->assertSame(4, $fare->fresh()->seats_left);
    }

    public function test_two_holds_racing_for_the_last_seat_only_one_wins(): void
    {
        [$partner, $key] = $this->partnerWithKey();
        $fare = $this->createFare(['seats_left' => 1]);

        $first = $this->postJson('/api/v1/holds', ['fare_id' => $fare->id], $this->authHeaders($key));
        $second = $this->postJson('/api/v1/holds', ['fare_id' => $fare->id], $this->authHeaders($key));

        $first->assertCreated();
        $second->assertStatus(409);
        $second->assertJsonPath('error.code', 'seat_unavailable');

        $this->assertSame(0, $fare->fresh()->seats_left);
        $this->assertSame(1, $fare->fresh()->bookings()->count());
    }

    public function test_fare_id_must_exist(): void
    {
        [$partner, $key] = $this->partnerWithKey();

        $response = $this->postJson('/api/v1/holds', ['fare_id' => 999999], $this->authHeaders($key));

        $response->assertStatus(422);
    }

    public function test_requires_authentication(): void
    {
        $fare = $this->createFare();

        $response = $this->postJson('/api/v1/holds', ['fare_id' => $fare->id]);

        $response->assertStatus(401);
    }

    public function test_requires_the_holds_write_scope(): void
    {
        [$partner, $key] = $this->partnerWithKey(['bookings.read']);
        $fare = $this->createFare();

        $response = $this->postJson('/api/v1/holds', ['fare_id' => $fare->id], $this->authHeaders($key));

        $response->assertStatus(403);
        $response->assertJsonPath('error.code', 'forbidden');
    }
}
