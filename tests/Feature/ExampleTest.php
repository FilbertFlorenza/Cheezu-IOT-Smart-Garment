<?php

use App\Models\User;

test('the root path redirects guests to the login screen', function () {
    $this->get('/')->assertRedirect(route('login'));
});

test('the root path redirects authenticated users to the dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/')->assertRedirect(route('dashboard'));
});
