<?php

use App\Enums\ImageStatusEnum;
use App\Models\Phone;
use App\Models\PhoneImage;
use App\Models\PhoneSource;
use App\Models\PhoneVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
});

it('lets a permitted admin upload a phone image, storing it verified and primary immediately', function () {
    $user = phoneDataUser(['phone-view', 'phone-edit']);
    PhoneSource::factory()->create(['key' => 'admin_manual', 'name' => 'Admin (Manual Entry)']);
    $phone = Phone::factory()->create();

    $response = $this->actingAs($user)->post(route('phones.images.store', $phone->id), [
        'image' => UploadedFile::fake()->image('phone.jpg', 800, 800),
    ]);

    $response->assertRedirect(route('phones.edit', $phone->id));

    $image = PhoneImage::where('phone_id', $phone->id)->firstOrFail();
    expect($image->status)->toBe(ImageStatusEnum::VERIFIED)
        ->and($image->is_primary)->toBeTrue()
        ->and($image->mime_type)->toBe('image/webp')
        ->and($image->created_by)->toBe((string) $user->id)
        ->and($image->source->key)->toBe('admin_manual');

    Storage::disk('public')->assertExists($image->path);
    // Immediately eligible for public display, same as an
    // automated/manual-research verified match.
    expect($phone->fresh()->primaryImage?->id)->toBe($image->id);
});

it('resizes an oversized upload down to the same ≤1000px cap the automated pipeline uses', function () {
    $user = phoneDataUser(['phone-view', 'phone-edit']);
    $phone = Phone::factory()->create();

    $this->actingAs($user)->post(route('phones.images.store', $phone->id), [
        'image' => UploadedFile::fake()->image('big.jpg', 2000, 1500),
    ])->assertRedirect();

    $image = PhoneImage::where('phone_id', $phone->id)->firstOrFail();
    expect($image->width)->toBeLessThanOrEqual(1000)
        ->and($image->height)->toBeLessThanOrEqual(1000);
});

it('replaces the existing primary image when a new one is uploaded, without deleting the old row', function () {
    $user = phoneDataUser(['phone-view', 'phone-edit']);
    $phone = Phone::factory()->create();
    $existing = PhoneImage::factory()->for($phone)->create(['is_primary' => true]);

    $this->actingAs($user)->post(route('phones.images.store', $phone->id), [
        'image' => UploadedFile::fake()->image('new.jpg', 600, 600),
    ])->assertRedirect();

    expect($existing->fresh()->is_primary)->toBeFalse();
    expect(PhoneImage::where('phone_id', $phone->id)->count())->toBe(2);
    expect($phone->fresh()->primaryImage->is_primary)->toBeTrue();
});

it('associates an uploaded image with a specific variant when one is chosen', function () {
    $user = phoneDataUser(['phone-view', 'phone-edit']);
    $phone = Phone::factory()->create();
    $variant = PhoneVariant::factory()->create(['phone_id' => $phone->id]);

    $this->actingAs($user)->post(route('phones.images.store', $phone->id), [
        'image' => UploadedFile::fake()->image('variant.jpg', 600, 600),
        'variant_id' => $variant->id,
    ])->assertRedirect();

    $image = PhoneImage::where('phone_id', $phone->id)->firstOrFail();
    expect($image->phone_variant_id)->toBe($variant->id);
});

it('rejects an upload that is not an image', function () {
    $user = phoneDataUser(['phone-view', 'phone-edit']);
    $phone = Phone::factory()->create();

    $response = $this->actingAs($user)->post(route('phones.images.store', $phone->id), [
        'image' => UploadedFile::fake()->create('not-a-photo.pdf', 100, 'application/pdf'),
    ]);

    $response->assertSessionHasErrors('image');
    expect(PhoneImage::where('phone_id', $phone->id)->count())->toBe(0);
});

it('rejects an upload larger than the configured size cap', function () {
    $user = phoneDataUser(['phone-view', 'phone-edit']);
    $phone = Phone::factory()->create();

    $response = $this->actingAs($user)->post(route('phones.images.store', $phone->id), [
        'image' => UploadedFile::fake()->create('huge.jpg', 9000, 'image/jpeg'), // 9000 KB > 8192 KB cap
    ]);

    $response->assertSessionHasErrors('image');
    expect(PhoneImage::where('phone_id', $phone->id)->count())->toBe(0);
});

it('requires a file to be present at all', function () {
    $user = phoneDataUser(['phone-view', 'phone-edit']);
    $phone = Phone::factory()->create();

    $this->actingAs($user)->post(route('phones.images.store', $phone->id), [])
        ->assertSessionHasErrors('image');
});

it('blocks an unauthenticated request from uploading an image', function () {
    $phone = Phone::factory()->create();

    $this->post(route('phones.images.store', $phone->id), [
        'image' => UploadedFile::fake()->image('phone.jpg'),
    ])->assertRedirect(route('login.view'));

    expect(PhoneImage::where('phone_id', $phone->id)->count())->toBe(0);
});

it('blocks a user without phone-edit permission from uploading an image', function () {
    $user = phoneDataUser(['phone-view']); // no phone-edit
    $phone = Phone::factory()->create();

    $this->actingAs($user)->post(route('phones.images.store', $phone->id), [
        'image' => UploadedFile::fake()->image('phone.jpg'),
    ])->assertForbidden();

    expect(PhoneImage::where('phone_id', $phone->id)->count())->toBe(0);
});
