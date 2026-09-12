<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import PublicLayout from '../../Layouts/PublicLayout.vue';
import SeoHead from '../../Components/Public/SeoHead.vue';

defineOptions({ layout: PublicLayout });

const props = defineProps({
    status: { type: Number, required: true },
});

const COPY = {
    404: { title: 'Page not found', body: "The page you're looking for doesn't exist, or it may have moved." },
    403: { title: "You don't have access", body: "You don't have permission to view this page." },
    419: { title: 'Your session expired', body: 'Please go back and try that again.' },
    500: { title: 'Something went wrong', body: "We hit an unexpected error. It's on our end, not yours - please try again shortly." },
    503: { title: 'Down for maintenance', body: "Phone Kinbo is briefly offline for maintenance. We'll be back shortly." },
};

const copy = computed(() => COPY[props.status] ?? COPY[500]);
const page = usePage();

// Never worth indexing, and never real duplicate content of anything -
// see prompt section 4's "likely non-indexable" list. No single canonical
// URL makes sense for a page that renders under every broken path, so
// this points at the homepage rather than the (often bogus) request URL.
// No manual " — Phone Kinbo" suffix here - app.js/ssr.js's Inertia title
// callback already appends it to every page (see how PhoneController's
// SeoMeta titles work), so doing it here too would double it up.
const seo = computed(() => ({
    title: copy.value.title,
    description: copy.value.body,
    canonical: (page.props.siteMeta?.base_url ?? '') + '/',
    robots: 'noindex,nofollow',
    og_image: null,
    og_type: 'website',
}));
</script>

<template>
    <SeoHead :seo="seo" />

    <div class="mx-auto flex max-w-lg flex-col items-center px-4 py-24 text-center sm:px-6">
        <p class="text-sm font-semibold text-emerald-700">Error {{ status }}</p>
        <h1 class="mt-2 text-2xl font-bold text-stone-900 sm:text-3xl">{{ copy.title }}</h1>
        <p class="mt-3 text-stone-600">{{ copy.body }}</p>

        <div class="mt-8 flex flex-col gap-3 sm:flex-row">
            <Link
                href="/"
                class="inline-flex items-center justify-center rounded-full bg-emerald-700 px-6 py-3 text-sm font-semibold text-white hover:bg-emerald-800"
            >
                Go to homepage
            </Link>
            <Link
                href="/phones"
                class="inline-flex items-center justify-center rounded-full border border-stone-200 px-6 py-3 text-sm font-semibold text-stone-700 hover:border-emerald-300 hover:text-emerald-700"
            >
                Browse phones
            </Link>
        </div>
    </div>
</template>
