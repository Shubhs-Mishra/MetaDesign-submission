<?php

namespace Tests\Feature;

use App\Models\CheckIn;
use App\Models\Child;
use App\Models\Room;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NurseryCheckInTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;

    private Room $roomA;
    private Room $roomB;

    private Child $childA;
    private Child $childB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create(['name' => 'Nursery A']);
        $this->tenantB = Tenant::create(['name' => 'Nursery B']);

        $this->roomA = Room::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Sunflowers',
        ]);

        $this->roomB = Room::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Little Explorers',
        ]);

        $this->childA = Child::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Child A',
        ]);

        $this->childB = Child::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Child B',
        ]);
    }

    private function tenantHeaders(Tenant $tenant): array
    {
        return [
            'X-Tenant-ID' => (string) $tenant->id,
            'Accept' => 'application/json',
        ];
    }

    public function test_child_can_be_checked_into_a_room(): void
    {
        $response = $this->postJson(
            "/api/rooms/{$this->roomA->id}/check-ins",
            ['child_id' => $this->childA->id],
            $this->tenantHeaders($this->tenantA)
        );

        $response->assertCreated()
            ->assertJsonPath('data.child_id', $this->childA->id)
            ->assertJsonPath('data.room_id', $this->roomA->id)
            ->assertJsonPath('data.tenant_id', $this->tenantA->id)
            ->assertJsonPath('data.checked_out_at', null);

        $this->assertDatabaseHas('check_ins', [
            'tenant_id' => $this->tenantA->id,
            'child_id' => $this->childA->id,
            'room_id' => $this->roomA->id,
            'checked_out_at' => null,
        ]);
    }

    public function test_check_in_requires_a_child_id(): void
    {
        $this->postJson(
            "/api/rooms/{$this->roomA->id}/check-ins",
            [],
            $this->tenantHeaders($this->tenantA)
        )->assertUnprocessable()
            ->assertJsonValidationErrors('child_id');
    }

    public function test_child_from_another_tenant_cannot_be_checked_in(): void
    {
        $this->postJson(
            "/api/rooms/{$this->roomA->id}/check-ins",
            ['child_id' => $this->childB->id],
            $this->tenantHeaders($this->tenantA)
        )->assertUnprocessable();

        $this->assertDatabaseCount('check_ins', 0);
    }

    public function test_room_from_another_tenant_cannot_be_used(): void
    {
        $this->postJson(
            "/api/rooms/{$this->roomB->id}/check-ins",
            ['child_id' => $this->childA->id],
            $this->tenantHeaders($this->tenantA)
        )->assertNotFound();

        $this->assertDatabaseCount('check_ins', 0);
    }

    public function test_child_cannot_have_two_active_check_ins(): void
    {
        CheckIn::create([
            'tenant_id' => $this->tenantA->id,
            'child_id' => $this->childA->id,
            'room_id' => $this->roomA->id,
            'checked_in_at' => now()->subHour(),
        ]);

        $this->postJson(
            "/api/rooms/{$this->roomA->id}/check-ins",
            ['child_id' => $this->childA->id],
            $this->tenantHeaders($this->tenantA)
        )->assertUnprocessable()
            ->assertJsonValidationErrors('child_id');

        $this->assertDatabaseCount('check_ins', 1);
    }

    public function test_child_can_be_checked_out(): void
    {
        $checkIn = CheckIn::create([
            'tenant_id' => $this->tenantA->id,
            'child_id' => $this->childA->id,
            'room_id' => $this->roomA->id,
            'checked_in_at' => now()->subHour(),
        ]);

        $this->postJson(
            "/api/check-ins/{$checkIn->id}/check-out",
            [],
            $this->tenantHeaders($this->tenantA)
        )->assertOk()
            ->assertJsonPath('data.id', $checkIn->id);

        $this->assertDatabaseMissing('check_ins', [
            'id' => $checkIn->id,
            'checked_out_at' => null,
        ]);
    }

    public function test_already_checked_out_child_cannot_be_checked_out_again(): void
    {
        $checkIn = CheckIn::create([
            'tenant_id' => $this->tenantA->id,
            'child_id' => $this->childA->id,
            'room_id' => $this->roomA->id,
            'checked_in_at' => now()->subHours(2),
            'checked_out_at' => now()->subHour(),
        ]);

        $this->postJson(
            "/api/check-ins/{$checkIn->id}/check-out",
            [],
            $this->tenantHeaders($this->tenantA)
        )->assertUnprocessable();
    }

    public function test_tenant_cannot_check_out_another_tenants_check_in(): void
    {
        $checkIn = CheckIn::create([
            'tenant_id' => $this->tenantB->id,
            'child_id' => $this->childB->id,
            'room_id' => $this->roomB->id,
            'checked_in_at' => now()->subHour(),
        ]);

        $this->postJson(
            "/api/check-ins/{$checkIn->id}/check-out",
            [],
            $this->tenantHeaders($this->tenantA)
        )->assertNotFound();

        $this->assertDatabaseHas('check_ins', [
            'id' => $checkIn->id,
            'checked_out_at' => null,
        ]);
    }

    public function test_room_occupants_only_include_children_currently_present(): void
    {
        $activeCheckIn = CheckIn::create([
            'tenant_id' => $this->tenantA->id,
            'child_id' => $this->childA->id,
            'room_id' => $this->roomA->id,
            'checked_in_at' => now()->subHour(),
        ]);

        CheckIn::create([
            'tenant_id' => $this->tenantA->id,
            'child_id' => Child::create([
                'tenant_id' => $this->tenantA->id,
                'name' => 'Child Previously Present',
            ])->id,
            'room_id' => $this->roomA->id,
            'checked_in_at' => now()->subHours(3),
            'checked_out_at' => now()->subHours(2),
        ]);

        CheckIn::create([
            'tenant_id' => $this->tenantB->id,
            'child_id' => $this->childB->id,
            'room_id' => $this->roomB->id,
            'checked_in_at' => now()->subHour(),
        ]);

        $this->getJson(
            "/api/rooms/{$this->roomA->id}/occupants",
            $this->tenantHeaders($this->tenantA)
        )->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->childA->id);
    }

    public function test_tenant_cannot_view_another_tenants_room_occupants(): void
    {
        CheckIn::create([
            'tenant_id' => $this->tenantB->id,
            'child_id' => $this->childB->id,
            'room_id' => $this->roomB->id,
            'checked_in_at' => now(),
        ]);

        $this->getJson(
            "/api/rooms/{$this->roomB->id}/occupants",
            $this->tenantHeaders($this->tenantA)
        )->assertNotFound();
    }

    public function test_request_without_tenant_header_is_rejected(): void
    {
        $this->getJson(
            "/api/rooms/{$this->roomA->id}/occupants"
        )->assertBadRequest();
    }

    public function test_request_with_unknown_tenant_is_rejected(): void
    {
        $this->getJson(
            "/api/rooms/{$this->roomA->id}/occupants",
            ['X-Tenant-ID' => '999999', 'Accept' => 'application/json']
        )->assertNotFound();
    }
}