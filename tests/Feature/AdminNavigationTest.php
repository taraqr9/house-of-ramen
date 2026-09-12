<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Regression test: the admin theme's topbar logo/home link shipped
 * pointing at the purchased theme's demo page ("index-2.html"), a URL
 * that doesn't exist in this app - clicking it 404'd instead of going
 * home. See resources/views/partials/nav.blade.php.
 */
it('points the admin topbar logo at the real dashboard route, not the theme demo page', function () {
    $user = phoneDataUser(['dashboard-view']);

    $response = $this->actingAs($user)->get('/admin');

    $response->assertOk();
    $response->assertDontSee('index-2.html', false);
    $response->assertSee(route('dashboard'), false);
});
