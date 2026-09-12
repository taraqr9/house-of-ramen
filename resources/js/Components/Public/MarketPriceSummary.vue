<script setup>
import { computed } from 'vue';
import { formatTaka } from '../../utils/format';

const props = defineProps({
    // { official: {price, price_min, price_max, has_range, retailer_count, last_checked_at} | null, unofficial: {...} | null }
    market: { type: Object, required: true },
    // Narrow contexts (e.g. a comparison column) should never go side-by-side.
    stacked: { type: Boolean, default: false },
});

const lastChecked = computed(() => {
    const dates = [props.market.official?.last_checked_at, props.market.unofficial?.last_checked_at].filter(Boolean);

    if (! dates.length) return null;

    return new Date(dates.sort().at(-1)).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
});
</script>

<template>
    <div v-if="market.official || market.unofficial" class="flex flex-col gap-3" :class="!stacked && 'sm:flex-row'">
        <div v-if="market.official" class="flex-1 rounded-2xl border border-emerald-200 bg-emerald-50/40 p-4">
            <p class="text-xs font-semibold tracking-wide text-emerald-700 uppercase">Official</p>
            <p class="mt-1 text-xl font-bold text-stone-900">{{ formatTaka(market.official.price) }}</p>
            <p v-if="market.official.retailer_count > 1" class="mt-1 text-xs text-stone-500">
                Based on {{ market.official.retailer_count }} retailers
            </p>
        </div>

        <div v-if="market.unofficial" class="flex-1 rounded-2xl border border-amber-200 bg-amber-50/40 p-4">
            <p class="text-xs font-semibold tracking-wide text-amber-700 uppercase">Unofficial</p>
            <p class="mt-1 text-xl font-bold text-stone-900">{{ formatTaka(market.unofficial.price) }}</p>
            <p v-if="market.unofficial.has_range" class="mt-1 text-xs text-stone-500">
                Typically {{ formatTaka(market.unofficial.price_min) }} – {{ formatTaka(market.unofficial.price_max) }}
            </p>
            <p v-if="market.unofficial.retailer_count > 1" class="mt-1 text-xs text-stone-500">
                Based on {{ market.unofficial.retailer_count }} retailers
            </p>
        </div>
    </div>
    <p v-else class="text-sm text-stone-400">Price not currently available.</p>

    <p v-if="lastChecked" class="mt-2 text-xs text-stone-400">Last checked: {{ lastChecked }}</p>
</template>
