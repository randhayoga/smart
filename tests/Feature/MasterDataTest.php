<?php

namespace Tests\Feature;

use App\Models\Master\Floor;
use App\Models\Master\Location;
use App\Models\Master\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_store_floor(): void
    {
        $user = User::factory()->create();
        $location = Location::factory()->create();

        $response = $this->actingAs($user)->post(route('smart.master.floors.store'), [
            'location_id' => $location->id,
            'name' => 'Lantai 1',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('floors', [
            'location_id' => $location->id,
            'name' => 'Lantai 1',
        ]);
    }

    public function test_can_update_floor(): void
    {
        $user = User::factory()->create();
        $floor = Floor::factory()->create();

        $response = $this->actingAs($user)->put(route('smart.master.floors.update', $floor), [
            'name' => 'Lantai Baru',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('floors', [
            'id' => $floor->id,
            'name' => 'Lantai Baru',
        ]);
    }

    public function test_can_destroy_floor(): void
    {
        $user = User::factory()->create();
        $floor = Floor::factory()->create();

        $response = $this->actingAs($user)->delete(route('smart.master.floors.destroy', $floor));

        $response->assertRedirect();
        $this->assertDatabaseMissing('floors', [
            'id' => $floor->id,
        ]);
    }

    public function test_cannot_destroy_floor_if_it_has_rooms(): void
    {
        $user = User::factory()->create();
        $floor = Floor::factory()->create();
        Room::factory()->create(['floor_id' => $floor->id]);

        $response = $this->actingAs($user)->delete(route('smart.master.floors.destroy', $floor));

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Lantai tidak dapat dihapus karena masih memiliki ruangan.');
        $this->assertDatabaseHas('floors', [
            'id' => $floor->id,
        ]);
    }

    public function test_can_store_room(): void
    {
        $user = User::factory()->create();
        $floor = Floor::factory()->create();

        $response = $this->actingAs($user)->post(route('smart.master.rooms.store'), [
            'floor_id' => $floor->id,
            'name' => 'Ruang Server',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('rooms', [
            'floor_id' => $floor->id,
            'name' => 'Ruang Server',
        ]);
    }

    public function test_can_update_room(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create();
        $newFloor = Floor::factory()->create();

        $response = $this->actingAs($user)->put(route('smart.master.rooms.update', $room), [
            'floor_id' => $newFloor->id,
            'name' => 'Ruang Meeting',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('rooms', [
            'id' => $room->id,
            'floor_id' => $newFloor->id,
            'name' => 'Ruang Meeting',
        ]);
    }

    public function test_can_destroy_room(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create();

        $response = $this->actingAs($user)->delete(route('smart.master.rooms.destroy', $room));

        $response->assertRedirect();
        $this->assertDatabaseMissing('rooms', [
            'id' => $room->id,
        ]);
    }
}
