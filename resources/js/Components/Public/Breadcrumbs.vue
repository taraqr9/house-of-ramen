<script setup>
// Renders the visible breadcrumb trail AND its BreadcrumbList JSON-LD in one
// place so the two can never drift apart. `items` is {label, href} in order
// from the site root, including the current page as the last entry with
// href left null (it isn't a link, matching Google's BreadcrumbList example).
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { jsonLdVNode } from '../../utils/jsonLd';

const props = defineProps({
    items: { type: Array, required: true },
});

const page = usePage();

const jsonLd = computed(() => ({
    '@context': 'https://schema.org',
    '@type': 'BreadcrumbList',
    itemListElement: props.items.map((item, index) => ({
        '@type': 'ListItem',
        position: index + 1,
        name: item.label,
        item: item.href ? `${page.props.siteMeta?.base_url ?? ''}${item.href}` : undefined,
    })),
}));
</script>

<template>
    <Head>
        <component :is="jsonLdVNode(jsonLd, 'json-ld-breadcrumb')" />
    </Head>

    <nav class="text-sm text-stone-400" aria-label="Breadcrumb">
        <template v-for="(item, index) in items" :key="item.label">
            <Link v-if="item.href" :href="item.href" class="hover:text-emerald-700">{{ item.label }}</Link>
            <span v-else class="text-stone-600">{{ item.label }}</span>
            <span v-if="index < items.length - 1" class="mx-1.5" aria-hidden="true">/</span>
        </template>
    </nav>
</template>
