<?php

use App\Enums\Role;

test('settings show runtime information and integration endpoints', function () {
    $this->actingAs(staffUser(Role::Administrator))
        ->get('/settings')
        ->assertOk()
        ->assertSee('System settings')
        ->assertSee('Defect classes')
        ->assertSee('POST /api/v1/inspections/results')
        ->assertSee('php artisan service:token');
});

test('an administrator can revoke a service token', function () {
    [$service, $plain] = serviceToken(['read']);
    $token = $service->tokens()->firstOrFail();

    $this->actingAs(staffUser(Role::Administrator))
        ->get('/settings')
        ->assertOk()
        ->assertSee('test-token');

    $this->actingAs(staffUser(Role::Administrator))
        ->delete('/settings/tokens/'.$token->id)
        ->assertRedirect(route('settings.index'));

    expect($service->tokens()->count())->toBe(0);

    $this->flushSession();
    app('auth')->forgetGuards();

    $this->getJson('/api/v1/devices', bearer($plain))->assertStatus(401);
});

test('staff tokens are rejected by the revoke endpoint', function () {
    $admin = staffUser(Role::Administrator);
    $token = $admin->createToken('web-test', ['read'])->accessToken;

    $this->actingAs($admin)
        ->delete('/settings/tokens/'.$token->id)
        ->assertNotFound();
});

test('the settings page is administrator only', function () {
    $this->actingAs(staffUser(Role::Operator))->get('/settings')->assertForbidden();
});
