<?php

use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('marks a notification with a link as read and reports the target url', function () {
    $user = User::factory()->create();

    NotificationService::send($user, 'With Link', 'Has a target url.', '/dashboard', 'bx-cart', 'success');

    $notification = $user->notifications()->first();

    expect($notification->read_at)->toBeNull();
    expect($notification->data['url'])->toBe('/dashboard');

    $response = $this->actingAs($user)
        ->post(route('notifications.read', $notification->id));

    $response->assertNoContent();

    expect($notification->fresh()->read_at)->not->toBeNull();
});

it('marks a notification without a link as read with no url to follow', function () {
    $user = User::factory()->create();

    NotificationService::send($user, 'No Link', 'Has no target url.');

    $notification = $user->notifications()->first();

    expect($notification->data['url'])->toBeNull();

    $this->actingAs($user)
        ->post(route('notifications.read', $notification->id))
        ->assertNoContent();

    expect($notification->fresh()->read_at)->not->toBeNull();
});

it('lists notifications and filters by read/unread status', function () {
    $user = User::factory()->create();

    NotificationService::send($user, 'Unread One', 'Message');
    NotificationService::send($user, 'Read One', 'Message');
    $user->notifications()->where('data->title', 'Read One')->first()->markAsRead();

    $this->actingAs($user)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertViewHas('notifications', fn ($notifications) => $notifications->total() === 2);

    $this->actingAs($user)
        ->get(route('notifications.index', ['status' => 'unread']))
        ->assertOk()
        ->assertViewHas('notifications', fn ($notifications) => $notifications->total() === 1
            && $notifications->first()->data['title'] === 'Unread One');

    $this->actingAs($user)
        ->get(route('notifications.index', ['status' => 'read']))
        ->assertOk()
        ->assertViewHas('notifications', fn ($notifications) => $notifications->total() === 1
            && $notifications->first()->data['title'] === 'Read One');
});

it('marks all notifications as read', function () {
    $user = User::factory()->create();

    NotificationService::send($user, 'One', 'Message');
    NotificationService::send($user, 'Two', 'Message');

    expect($user->unreadNotifications()->count())->toBe(2);

    $this->actingAs($user)
        ->post(route('notifications.mark-all-read'))
        ->assertRedirect();

    expect($user->unreadNotifications()->count())->toBe(0);
});
