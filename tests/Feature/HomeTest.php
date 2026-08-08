<?php

use App\Models\User;

test('a guest at the root is sent to the login screen', function () {
    $this->get(route('home'))->assertRedirect(route('login'));
});

test('a signed in user at the root is sent to the focus screen', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('home'))
        ->assertRedirect(route('focus.show'));
});
