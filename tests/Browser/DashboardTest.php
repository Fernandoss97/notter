<?php

use App\Models\User;

test('guests are redirected away from the dashboard', function () {
    $page = visit('/dashboard');

    $page->assertPathIs('/login')
        ->assertNoJavaScriptErrors();
});

test('a user can log in and reach the dashboard', function () {
    $user = User::factory()->create();

    $page = visit('/login');

    $page->assertSee('Log in')
        ->assertNoJavaScriptErrors()
        ->fill('email', $user->email)
        ->fill('password', 'password')
        ->click('Log in')
        ->assertPathIs('/dashboard')
        ->assertNoJavaScriptErrors();

    $this->assertAuthenticated();
});
