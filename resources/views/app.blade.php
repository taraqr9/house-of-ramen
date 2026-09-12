<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#EB5F3F">

    <link rel="icon" href="{{ asset('brand/favicon-32.png') }}" sizes="32x32">
    <link rel="icon" href="{{ asset('brand/favicon-16.png') }}" sizes="16x16">
    <link rel="apple-touch-icon" href="{{ asset('brand/apple-touch-icon-180.png') }}">

    <title inertia>House of Ramen — Modern Ramen & Japanese-Korean Comfort Food in Dhaka</title>
    <meta name="description" content="House of Ramen serves modern Japanese-Korean ramen, rice, noodles, sushi and more in Uttara, Dhaka. Browse the menu, see the space, and find us.">
    <link rel="canonical" href="{{ url()->current() }}">

    <meta property="og:site_name" content="House of Ramen">
    <meta property="og:type" content="website">
    <meta property="og:title" content="House of Ramen — Modern Ramen & Japanese-Korean Comfort Food in Dhaka">
    <meta property="og:description" content="House of Ramen serves modern Japanese-Korean ramen, rice, noodles, sushi and more in Uttara, Dhaka.">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="House of Ramen — Modern Ramen & Japanese-Korean Comfort Food in Dhaka">
    <meta name="twitter:description" content="House of Ramen serves modern Japanese-Korean ramen, rice, noodles, sushi and more in Uttara, Dhaka.">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @inertiaHead
</head>
<body class="bg-stone-50 font-sans text-stone-900 antialiased">
    @inertia
</body>
</html>
