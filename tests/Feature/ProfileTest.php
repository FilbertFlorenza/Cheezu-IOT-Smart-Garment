<?php

use App\Enums\Role;
use App\Models\User;

test('profile page is displayed for an authenticated user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/profile')->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch('/profile', [
            'name' => 'Test User',
            'email' => 'updated@example.com',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $user->refresh();

    expect($user->name)->toBe('Test User')
        ->and($user->email)->toBe('updated@example.com');
});

test('an email address already in use cannot be taken', function () {
    $other = User::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch('/profile', [
            'name' => $user->name,
            'email' => $other->email,
        ])
        ->assertSessionHasErrors('email');
});

test('service accounts cannot sign in to the web interface', function () {
    $service = User::factory()->service()->create();

    $this->post('/login', [
        'email' => $service->email,
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('inactive accounts cannot sign in', function () {
    $inactive = User::factory()->create(['is_active' => false]);

    $this->post('/login', [
        'email' => $inactive->email,
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('an inactive user is signed out of an existing session', function () {
    $user = User::factory()->create(['is_active' => false]);

    $this->actingAs($user)->get('/dashboard')->assertRedirect(route('login'));

    $this->assertGuest();
});

test('roles are the expected set', function () {
    expect(array_map(fn (Role $role) => $role->value, Role::cases()))
        ->toBe(['ADMINISTRATOR', 'OPERATOR', 'SERVICE']);
});

test('public registration routes do not exist', function () {
    $this->get('/register')->assertNotFound();
});
