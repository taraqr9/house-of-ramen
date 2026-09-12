<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('redirects unauthenticated users to the login page when visiting the admin panel', function () {
    $response = $this->get('/admin');

    $response->assertRedirect(route('login.view'));
});

it('serves the public homepage without requiring authentication', function () {
    makeRestaurant();

    $response = $this->get('/');

    $response->assertOk();
});
