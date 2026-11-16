<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('an administrator can create an operator account', function () {
    $admin = staffUser(Role::Administrator);

    $this->actingAs($admin)->get('/users')->assertOk()->assertSee($admin->name);
    $this->actingAs($admin)->get('/users/create')->assertOk();

    $this->actingAs($admin)
        ->post('/users', [
            'name' => 'New Operator',
            'email' => 'new.operator@garment.test',
            'role' => 'OPERATOR',
            'is_active' => '1',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])
        ->assertRedirect(route('users.index'));

    $created = User::query()->where('email', 'new.operator@garment.test')->firstOrFail();

    expect($created->role)->toBe(Role::Operator)
        ->and($created->is_active)->toBeTrue()
        ->and(Hash::check('secret-password', $created->password))->toBeTrue();
});

test('the service role cannot be assigned through user management', function () {
    $this->actingAs(staffUser(Role::Administrator))
        ->post('/users', [
            'name' => 'Sneaky Service',
            'email' => 'sneaky@garment.test',
            'role' => 'SERVICE',
            'is_active' => '1',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])
        ->assertSessionHasErrors('role');
});

test('an administrator can update a user without changing the password', function () {
    $admin = staffUser(Role::Administrator);
    $operator = staffUser(Role::Operator);
    $originalHash = $operator->password;

    $this->actingAs($admin)->get('/users/'.$operator->id.'/edit')->assertOk();

    $this->actingAs($admin)
        ->put('/users/'.$operator->id, [
            'name' => 'Updated Operator',
            'email' => $operator->email,
            'role' => 'OPERATOR',
            'is_active' => '1',
            'password' => '',
        ])
        ->assertRedirect(route('users.index'));

    $operator->refresh();

    expect($operator->name)->toBe('Updated Operator')
        ->and($operator->password)->toBe($originalHash);
});

test('an administrator can deactivate a user', function () {
    $admin = staffUser(Role::Administrator);
    $operator = staffUser(Role::Operator);

    $this->actingAs($admin)
        ->patch('/users/'.$operator->id.'/status')
        ->assertRedirect();

    expect($operator->fresh()->is_active)->toBeFalse();
});

test('an administrator cannot deactivate their own account', function () {
    $admin = staffUser(Role::Administrator);

    $this->actingAs($admin)
        ->from('/users')
        ->patch('/users/'.$admin->id.'/status')
        ->assertSessionHasErrors('role');

    expect($admin->fresh()->is_active)->toBeTrue();
});

test('the last active administrator cannot be demoted', function () {
    $admin = staffUser(Role::Administrator);

    $this->actingAs($admin)
        ->put('/users/'.$admin->id, [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => 'OPERATOR',
            'is_active' => '1',
        ])
        ->assertSessionHasErrors('role');

    expect($admin->fresh()->role)->toBe(Role::Administrator);
});

test('an administrator can create another administrator', function () {
    $admin = staffUser(Role::Administrator);

    $this->actingAs($admin)
        ->post('/users', [
            'name' => 'Second Admin',
            'email' => 'second.admin@garment.test',
            'role' => 'ADMINISTRATOR',
            'is_active' => '1',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])
        ->assertRedirect(route('users.index'));

    expect(User::query()->where('role', Role::Administrator)->count())->toBe(2);
});

test('service accounts are hidden from user management', function () {
    User::factory()->service()->create();
    $admin = staffUser(Role::Administrator);

    $this->actingAs($admin)->get('/users')
        ->assertOk()
        ->assertDontSee('Service Account');
});
