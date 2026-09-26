<?php

use App\Enums\StatusEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create([
        'username' => 'inactivetestuser',
        'password' => Hash::make('correct-password'),
    ]);
});

it('refuses web login for an inactive user with a normal form error', function () {
    $this->user->update(['is_active' => StatusEnum::INACTIVE]);

    $response = $this->from('/login')->post('/login', ['username' => 'inactivetestuser', 'password' => 'correct-password']);

    $response->assertRedirect('/login');
    $response->assertSessionHasErrors(['username' => 'Your account is disabled.']);
    $response->assertSessionHasInput('username', 'inactivetestuser');
    $this->assertGuest();
});

it('still shows the generic error for a wrong password on an inactive account', function () {
    $this->user->update(['is_active' => StatusEnum::INACTIVE]);

    $this->from('/login')->post('/login', ['username' => 'inactivetestuser', 'password' => 'wrong-password'])
        ->assertSessionHasErrors(['username' => 'Invalid username or password.']);
    $this->assertGuest();
});

it('logs in again once the account is re-activated', function () {
    $this->user->update(['is_active' => StatusEnum::INACTIVE]);
    $this->post('/login', ['username' => 'inactivetestuser', 'password' => 'correct-password']);
    $this->assertGuest();

    $this->user->update(['is_active' => StatusEnum::ACTIVE]);
    $this->post('/login', ['username' => 'inactivetestuser', 'password' => 'correct-password'])
        ->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($this->user);
});
