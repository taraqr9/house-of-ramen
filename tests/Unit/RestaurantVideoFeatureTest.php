<?php

use App\Models\RestaurantVideoFeature;

it('extracts the youtube video id from every common url form', function (string $url, string $expectedId) {
    $video = new RestaurantVideoFeature(['video_url' => $url]);

    expect($video->youtube_video_id)->toBe($expectedId);
})->with([
    'watch url' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
    'watch url with extra params' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=30s', 'dQw4w9WgXcQ'],
    'short url' => ['https://youtu.be/dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
    'embed url' => ['https://www.youtube.com/embed/dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
    'shorts url' => ['https://www.youtube.com/shorts/dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
]);

it('returns null for a url with no recognizable video id', function () {
    $video = new RestaurantVideoFeature(['video_url' => 'https://example.com/not-a-video']);

    expect($video->youtube_video_id)->toBeNull();
});

it('detects the platform for youtube, facebook, and instagram links', function (string $url, ?string $expectedPlatform) {
    expect(RestaurantVideoFeature::detectPlatform($url))->toBe($expectedPlatform);
})->with([
    'youtube watch url' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'youtube'],
    'youtube short url' => ['https://youtu.be/dQw4w9WgXcQ', 'youtube'],
    'facebook video url' => ['https://www.facebook.com/HouseOfRamen/videos/1234567890/', 'facebook'],
    'facebook watch shortcode' => ['https://fb.watch/abc123/', 'facebook'],
    'instagram reel url' => ['https://www.instagram.com/reel/Cabc123XYZ9/', 'instagram'],
    'instagram post url' => ['https://www.instagram.com/p/Cabc123XYZ9/', 'instagram'],
    'unsupported url' => ['https://example.com/some-video', null],
    'instagram profile without a shortcode' => ['https://www.instagram.com/houseoframen/', null],
]);

it('derives the platform attribute from the stored video_url', function () {
    $video = new RestaurantVideoFeature(['video_url' => 'https://www.facebook.com/HouseOfRamen/videos/1234567890/']);

    expect($video->platform)->toBe('facebook');
});

it('extracts the instagram embed path for reel, post, and tv links', function (string $url, string $expectedPath) {
    $video = new RestaurantVideoFeature(['video_url' => $url]);

    expect($video->instagram_embed_path)->toBe($expectedPath);
})->with([
    'reel' => ['https://www.instagram.com/reel/Cabc123XYZ9/', 'reel/Cabc123XYZ9'],
    'post' => ['https://www.instagram.com/p/Cabc123XYZ9/', 'p/Cabc123XYZ9'],
    'tv' => ['https://www.instagram.com/tv/Cabc123XYZ9/', 'tv/Cabc123XYZ9'],
]);
