<script setup>
import { computed, onMounted } from 'vue';
import { Link } from '@inertiajs/vue3';
import PublicLayout from '../../Layouts/PublicLayout.vue';
import ResultCard from '../../Components/Public/ResultCard.vue';
import SeoHead from '../../Components/Public/SeoHead.vue';
import { formatTaka } from '../../utils/format';
import { trackEvent } from '../../utils/analytics';

defineOptions({ layout: PublicLayout });

const props = defineProps({
    results: { type: Array, default: () => [] },
    criteria: { type: Object, default: () => ({}) },
    seo: { type: Object, required: true },
});

onMounted(() => {
    trackEvent('recommendation_viewed', {
        result_count: props.results.length,
        budget_range: props.criteria.max_budget ?? 'any',
        primary_usage: props.criteria.primary_usage,
    });
});

const best = computed(() => props.results[0] ?? null);

const DIMENSION_LABELS = {
    performance: 'performance',
    gaming: 'gaming performance',
    camera: 'the camera',
    battery: 'battery life',
    display: 'the display',
    software: 'software support',
    build: 'build quality',
    charging: 'charging speed',
    value: 'value for money',
};

// Derived purely from fields the engine already returned for each result
// (price, per-dimension scores, brand) - never a recalculation of the
// underlying match/ranking logic itself.
function captionFor(alt) {
    if (!best.value) return '';

    if (best.value.price - alt.price >= Math.max(1000, best.value.price * 0.08)) {
        return 'Choose this if you want to spend less.';
    }

    if (props.criteria.preferred_brand_ids?.includes(alt.brand_id) && !props.criteria.preferred_brand_ids?.includes(best.value.brand_id)) {
        return `Choose this if you prefer ${alt.brand}.`;
    }

    let strongestDimension = null;
    let strongestDelta = 0;

    for (const [dimension, label] of Object.entries(DIMENSION_LABELS)) {
        const delta = (alt.score_breakdown?.[dimension] ?? 0) - (best.value.score_breakdown?.[dimension] ?? 0);
        if (delta > strongestDelta) {
            strongestDelta = delta;
            strongestDimension = label;
        }
    }

    if (strongestDimension && strongestDelta >= 8) {
        return `Choose this if ${strongestDimension} matters more to you.`;
    }

    return 'A solid alternative worth a look.';
}
</script>

<template>
    <SeoHead :seo="seo" />

    <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6 sm:py-14">
        <p class="text-sm font-semibold tracking-wide text-emerald-700 uppercase">Your matches</p>
        <h1 class="mt-1 text-2xl font-bold text-stone-900 sm:text-3xl">
            Here's what fits what you told us
        </h1>
        <p v-if="criteria.max_budget" class="mt-2 text-stone-600">
            Within a budget of {{ formatTaka(criteria.max_budget) }}.
        </p>

        <p class="mt-3 text-sm text-stone-500">
            These are matched to your answers, not a single "best phone overall" — everyone's priorities are different.
        </p>

        <div v-if="results.length" class="mt-8 grid items-stretch gap-5 sm:grid-cols-2 lg:grid-cols-3">
            <ResultCard
                v-for="(result, index) in results"
                :key="result.phone_id"
                :result="result"
                :featured="index === 0"
                :caption="index === 0 ? '' : captionFor(result)"
                :position="index + 1"
                class="h-full"
            />
        </div>

        <div v-else class="mt-10 rounded-2xl border border-stone-200 bg-white p-8 text-center">
            <h2 class="text-lg font-semibold text-stone-900">No phones matched all of your requirements</h2>
            <p class="mt-2 text-stone-600">
                Try loosening a requirement — a higher budget or one fewer "must-have" usually opens up more
                options.
            </p>
        </div>

        <div class="mt-10 flex flex-col gap-3 sm:flex-row">
            <Link
                href="/#find-my-phone"
                class="inline-flex items-center justify-center rounded-full border border-stone-200 px-6 py-3 text-[15px] font-semibold text-stone-700 hover:border-emerald-300 hover:text-emerald-700"
            >
                Adjust my answers
            </Link>
            <Link
                href="/phones"
                class="inline-flex items-center justify-center rounded-full px-6 py-3 text-[15px] font-semibold text-stone-500 hover:text-emerald-700"
            >
                Browse all phones →
            </Link>
        </div>
    </div>
</template>
