<script setup>
// Single place every public page uses to render <head> tags, so the
// canonical/robots/OG policy decided server-side (see App\Services\Seo\SeoMeta)
// actually reaches the page instead of every page hand-rolling its own
// <Head> block and inevitably drifting. Requires SSR (resources/js/ssr.js) to
// actually be visible to crawlers/social scrapers that don't execute JS -
// without it these tags only exist after client-side hydration.
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    seo: { type: Object, required: true },
});
</script>

<template>
    <Head>
        <title>{{ seo.title }}</title>
        <meta name="description" :content="seo.description" />
        <meta name="robots" :content="seo.robots" />
        <link rel="canonical" :href="seo.canonical" />

        <meta property="og:site_name" content="Phone Kinbo" />
        <meta property="og:type" :content="seo.og_type" />
        <meta property="og:title" :content="seo.title" />
        <meta property="og:description" :content="seo.description" />
        <meta property="og:url" :content="seo.canonical" />
        <meta v-if="seo.og_image" property="og:image" :content="seo.og_image" />

        <meta name="twitter:card" :content="seo.og_image ? 'summary_large_image' : 'summary'" />
        <meta name="twitter:title" :content="seo.title" />
        <meta name="twitter:description" :content="seo.description" />
        <meta v-if="seo.og_image" name="twitter:image" :content="seo.og_image" />
    </Head>
</template>
