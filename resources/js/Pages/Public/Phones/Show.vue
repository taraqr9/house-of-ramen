<script setup>
import { computed, onMounted, ref } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import PublicLayout from '../../../Layouts/PublicLayout.vue';
import PhoneImage from '../../../Components/Public/PhoneImage.vue';
import MarketPriceSummary from '../../../Components/Public/MarketPriceSummary.vue';
import PhoneStatistics from '../../../Components/Public/PhoneStatistics.vue';
import SeoHead from '../../../Components/Public/SeoHead.vue';
import Breadcrumbs from '../../../Components/Public/Breadcrumbs.vue';
import { formatTaka } from '../../../utils/format';
import { jsonLdVNode } from '../../../utils/jsonLd';
import { trackEvent } from '../../../utils/analytics';

defineOptions({ layout: PublicLayout });

const props = defineProps({
    phone: { type: Object, required: true },
    highlights: { type: Object, default: () => ({ pros: [], considerations: [] }) },
    performanceProfile: { type: Array, default: null },
    related: { type: Array, default: () => [] },
    breadcrumbs: { type: Array, required: true },
    seo: { type: Object, required: true },
});

const page = usePage();

// Which region's variant table is showing - only meaningful when the
// phone actually has more than one (see phone.variant_regions, grouped
// server-side by App\Services\Presentation\PhoneVariantRegionSelector).
// Defaults to the first group, which the backend always orders Global
// first when both exist.
const activeRegion = ref(props.phone.variant_regions[0]?.region ?? null);

const activeRegionVariants = computed(
    () => props.phone.variant_regions.find((group) => group.region === activeRegion.value)?.variants ?? [],
);

const spec = computed(() => props.phone.spec ?? {});

const specGroups = computed(() => {
    const s = spec.value;

    const groups = [
        {
            title: 'Performance',
            rows: [
                ['Processor', s.processor],
                ['GPU', s.gpu],
            ],
        },
        {
            title: 'Display',
            rows: [
                ['Size', s.display_size ? `${s.display_size}"` : null],
                ['Resolution', s.display_resolution],
                ['Panel', s.display_panel_type],
                ['Refresh rate', s.display_refresh_rate ? `${s.display_refresh_rate}Hz` : null],
            ],
        },
        {
            title: 'Camera',
            rows: [
                ['Main camera', s.main_camera],
                ['Ultrawide', s.ultrawide_camera],
                ['Telephoto', s.telephoto_camera],
                ['Front camera', s.front_camera],
            ],
        },
        {
            title: 'Battery & charging',
            rows: [
                ['Battery', s.battery_capacity_mah ? `${s.battery_capacity_mah.toLocaleString()} mAh` : null],
                ['Charging speed', s.charging_speed_w ? `${s.charging_speed_w}W` : null],
            ],
        },
        {
            title: 'Build & connectivity',
            rows: [
                ['Build', s.build_materials],
                ['Water/dust resistance', s.ip_rating],
                ['5G', s.network_5g === null ? null : s.network_5g ? 'Yes' : 'No'],
                ['NFC', s.nfc === null ? null : s.nfc ? 'Yes' : 'No'],
                ['Weight', s.weight_g ? `${s.weight_g}g` : null],
            ],
        },
        {
            title: 'Software',
            rows: [
                ['Ships with', s.os],
                ['OS update support', s.os_update_years ? `${s.os_update_years} years` : null],
            ],
        },
    ];

    return groups
        .map((group) => ({ ...group, rows: group.rows.filter(([, value]) => value !== null && value !== undefined && value !== '') }))
        .filter((group) => group.rows.length > 0);
});

// Real offers only - an unofficial/grey-market import is a materially
// different purchase (no manufacturer warranty) from an official one, so
// each becomes its own Offer rather than being blended into a single
// price. Availability reflects the actual most-recently-collected status
// (schema_availability, built server-side) rather than an assumed
// "in stock" - see prompt section 8.
const offers = computed(() => {
    const list = [];
    const base = { '@type': 'Offer', priceCurrency: 'BDT', url: props.seo.canonical };
    if (props.phone.schema_availability) base.availability = props.phone.schema_availability;

    if (props.phone.market.official) {
        list.push({ ...base, price: props.phone.market.official.price, seller: { '@type': 'Organization', name: 'Official Bangladesh retailer' } });
    }
    if (props.phone.market.unofficial) {
        list.push({ ...base, price: props.phone.market.unofficial.price, seller: { '@type': 'Organization', name: 'Unofficial / grey-market import' } });
    }
    return list;
});

