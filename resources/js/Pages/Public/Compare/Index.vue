<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import PublicLayout from '../../../Layouts/PublicLayout.vue';
import PhoneImage from '../../../Components/Public/PhoneImage.vue';
import MarketPriceSummary from '../../../Components/Public/MarketPriceSummary.vue';
import StarRating from '../../../Components/Public/StarRating.vue';
import SeoHead from '../../../Components/Public/SeoHead.vue';
import { trackEvent } from '../../../utils/analytics';

defineOptions({ layout: PublicLayout });

const props = defineProps({
    selected: { type: Array, default: () => [] },
    maxPhones: { type: Number, default: 4 },
    pickerOptions: { type: Array, default: () => [] },
    seo: { type: Object, required: true },
});

const query = ref('');

const searchResults = computed(() => {
    const selectedSlugs = new Set(props.selected.map((p) => p.slug));
    const term = query.value.trim().toLowerCase();

    return props.pickerOptions
        .filter((option) => !selectedSlugs.has(option.slug))
        .filter((option) => !term || option.name.toLowerCase().includes(term) || option.brand.toLowerCase().includes(term))
        .slice(0, 8);
});

function goTo(slugs) {
    router.get('/compare', slugs.length ? { phones: slugs.join(',') } : {}, { preserveState: true, replace: true });
}

function addPhone(slug) {
    if (props.selected.length >= props.maxPhones) return;
    goTo([...props.selected.map((p) => p.slug), slug]);
    query.value = '';
}

function removePhone(slug) {
    goTo(props.selected.map((p) => p.slug).filter((s) => s !== slug));
}

const rows = computed(() => [
    { label: 'Processor', value: (p) => p.spec?.processor ?? '—' },
    { label: 'Display', value: (p) => (p.spec?.display_size ? `${p.spec.display_size}" ${p.spec.display_panel_type ?? ''}`.trim() : '—') },
    { label: 'Refresh rate', value: (p) => (p.spec?.display_refresh_rate ? `${p.spec.display_refresh_rate}Hz` : '—') },
    { label: 'Main camera', value: (p) => p.spec?.main_camera ?? '—' },
    { label: 'Battery', value: (p) => (p.spec?.battery_capacity_mah ? `${p.spec.battery_capacity_mah.toLocaleString()} mAh` : '—') },
    { label: 'Charging', value: (p) => (p.spec?.charging_speed_w ? `${p.spec.charging_speed_w}W` : '—') },
    { label: '5G', value: (p) => (p.spec?.network_5g === null || p.spec?.network_5g === undefined ? '—' : p.spec.network_5g ? 'Yes' : 'No') },
    { label: 'NFC', value: (p) => (p.spec?.nfc === null || p.spec?.nfc === undefined ? '—' : p.spec.nfc ? 'Yes' : 'No') },
    { label: 'OS updates', value: (p) => (p.spec?.os_update_years ? `${p.spec.os_update_years} years` : '—') },
]);

// Fixed order/labels, matching App\Services\Presentation\PhonePerformanceProfile
// exactly - this is display metadata only (which key to look up and what
// to call it), never a second place scores are computed. The actual
// 0-100 score and star rating for each phone come straight from the
// `performanceProfile` the backend already attached to it.
const statDimensions = [
    { key: 'performance', label: 'Performance' },
    { key: 'gaming', label: 'Gaming' },
    { key: 'camera', label: 'Camera' },
    { key: 'battery', label: 'Battery' },
    { key: 'display', label: 'Display' },
    { key: 'software', label: 'Software' },
    { key: 'build', label: 'Build' },
    { key: 'charging', label: 'Charging' },
];

function statFor(phone, key) {
    return phone.performanceProfile?.find((dimension) => dimension.key === key) ?? null;
}

// Only shown once at least one selected phone actually has a profile -
// no point rendering a whole "not enough data" table for every row.
const hasAnyStats = computed(() => props.selected.some((phone) => phone.performanceProfile?.length));

function trackCompare(selected) {
    trackEvent('compare_phones', {
        phone_ids: selected.map((p) => p.slug),
        phone_names: selected.map((p) => p.name),
        brands: selected.map((p) => p.brand),
        number_of_phones: selected.length,
    });
}

// `selected` comes from the `phones` query string, so both a fresh visit
// to /compare with phones already picked and every add/remove afterwards
// (goTo() above, preserveState) need to be covered - onMounted for the
// former, the watcher for the latter.
onMounted(() => {
    if (props.selected.length === 0) {
        trackEvent('compare_started');
    } else if (props.selected.length >= 2) {
        trackCompare(props.selected);
    }
});

