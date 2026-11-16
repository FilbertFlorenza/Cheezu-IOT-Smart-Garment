<?php

use App\Enums\Role;
use App\Models\Device;
use App\Models\Inspection;

test('administrators can browse, search, and filter devices', function () {
    Device::factory()->create(['device_code' => 'DEVICE-001', 'location' => 'Line A']);
    Device::factory()->inactive()->create(['device_code' => 'DEVICE-002', 'location' => 'Line B']);

    $admin = staffUser(Role::Administrator);

    $this->actingAs($admin)->get('/devices')
        ->assertOk()
        ->assertSee('DEVICE-001')
        ->assertSee('DEVICE-002');

    $this->actingAs($admin)->get('/devices?search=Line%20B')
        ->assertOk()
        ->assertSee('DEVICE-002')
        ->assertDontSee('DEVICE-001');

    $this->actingAs($admin)->get('/devices?status=INACTIVE')
        ->assertOk()
        ->assertSee('DEVICE-002')
        ->assertDontSee('DEVICE-001');
});

test('an administrator can register a device', function () {
    $admin = staffUser(Role::Administrator);

    $this->actingAs($admin)->get('/devices/create')->assertOk();

    $this->actingAs($admin)
        ->post('/devices', [
            'device_code' => 'device-010',
            'device_name' => 'Finishing Camera',
            'location' => 'Finishing',
            'status' => 'ACTIVE',
            'description' => 'Mounted at the finishing table.',
        ])
        ->assertRedirect();

    $device = Device::query()->where('device_code', 'DEVICE-010')->firstOrFail();

    expect($device->device_name)->toBe('Finishing Camera')
        ->and($device->status->value)->toBe('ACTIVE');
});

test('device codes must be unique and follow the format', function () {
    Device::factory()->create(['device_code' => 'DEVICE-001']);
    $admin = staffUser(Role::Administrator);

    $this->actingAs($admin)
        ->post('/devices', [
            'device_code' => 'DEVICE-001',
            'device_name' => 'Duplicate',
            'status' => 'ACTIVE',
        ])
        ->assertSessionHasErrors('device_code');

    $this->actingAs($admin)
        ->post('/devices', [
            'device_code' => 'bad code!',
            'device_name' => 'Invalid',
            'status' => 'ACTIVE',
        ])
        ->assertSessionHasErrors('device_code');
});

test('an administrator can update and deactivate a device', function () {
    $device = Device::factory()->create(['device_code' => 'DEVICE-001']);
    $admin = staffUser(Role::Administrator);

    $this->actingAs($admin)->get('/devices/'.$device->id.'/edit')->assertOk();

    $this->actingAs($admin)
        ->put('/devices/'.$device->id, [
            'device_code' => 'DEVICE-001',
            'device_name' => 'Renamed camera',
            'location' => 'Line C',
            'status' => 'MAINTENANCE',
        ])
        ->assertRedirect(route('devices.show', $device));

    expect($device->fresh()->device_name)->toBe('Renamed camera');

    $this->actingAs($admin)
        ->patch('/devices/'.$device->id.'/deactivate')
        ->assertRedirect();

    expect($device->fresh()->status->value)->toBe('INACTIVE');

    $this->actingAs($admin)
        ->patch('/devices/'.$device->id.'/activate')
        ->assertRedirect();

    expect($device->fresh()->status->value)->toBe('ACTIVE');
});

test('device detail shows inspection totals', function () {
    $device = Device::factory()->create();
    Inspection::factory()->passed()->create(['device_id' => $device->id]);
    Inspection::factory()->defective(1)->create(['device_id' => $device->id]);

    $this->actingAs(staffUser(Role::Operator))
        ->get('/devices/'.$device->id)
        ->assertOk()
        ->assertSee('Recent inspections');
});

test('operators can view but not manage devices', function () {
    $device = Device::factory()->create();
    $operator = staffUser(Role::Operator);

    $this->actingAs($operator)->get('/devices')->assertOk();
    $this->actingAs($operator)->get('/devices/create')->assertForbidden();
    $this->actingAs($operator)->post('/devices', [])->assertForbidden();
    $this->actingAs($operator)
        ->patch('/devices/'.$device->id.'/deactivate')
        ->assertForbidden();
});
