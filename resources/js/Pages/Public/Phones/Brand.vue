<script setup>
import { onMounted } from 'vue';
import { Link } from '@inertiajs/vue3';
import PublicLayout from '../../../Layouts/PublicLayout.vue';
import PhoneImage from '../../../Components/Public/PhoneImage.vue';
import SeoHead from '../../../Components/Public/SeoHead.vue';
import Breadcrumbs from '../../../Components/Public/Breadcrumbs.vue';
import { formatTaka } from '../../../utils/format';
import { trackEvent } from '../../../utils/analytics';

defineOptions({ layout: PublicLayout });

const props = defineProps({
    brand: { type: Object, required: true },
    stats: { type: Object, required: true },
    phones: { type: Array, required: true },
    breadcrumbs: { type: Array, required: true },
    seo: { type: Object, required: true },
});

onMounted(() => {
    trackEvent('brand_viewed', { brand: props.brand.name, phone_count: props.stats.phone_count });
});
</script>

<template>
    <SeoHead :seo="seo" />

    <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6 sm:py-14">
        <Breadcrumbs :items="breadcrumbs" />

        <div class="mt-4 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-stone-900 sm:text-3xl">{{ brand.name }} phones in Bangladesh</h1>
                <p class="mt-1 text-stone-600">
                    {{ stats.phone_count }} {{ brand.name }} {{ stats.phone_count === 1 ? 'phone' : 'phones' }} tracked right now.
                </p>
            </div>

            <Link
                href="/#find-my-phone"
                class="inline-flex items-center justify-center rounded-full bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-800"
            >
                Not sure? Find My Phone
            </Link>
        </div>

        <!-- Real aggregate stats, not filler copy -->
        <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-2xl border border-stone-200 bg-white p-4">
                <p class="text-xs font-medium text-stone-400">Price range</p>
                <p class="mt-1 text-sm font-semibold text-stone-900">
                    <template v-if="stats.min_price !== null">{{ formatTaka(stats.min_price) }} – {{ formatTaka(stats.max_price) }}</template>
                    <template v-else>—</template>
                </p>
            </div>
            <div class="rounded-2xl border border-stone-200 bg-white p-4">
                <p class="text-xs font-medium text-stone-400">Phones tracked</p>
                <p class="mt-1 text-sm font-semibold text-stone-900">{{ stats.phone_count }}</p>
            </div>
            <div class="rounded-2xl border border-stone-200 bg-white p-4">
                <p class="text-xs font-medium text-stone-400">Official BD availability</p>
                <p class="mt-1 text-sm font-semibold text-stone-900">{{ stats.official_count }} phones</p>
            </div>
            <div class="rounded-2xl border border-stone-200 bg-white p-4">
                <p class="text-xs font-medium text-stone-400">Unofficial market</p>
                <p class="mt-1 text-sm font-semibold text-stone-900">{{ stats.unofficial_count }} phones</p>
            </div>
        </div>

        <div v-if="phones.length" class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <Link
                v-for="phone in phones"
                :key="phone.slug"
                :href="`/phones/${phone.slug}`"
                class="flex items-center gap-4 rounded-2xl border border-stone-200 bg-white p-4 transition-colors hover:border-emerald-300"
            >
                <PhoneImage :src="phone.image_url" :label="phone.name" size="sm" />
                <div class="min-w-0">
                    <p class="truncate text-[15px] font-semibold text-stone-900">{{ phone.name }}</p>
                    <p class="mt-0.5 font-semibold text-emerald-700">{{ formatTaka(phone.price) }}</p>
                </div>
            </Link>
        </div>

        <p class="mt-10 text-sm text-stone-400">
            Looking for something else?
            <Link href="/phones" class="font-semibold text-emerald-700 hover:text-emerald-800">Browse all phones</Link>
            or
            <Link href="/#find-my-phone" class="font-semibold text-emerald-700 hover:text-emerald-800">get a personalised match</Link>.
        </p>
    </div>
</template>
