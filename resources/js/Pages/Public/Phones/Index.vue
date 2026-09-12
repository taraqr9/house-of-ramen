<script setup>
import { Link, router } from '@inertiajs/vue3';
import PublicLayout from '../../../Layouts/PublicLayout.vue';
import PhoneImage from '../../../Components/Public/PhoneImage.vue';
import SeoHead from '../../../Components/Public/SeoHead.vue';
import { formatTaka } from '../../../utils/format';
import { trackEvent } from '../../../utils/analytics';
import { budgetRangeFor } from '../../../utils/budget';

defineOptions({ layout: PublicLayout });

const props = defineProps({
    phones: { type: Object, required: true },
    brands: { type: Array, default: () => [] },
    priceBrackets: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    seo: { type: Object, required: true },
});

function updateFilters(partial) {
    if ('max_budget' in partial) {
        const { budget_min, budget_max, budget_range } = budgetRangeFor(partial.max_budget, props.priceBrackets);
        trackEvent('budget_selected', { budget_min, budget_max, budget_range, source: 'phones_index' });
    }

    if ('brand' in partial) {
        trackEvent('filter_applied', { filter_type: 'brand', value: partial.brand ?? 'all' });
    }

    if ('sort' in partial) {
        trackEvent('sort_changed', { sort: partial.sort });
    }

    // Each filter change pushes its own history entry (no `replace`) so the
    // browser Back/Forward buttons move between filter selections, not
    // straight out of the page - a discrete button click is a new,
    // back-navigable state, unlike e.g. a search box's every-keystroke updates.
    router.get('/phones', { ...props.filters, ...partial }, { preserveState: true, preserveScroll: true });
}
</script>

<template>
    <SeoHead :seo="seo" />

    <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6 sm:py-14">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-stone-900 sm:text-3xl">Browse phones</h1>
                <p class="mt-1 text-stone-600">{{ phones.total }} phones tracked in Bangladesh right now.</p>
            </div>

            <Link
                href="/#find-my-phone"
                class="inline-flex items-center justify-center rounded-full bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-800"
            >
                Not sure? Find My Phone
            </Link>
        </div>

        <div class="mt-6 flex flex-wrap items-center gap-2">
            <button
                v-for="bracket in priceBrackets"
                :key="bracket.label"
                type="button"
                class="rounded-full border px-4 py-2 text-sm font-semibold transition-colors focus:outline-none focus:ring-1 focus:ring-emerald-500"
                :aria-pressed="filters.max_budget === bracket.max_budget"
                :class="filters.max_budget === bracket.max_budget
                    ? 'border-emerald-700 bg-emerald-700 text-white'
                    : 'border-stone-200 bg-white text-stone-600 hover:border-emerald-300'"
                @click="updateFilters({ max_budget: bracket.max_budget })"
            >
                {{ bracket.label }}
            </button>

            <span class="mx-1 hidden h-6 w-px bg-stone-200 sm:inline-block" aria-hidden="true" />

            <label class="sr-only" for="brand-filter">Filter by brand</label>
            <div class="relative">
                <select
                    id="brand-filter"
                    class="cursor-pointer appearance-none rounded-full border border-stone-200 bg-white py-2 pr-9 pl-4 text-sm font-medium text-stone-700 shadow-sm transition-colors hover:border-emerald-300 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 focus:outline-none"
                    :value="filters.brand ?? ''"
                    @change="updateFilters({ brand: $event.target.value || null })"
                >
                    <option value="">All brands</option>
                    <option v-for="brand in brands" :key="brand.slug" :value="brand.slug">{{ brand.name }}</option>
                </select>
                <svg
                    class="pointer-events-none absolute top-1/2 right-3.5 h-3.5 w-3.5 -translate-y-1/2 text-stone-400"
                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6" />
                </svg>
            </div>

            <label class="sr-only" for="sort-filter">Sort</label>
            <div class="relative">
                <select
                    id="sort-filter"
                    class="cursor-pointer appearance-none rounded-full border border-stone-200 bg-white py-2 pr-9 pl-4 text-sm font-medium text-stone-700 shadow-sm transition-colors hover:border-emerald-300 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 focus:outline-none"
                    :value="filters.sort ?? 'newest'"
                    @change="updateFilters({ sort: $event.target.value })"
                >
                    <option value="newest">Newest first</option>
                    <option value="price_asc">Price: low to high</option>
                    <option value="price_desc">Price: high to low</option>
                </select>
                <svg
                    class="pointer-events-none absolute top-1/2 right-3.5 h-3.5 w-3.5 -translate-y-1/2 text-stone-400"
                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6" />
                </svg>
            </div>
        </div>

        <div v-if="phones.data.length" class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <Link
                v-for="phone in phones.data"
                :key="phone.id"
                :href="`/phones/${phone.slug}`"
                class="flex items-center gap-4 rounded-2xl border border-stone-200 bg-white p-4 transition-colors hover:border-emerald-300"
            >
                <PhoneImage :src="phone.image_url" :label="phone.name" size="sm" />
                <div class="min-w-0">
                    <p class="text-xs font-medium text-stone-400">{{ phone.brand }}</p>
                    <p class="truncate text-[15px] font-semibold text-stone-900">{{ phone.name }}</p>
                    <p v-if="phone.price !== null" class="mt-0.5 font-semibold text-emerald-700">
                        {{ formatTaka(phone.price) }}
                        <span class="font-normal text-stone-400">({{ phone.is_official ? 'Official' : 'Unofficial' }})</span>
                    </p>
                    <p v-else class="mt-0.5 text-sm text-stone-400">Price unavailable</p>
                    <p v-if="phone.region" class="mt-0.5 text-xs font-medium text-stone-400">{{ phone.region }}</p>
                </div>
            </Link>
        </div>

        <div v-else class="mt-10 rounded-2xl border border-stone-200 bg-white p-8 text-center text-stone-600">
            No phones match these filters right now.
        </div>

        <nav v-if="phones.links.length > 3" class="mt-10 flex flex-wrap justify-center gap-1" aria-label="Pagination">
            <Link
                v-for="(link, index) in phones.links"
                :key="index"
                :href="link.url ?? '#'"
                v-html="link.label"
                class="min-w-10 rounded-lg px-3 py-2 text-center text-sm font-medium"
                :class="[
                    link.active ? 'bg-emerald-700 text-white' : 'text-stone-600 hover:bg-stone-100',
                    !link.url && 'pointer-events-none opacity-40',
                ]"
                preserve-scroll
            />
        </nav>
    </div>
</template>