watch(
    () => props.selected,
    (selected, previous) => {
        if (selected.length >= 2 && selected.length !== previous?.length) {
            trackCompare(selected);
        }
    },
);
</script>

<template>
    <SeoHead :seo="seo" />

    <div class="mx-auto max-w-5xl px-4 py-10 sm:px-6 sm:py-14">
        <h1 class="text-2xl font-bold text-stone-900 sm:text-3xl">Compare phones</h1>
        <p class="mt-2 text-stone-600">Pick up to {{ maxPhones }} phones to see them side by side.</p>

        <!-- Picker -->
        <div v-if="selected.length < maxPhones" class="relative mt-6 max-w-md">
            <input
                v-model="query"
                type="text"
                placeholder="Search a phone to add…"
                class="w-full rounded-full border border-stone-200 bg-white px-5 py-3 text-[15px] focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 focus:outline-none"
                aria-label="Search a phone to add to comparison"
            />

            <ul v-if="query && searchResults.length" class="absolute z-10 mt-2 w-full overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-lg">
                <li v-for="option in searchResults" :key="option.slug">
                    <button
                        type="button"
                        class="flex w-full items-center justify-between px-4 py-3 text-left text-sm hover:bg-emerald-50"
                        @click="addPhone(option.slug)"
                    >
                        <span class="font-medium text-stone-800">{{ option.name }}</span>
                        <span class="text-stone-400">{{ option.brand }}</span>
                    </button>
                </li>
            </ul>
        </div>

        <!-- Comparison table -->
        <div v-if="selected.length" class="mt-8 overflow-x-auto">
            <table class="w-full min-w-[560px] border-separate border-spacing-0 text-sm">
                <thead>
                    <tr>
                        <th scope="col" class="w-40"></th>
                        <th v-for="phone in selected" :key="phone.slug" scope="col" class="px-4 pb-4 text-left align-bottom">
                            <div class="rounded-2xl border border-stone-200 bg-white p-4">
                                <button
                                    type="button"
                                    class="mb-2 text-xs font-medium text-stone-400 hover:text-red-600"
                                    @click="removePhone(phone.slug)"
                                >
                                    Remove ✕
                                </button>
                                <PhoneImage :src="phone.image_url" :label="phone.name" size="sm" />
                                <p class="mt-2 text-xs font-medium text-stone-400">{{ phone.brand }}</p>
                                <p class="font-semibold text-stone-900">{{ phone.name }}</p>
                                <p v-if="phone.region" class="mt-0.5 text-xs font-medium text-stone-400">{{ phone.region }} version</p>
                                <div class="mt-3">
                                    <MarketPriceSummary :market="phone.market" stacked />
                                </div>
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Phone statistics - the same PhoneScorer-derived
                         profile shown on each phone's own detail page,
                         lined up column-by-column so every dimension is a
                         direct side-by-side comparison. Shown first, ahead
                         of the raw spec rows below. -->
                    <template v-if="hasAnyStats">
                        <tr>
                            <th scope="colgroup" colspan="100%" class="bg-stone-200/70 px-4 py-2 text-left text-xs font-bold tracking-wide text-stone-600 uppercase">
                                Phone statistics
                            </th>
                        </tr>
                        <tr v-for="dimension in statDimensions" :key="dimension.key" class="odd:bg-white even:bg-stone-100/60">
                            <th scope="row" class="px-4 py-3 text-left text-xs font-semibold text-stone-500">{{ dimension.label }}</th>
                            <td v-for="phone in selected" :key="phone.slug" class="px-4 py-3">
                                <StarRating
                                    v-if="statFor(phone, dimension.key)"
                                    :stars="statFor(phone, dimension.key).stars"
                                    :score="statFor(phone, dimension.key).score"
                                    :label="dimension.label"
                                    :id-prefix="`compare-${phone.slug}-${dimension.key}`"
                                    :show-bar="false"
                                />
                                <span v-else class="text-stone-400">—</span>
                            </td>
                        </tr>
                    </template>

                    <tr v-for="row in rows" :key="row.label" class="odd:bg-white even:bg-stone-100/60">
                        <th scope="row" class="px-4 py-3 text-left text-xs font-semibold text-stone-500">{{ row.label }}</th>
                        <td v-for="phone in selected" :key="phone.slug" class="px-4 py-3 text-stone-700">
                            {{ row.value(phone) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-else class="mt-10 rounded-2xl border border-stone-200 bg-white p-8 text-center text-stone-600">
            Search for a phone above to start comparing.
        </div>
    </div>
</template>
