<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import PublicLayout from '../../../Layouts/PublicLayout.vue';
import PhoneImage from '../../../Components/Public/PhoneImage.vue';
import MarketPriceSummary from '../../../Components/Public/MarketPriceSummary.vue';
import SeoHead from '../../../Components/Public/SeoHead.vue';
import Breadcrumbs from '../../../Components/Public/Breadcrumbs.vue';
import { jsonLdVNode } from '../../../utils/jsonLd';
import { formatTaka } from '../../../utils/format';

defineOptions({ layout: PublicLayout });

const props = defineProps({
    heading: { type: String, required: true },
    isBudgetOnly: { type: Boolean, required: true },
    categoryLabel: { type: String, default: '' },
    maxBudget: { type: Number, default: null },
    totalEligible: { type: Number, required: true },
    results: { type: Array, required: true },
    breadcrumbs: { type: Array, required: true },
    relatedPages: { type: Array, default: () => [] },
    seo: { type: Object, required: true },
});

const page = usePage();

// A list/category page describes ranked items, not one product - ItemList
// is the correct schema type here (see prompt section 8: "Do not use
// Product schema incorrectly for a page that is actually a list").
const itemListJsonLd = computed(() => ({
    '@context': 'https://schema.org',
    '@type': 'ItemList',
    name: props.heading,
    itemListElement: props.results.map((result, index) => ({
        '@type': 'ListItem',
        position: index + 1,
        url: `${page.props.siteMeta?.base_url ?? ''}/phones/${result.phone_slug}`,
        name: result.phone_name,
    })),
}));
</script>

<template>
    <SeoHead :seo="seo" />
    <Head>
        <component :is="jsonLdVNode(itemListJsonLd, 'json-ld-itemlist')" />
    </Head>

    <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6 sm:py-14">
        <Breadcrumbs :items="breadcrumbs" />

        <div class="mt-4 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-stone-900 sm:text-3xl">{{ heading }}</h1>
                <p class="mt-1 text-stone-600">
                    {{ totalEligible }} {{ totalEligible === 1 ? 'phone' : 'phones' }}
                    <template v-if="maxBudget"> under {{ formatTaka(maxBudget) }}</template>
                    tracked in Bangladesh right now{{ isBudgetOnly ? '' : `, ranked for ${categoryLabel} performance` }}.
                </p>
            </div>

            <Link
                href="/#find-my-phone"
                class="inline-flex items-center justify-center rounded-full bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-800"
            >
                Get a personalised match
            </Link>
        </div>

        <div v-if="results.length" class="mt-8 grid gap-5 sm:grid-cols-2">
            <article
                v-for="(result, index) in results"
                :key="result.phone_slug"
                class="flex flex-col gap-3 rounded-2xl border p-5"
                :class="index === 0 ? 'border-emerald-200 shadow-sm ring-1 ring-emerald-100' : 'border-stone-200 bg-white'"
            >
                <div class="flex items-start gap-4">
                    <PhoneImage :src="result.image_url" :label="result.phone_name" size="md" />
                    <div class="min-w-0 flex-1">
                        <p v-if="index === 0" class="text-xs font-semibold tracking-wide text-emerald-700 uppercase">Top pick</p>
                        <p class="text-xs font-medium text-stone-400">{{ result.brand }}</p>
                        <Link :href="`/phones/${result.phone_slug}`" class="mt-0.5 block text-lg font-semibold text-stone-900 hover:text-emerald-700">
                            {{ result.phone_name }}
                        </Link>
                        <div class="mt-1 flex flex-wrap gap-1.5">
                            <span
                                v-for="strength in result.strengths"
                                :key="strength"
                                class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700"
                            >
                                Strong {{ strength.toLowerCase() }}
                            </span>
                        </div>
                    </div>
                    <div class="flex shrink-0 flex-col items-center">
                        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-stone-100 text-sm font-bold text-stone-700">
                            {{ Math.round(result.match_score) }}%
                        </div>
                        <span class="mt-1 text-[10px] text-stone-400">match</span>
                    </div>
                </div>

                <MarketPriceSummary :market="result.market" stacked />

                <Link
                    :href="`/phones/${result.phone_slug}`"
                    class="mt-1 inline-flex items-center justify-center rounded-full border border-stone-200 px-4 py-2.5 text-sm font-semibold text-stone-700 transition-colors hover:border-emerald-300 hover:text-emerald-700"
                >
                    View full specs & prices
                </Link>
            </article>
        </div>

        <div v-else class="mt-10 rounded-2xl border border-stone-200 bg-white p-8 text-center text-stone-600">
            No phones currently match this budget. Try
            <Link href="/#find-my-phone" class="font-semibold text-emerald-700 hover:text-emerald-800">Find My Phone</Link>
            for a personalised match, or
            <Link href="/phones" class="font-semibold text-emerald-700 hover:text-emerald-800">browse all phones</Link>.
        </div>

        <!-- Cross-links to sibling landing pages - a real, bounded internal
             link graph (5 pages), not an excessive one. -->
        <div v-if="relatedPages.length" class="mt-12 border-t border-stone-200 pt-8">
            <h2 class="text-sm font-semibold text-stone-900">Looking for something else?</h2>
            <div class="mt-3 flex flex-wrap gap-2">
                <Link
                    v-for="related in relatedPages"
                    :key="related.slug"
                    :href="`/${related.slug}`"
                    class="rounded-full border border-stone-200 bg-white px-4 py-2 text-sm font-medium text-stone-600 hover:border-emerald-300 hover:text-emerald-700"
                >
                    {{ related.heading }}
                </Link>
                <Link
                    href="/phones"
                    class="rounded-full border border-stone-200 bg-white px-4 py-2 text-sm font-medium text-stone-600 hover:border-emerald-300 hover:text-emerald-700"
                >
                    Browse all phones
                </Link>
            </div>
        </div>
    </div>
</template>
