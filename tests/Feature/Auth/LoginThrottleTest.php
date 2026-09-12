<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function loginPayload(User $user, string $password = 'correct-password'): array
{
    return ['username' => $user->username, 'password' => $password];
}

beforeEach(function () {
    $this->user = User::factory()->create([
        'username' => 'throttletestuser',
        'password' => Hash::make('correct-password'),
    ]);
});

it('logs a user in normally with correct credentials', function () {
    $response = $this->post('/login', loginPayload($this->user));

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($this->user);
});

it('rejects an incorrect password without throttling a single attempt', function () {
    $response = $this->post('/login', loginPayload($this->user, 'wrong-password'));

    $response->assertRedirect();
    $response->assertSessionHasErrors('username');
    $this->assertGuest();
});

it('throttles login after the configured number of failed attempts', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->post('/login', loginPayload($this->user, 'wrong-password'));
    }

    // The 6th attempt is blocked by the throttle, even with the correct
    // password this time - proves the block is on attempt count, not on
    // repeatedly-wrong credentials.
    $response = $this->post('/login', loginPayload($this->user, 'correct-password'));

    $response->assertSessionHasErrors('username');
    $errors = session('errors')->getBag('default')->get('username');
    expect($errors[0])->toContain('Too many login attempts');
    $this->assertGuest();
});

it('does not throttle a different username from the same browser/IP', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->post('/login', loginPayload($this->user, 'wrong-password'));
    }

    $otherUser = User::factory()->create([
        'username' => 'anotherthrottleuser',
        'password' => Hash::make('correct-password'),
    ]);

    $response = $this->post('/login', loginPayload($otherUser, 'correct-password'));

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($otherUser);
});

it('allows login again once the throttle window has elapsed', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->post('/login', loginPayload($this->user, 'wrong-password'));
    }

    $this->post('/login', loginPayload($this->user, 'correct-password'))
        ->assertSessionHasErrors('username');
    $this->assertGuest();

    // Simulate the 60-second decay window elapsing (RateLimiter's
    // ArrayStore-backed TTL is computed from Carbon::now(), so travelling
    // forward genuinely expires the block rather than faking it).
    $this->travel(61)->seconds();

    $response = $this->post('/login', loginPayload($this->user, 'correct-password'));

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($this->user);
});

it('clears the throttle counter on a successful login', function () {
    $this->post('/login', loginPayload($this->user, 'wrong-password'));
    $this->post('/login', loginPayload($this->user, 'wrong-password'));

    $this->post('/login', loginPayload($this->user, 'correct-password'))
        ->assertRedirect(route('dashboard'));

    $this->post('/logout');

    // Fresh failed attempts after a successful login should get the full
    // 5-attempt allowance again, not continue counting from before.
    for ($i = 0; $i < 4; $i++) {
        $this->post('/login', loginPayload($this->user, 'wrong-password'));
    }

    $response = $this->post('/login', loginPayload($this->user, 'correct-password'));

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($this->user);
});
