<script setup>
import { Link } from '@inertiajs/vue3';
import PhoneImage from './PhoneImage.vue';
import { formatTaka } from '../../utils/format';
import { trackEvent } from '../../utils/analytics';

const props = defineProps({
    result: { type: Object, required: true },
    featured: { type: Boolean, default: false },
    caption: { type: String, default: '' },
    // 1-indexed position within the results list - only set on the
    // Find My Phone results page, where "which position gets clicked
    // most" is meaningful; the homepage's single example card leaves it
    // unset rather than always claiming position 1.
    position: { type: Number, default: null },
});

const detailHref = `/phones/${props.result.phone_slug}`;

function onDetailClick() {
    trackEvent('recommendation_phone_clicked', {
        phone_id: props.result.phone_id,
        phone_name: props.result.phone_name,
        brand: props.result.brand,
        position: props.position,
        match_score: Math.round(props.result.match_score),
    });
}

// The chosen price already reflects the buyer's official/unofficial
// preference - but if the OTHER market also exists for this phone, it's
// still worth surfacing for transparency (never silently hidden).
const alternatePrice = props.result.is_official_bd ? props.result.unofficial_price : props.result.official_price;
const alternateLabel = props.result.is_official_bd ? 'unofficially' : 'officially';
</script>

<template>
    <article
        class="flex h-full flex-col gap-4 rounded-2xl border bg-white p-5 sm:p-6"
        :class="featured ? 'border-emerald-200 shadow-sm ring-1 ring-emerald-100' : 'border-stone-200'"
    >
        <div class="flex items-start gap-3 sm:gap-4">
            <PhoneImage :src="result.image_url" :label="result.phone_name" size="md" />

            <div class="min-w-0 flex-1">
                <p v-if="featured" class="text-xs font-semibold tracking-wide text-emerald-700 uppercase">Best match</p>
                <p v-else class="text-xs font-semibold tracking-wide text-stone-400 uppercase">Alternative</p>

                <h3 class="mt-0.5 text-lg font-semibold text-stone-900" :class="featured && 'sm:text-xl'">
                    {{ result.phone_name }}
                </h3>

                <p class="mt-1 text-lg font-semibold text-stone-900">{{ formatTaka(result.price) }}</p>

                <span
                    class="mt-1 inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium"
                    :class="result.is_official_bd ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'"
                >
                    {{ result.is_official_bd ? 'Official BD import' : 'Unofficial import' }}
                </span>
                <span
                    v-if="result.is_chinese_variant"
                    class="mt-1 ml-1 inline-flex items-center gap-1 rounded-full bg-stone-100 px-2.5 py-0.5 text-xs font-medium text-stone-600"
                >
                    Chinese version
                </span>

                <p v-if="alternatePrice" class="mt-1 text-xs text-stone-400">
                    Also available {{ alternateLabel }} at {{ formatTaka(alternatePrice) }}
                </p>
            </div>

            <div class="flex shrink-0 flex-col items-center">
                <div
                    class="flex h-14 w-14 items-center justify-center rounded-full text-base font-bold"
                    :class="featured ? 'bg-emerald-700 text-white' : 'bg-stone-100 text-stone-700'"
                >
                    {{ Math.round(result.match_score) }}%
                </div>
                <span class="mt-1 text-[11px] text-stone-400">match</span>
            </div>
        </div>

        <p v-if="caption" class="rounded-xl bg-stone-50 px-3 py-2 text-sm font-medium text-stone-700">
            {{ caption }}
        </p>

        <div v-if="result.reasons?.length" class="space-y-1.5">
            <p class="text-sm font-semibold text-stone-900">Why this phone?</p>
            <ul class="space-y-1">
                <li v-for="reason in result.reasons" :key="reason" class="flex gap-2 text-sm text-stone-600">
                    <span class="mt-0.5 text-emerald-600" aria-hidden="true">✓</span>
                    <span>{{ reason }}</span>
                </li>
            </ul>
        </div>

        <div v-if="result.tradeoffs?.length" class="space-y-1.5">
            <p class="text-sm font-semibold text-stone-900">Things to consider</p>
            <ul class="space-y-1">
                <li v-for="tradeoff in result.tradeoffs" :key="tradeoff" class="flex gap-2 text-sm text-stone-500">
                    <span class="mt-0.5" aria-hidden="true">•</span>
                    <span>{{ tradeoff }}</span>
                </li>
            </ul>
        </div>

        <Link
            :href="detailHref"
            class="mt-auto inline-flex items-center justify-center rounded-full border border-stone-200 px-4 py-2.5 text-sm font-semibold text-stone-700 transition-colors hover:border-emerald-300 hover:text-emerald-700"
            @click="onDetailClick"
        >
            View full details
        </Link>
    </article>
</template>
