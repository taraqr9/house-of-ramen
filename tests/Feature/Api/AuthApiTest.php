<?php

use App\Enums\StatusEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\PersonalAccessToken;

uses(RefreshDatabase::class);

function apiUser(array $attributes = [], array $permissions = []): User
{
    $user = adminUser($permissions);
    $user->update(array_merge(['username' => 'cashier1', 'password' => Hash::make('Secret#123')], $attributes));

    return $user->fresh();
}

function login(array $overrides = []): TestResponse
{
    return test()->postJson('/api/v1/auth/login', array_merge([
        'username' => 'cashier1', 'password' => 'Secret#123', 'device_name' => 'Counter Tablet',
    ], $overrides));
}

it('logs in with the web credentials and returns a bearer token + safe user info', function () {
    apiUser(permissions: ['order-view']);

    $response = login()->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.user.username', 'cashier1')
        ->assertJsonPath('data.user.permissions', ['order-view']);

    expect($response->json('data.token'))->toBeString()->not->toBeEmpty()
        ->and($response->json('data.expires_at'))->not->toBeNull()
        ->and(json_encode($response->json()))->not->toContain('password')->not->toContain('remember_token');

    $this->withToken($response->json('data.token'))->getJson('/api/v1/auth/me')
        ->assertOk()->assertJsonPath('data.username', 'cashier1');
});

it('rejects wrong credentials with the same message for unknown users', function () {
    apiUser();

    login(['password' => 'wrong'])->assertStatus(401)->assertJson(['success' => false, 'message' => 'Invalid username or password.']);
    login(['username' => 'nobody'])->assertStatus(401)->assertJson(['success' => false, 'message' => 'Invalid username or password.']);
    login(['device_name' => ''])->assertStatus(422)->assertJson(['success' => false, 'message' => 'Validation failed.'])->assertJsonValidationErrors('device_name');
});

it('refuses inactive users and users who still have a temporary password', function () {
    $user = apiUser(['is_active' => StatusEnum::INACTIVE]);
    login()->assertForbidden()->assertJsonPath('message', 'Your account is disabled.');

    $user->update(['is_active' => StatusEnum::ACTIVE, 'password_changed_at' => null, 'password_setup_token' => 'abc']);
    login()->assertForbidden()->assertJsonPath('success', false);
});

it('blocks a token as soon as its user is deactivated', function () {
    $user = apiUser(permissions: ['order-view']);
    $token = login()->json('data.token');

    $user->update(['is_active' => StatusEnum::INACTIVE]);

    $this->withToken($token)->getJson('/api/v1/auth/me')->assertForbidden();
    expect(PersonalAccessToken::count())->toBe(0);
});

it('requires a token for protected routes', function () {
    $this->getJson('/api/v1/auth/me')->assertUnauthorized()->assertExactJson(['success' => false, 'message' => 'Unauthenticated.']);
    $this->getJson('/api/v1/pos/bootstrap')->assertUnauthorized();
    $this->withToken('not-a-real-token')->getJson('/api/v1/pos/tables')->assertUnauthorized();
    // No Accept header: still JSON.
    $this->get('/api/v1/pos/tables')->assertUnauthorized()->assertJsonPath('success', false);
});

it('revokes the current token on logout', function () {
    apiUser();
    $token = login()->json('data.token');

    $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk()->assertJsonPath('success', true);

    expect(PersonalAccessToken::count())->toBe(0);
    app('auth')->forgetGuards();
    $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
});

it('keeps one token per device name and expires tokens', function () {
    apiUser();
    login();
    login();
    login(['device_name' => 'Kitchen Tablet']);

    expect(PersonalAccessToken::count())->toBe(2)
        ->and(PersonalAccessToken::first()->expires_at)->not->toBeNull();

    $token = login()->json('data.token');
    $this->travel(31)->days();
    app('auth')->forgetGuards();
    $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
});

it('rate limits login attempts', function () {
    apiUser();
    foreach (range(1, 5) as $i) {
        login(['password' => 'wrong'])->assertStatus(401);
    }

    login(['password' => 'wrong'])->assertStatus(429)->assertJsonPath('success', false)->assertHeader('Retry-After');
});

it('returns the JSON envelope for 404s without leaking internals', function () {
    $user = apiUser(permissions: ['order-view']);
    $token = login()->json('data.token');

    $this->withToken($token)->getJson('/api/v1/pos/orders/999999')->assertNotFound()
        ->assertExactJson(['success' => false, 'message' => 'Resource not found.']);
    $this->withToken($token)->getJson('/api/v1/nope')->assertNotFound()->assertJsonPath('success', false);
});