const structuredData = computed(() => ({
    '@context': 'https://schema.org',
    '@type': 'Product',
    name: props.phone.name,
    url: props.seo.canonical,
    ...(props.phone.image_url ? { image: [props.phone.image_url] } : {}),
    brand: { '@type': 'Brand', name: props.phone.brand },
    ...(offers.value.length ? { offers: offers.value } : {}),
}));

// The buyer-facing headline price - same "cheapest current market price,
// official or unofficial" rule as MarketPriceSummary.vue displays.
onMounted(() => {
    const official = props.phone.market.official;
    const unofficial = props.phone.market.unofficial;
    const cheapest = [official, unofficial].filter(Boolean).sort((a, b) => a.price - b.price)[0] ?? null;

    trackEvent('view_phone', {
        phone_id: props.phone.id,
        phone_name: props.phone.name,
        brand: props.phone.brand,
        price: cheapest?.price ?? null,
        price_type: cheapest === official ? 'official' : cheapest === unofficial ? 'unofficial' : null,
        route: page.url,
    });
});
</script>

<template>
    <SeoHead :seo="seo" />
    <Head>
        <component :is="jsonLdVNode(structuredData, 'json-ld-product')" />
    </Head>

    <div class="mx-auto max-w-4xl px-4 py-10 sm:px-6 sm:py-14">
        <Breadcrumbs :items="breadcrumbs" />

        <div class="mt-4 flex flex-col gap-6 sm:flex-row">
            <div>
                <PhoneImage :src="phone.image_url" :label="phone.name" size="lg" />
                <p v-if="phone.image_url && phone.image_attribution" class="mt-1 max-w-32 text-center text-[10px] leading-tight text-stone-300">
                    {{ phone.image_attribution }}
                </p>
            </div>

            <div class="min-w-0 flex-1">
                <p class="text-sm font-medium text-stone-400">{{ phone.brand }}</p>
                <h1 class="mt-0.5 text-2xl font-bold text-stone-900 sm:text-3xl">{{ phone.name }}</h1>

                <div class="mt-3">
                    <MarketPriceSummary :market="phone.market" />
                    <p v-if="phone.primary_region" class="mt-2 text-xs font-medium text-stone-400">{{ phone.primary_region }} version</p>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    <span class="rounded-full bg-stone-100 px-3 py-1 text-xs font-medium text-stone-600">{{ phone.status }}</span>
                    <span v-if="phone.release_date" class="rounded-full bg-stone-100 px-3 py-1 text-xs font-medium text-stone-600">
                        Released {{ phone.release_date }}
                    </span>
                </div>

                <Link
                    :href="`/compare?phones=${phone.slug}`"
                    class="mt-5 inline-flex items-center justify-center rounded-full border border-stone-200 px-5 py-2.5 text-sm font-semibold text-stone-700 hover:border-emerald-300 hover:text-emerald-700"
                >
                    Add to compare
                </Link>
            </div>
        </div>

        <p v-if="phone.summary" class="mt-6 max-w-2xl leading-relaxed text-stone-600">{{ phone.summary }}</p>

        <!-- Phone statistics -->
        <div class="mt-10">
            <PhoneStatistics :profile="performanceProfile" />
        </div>

        <!-- Pros / considerations -->
        <div v-if="highlights.pros.length || highlights.considerations.length" class="mt-10 grid gap-6 sm:grid-cols-2">
            <div v-if="highlights.pros.length" class="rounded-2xl border border-stone-200 bg-white p-5">
                <h2 class="text-sm font-semibold text-stone-900">Strengths</h2>
                <ul class="mt-3 space-y-2">
                    <li v-for="pro in highlights.pros" :key="pro" class="flex gap-2 text-sm text-stone-600">
                        <span class="mt-0.5 text-emerald-600" aria-hidden="true">✓</span>
                        <span>{{ pro }}</span>
                    </li>
                </ul>
            </div>
            <div v-if="highlights.considerations.length" class="rounded-2xl border border-stone-200 bg-white p-5">
                <h2 class="text-sm font-semibold text-stone-900">Worth considering</h2>
                <ul class="mt-3 space-y-2">
                    <li v-for="item in highlights.considerations" :key="item" class="flex gap-2 text-sm text-stone-500">
                        <span class="mt-0.5" aria-hidden="true">•</span>
                        <span>{{ item }}</span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Variants / availability, grouped by Global/Chinese version -->
        <div v-if="phone.variant_regions.length" class="mt-10">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-lg font-semibold text-stone-900">Prices & availability</h2>

                <!-- Only shown when the phone genuinely has both a Global
                     and a Chinese version - a single-version phone (the
                     vast majority of the catalogue today) just shows its
                     one version as plain text below, never a useless
                     one-option selector. -->
                <div v-if="phone.variant_regions.length > 1" class="inline-flex rounded-full border border-stone-200 bg-stone-50 p-1">
                    <button
                        v-for="group in phone.variant_regions"
                        :key="group.region"
                        type="button"
                        class="rounded-full px-4 py-1.5 text-sm font-semibold transition-colors"
                        :class="activeRegion === group.region ? 'bg-emerald-700 text-white' : 'text-stone-600 hover:text-emerald-700'"
                        @click="activeRegion = group.region"
                    >
                        {{ group.region }}
                    </button>
                </div>
                <span v-else class="rounded-full bg-stone-100 px-3 py-1 text-xs font-semibold text-stone-600">
                    {{ phone.variant_regions[0].region }}
                </span>
            </div>

            <div class="mt-4 overflow-x-auto rounded-2xl border border-stone-200 bg-white">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-stone-100 text-stone-400">
                        <tr>
                            <th scope="col" class="px-4 py-3 font-medium">Configuration</th>
                            <th scope="col" class="px-4 py-3 font-medium">Price</th>
                            <th scope="col" class="px-4 py-3 font-medium">Store</th>
                            <th scope="col" class="px-4 py-3 font-medium">Warranty</th>
                            <th scope="col" class="px-4 py-3 font-medium">Availability</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        <template v-for="variant in activeRegionVariants" :key="variant.id">
                            <tr v-for="(price, index) in variant.prices" :key="`${variant.id}-${index}`">
                                <td class="px-4 py-3 font-medium text-stone-800">{{ variant.config_label }}</td>
                                <td class="px-4 py-3">
                                    <span class="font-semibold text-stone-900">{{ formatTaka(price.amount) }}</span>
                                    <span
                                        class="ml-2 rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="price.is_official ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'"
                                    >
                                        {{ price.type }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-stone-500">{{ price.store ?? '—' }}</td>
                                <td class="px-4 py-3 text-stone-500">{{ price.warranty_type ?? '—' }}</td>
                                <td class="px-4 py-3 text-stone-500">{{ variant.availability ?? '—' }}</td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Specifications -->
        <div v-if="specGroups.length" class="mt-10">
            <h2 class="text-lg font-semibold text-stone-900">Full specifications</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div v-for="group in specGroups" :key="group.title" class="rounded-2xl border border-stone-200 bg-white p-5">
                    <h3 class="text-sm font-semibold text-stone-900">{{ group.title }}</h3>
                    <dl class="mt-3 space-y-2">
                        <div v-for="[label, value] in group.rows" :key="label" class="flex justify-between gap-4 text-sm">
                            <dt class="text-stone-400">{{ label }}</dt>
                            <dd class="text-right text-stone-700">{{ value }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>

        <!-- Related phones -->
        <div v-if="related.length" class="mt-10">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-stone-900">More from {{ phone.brand }}</h2>
                <Link :href="`/phones/brand/${phone.brand_slug}`" class="text-sm font-semibold text-emerald-700 hover:text-emerald-800">
                    See all {{ phone.brand }} phones →
                </Link>
            </div>
            <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Link
                    v-for="item in related"
                    :key="item.slug"
                    :href="`/phones/${item.slug}`"
                    class="rounded-2xl border border-stone-200 bg-white p-4 transition-colors hover:border-emerald-300"
                >
                    <PhoneImage :src="item.image_url" :label="item.name" size="sm" />
                    <p class="mt-3 truncate text-sm font-semibold text-stone-900">{{ item.name }}</p>
                    <p class="text-sm font-semibold text-emerald-700">{{ formatTaka(item.price) }}</p>
                </Link>
            </div>
        </div>
    </div>
</template>
