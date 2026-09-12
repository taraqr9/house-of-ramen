<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#00845A">

    <link rel="icon" href="{{ asset('brand/favicon.svg') }}" type="image/svg+xml">
    <link rel="icon" href="{{ asset('brand/favicon-32.png') }}" sizes="32x32">
    <link rel="apple-touch-icon" href="{{ asset('brand/apple-touch-icon-180.png') }}">

    <title inertia>Phone Kinbo — Find the right phone for you</title>
    <meta name="description" content="Tell Phone Kinbo your budget and what matters to you, and get personalised phone recommendations for Bangladesh — no sign-up required.">
    <link rel="canonical" href="{{ url()->current() }}">

    <meta property="og:site_name" content="Phone Kinbo">
    <meta property="og:type" content="website">
    <meta property="og:title" content="Phone Kinbo — Find the right phone for you">
    <meta property="og:description" content="Tell Phone Kinbo your budget and what matters to you, and get personalised phone recommendations for Bangladesh.">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="Phone Kinbo — Find the right phone for you">
    <meta name="twitter:description" content="Tell Phone Kinbo your budget and what matters to you, and get personalised phone recommendations for Bangladesh.">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @inertiaHead
</head>
<body class="bg-stone-50 font-sans text-stone-900 antialiased">
    @inertia
</body>
</html>
