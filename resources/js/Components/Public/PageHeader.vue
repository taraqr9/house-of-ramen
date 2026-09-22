<script setup>
import Breadcrumbs from './Breadcrumbs.vue';

defineProps({
    eyebrow: { type: String, default: '' },
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    // Real restaurant photo only - falls back to a plain brand-colored
    // gradient (never a stock image) when a page has none available yet.
    backgroundImage: { type: String, default: null },
    breadcrumbs: { type: Array, default: () => [] },
});
</script>

<template>
    <section class="relative isolate overflow-hidden bg-charcoal-900">
        <img
            v-if="backgroundImage"
            :src="backgroundImage"
            alt=""
            aria-hidden="true"
            class="absolute inset-0 h-full w-full object-cover"
            loading="eager"
        />
        <div
            class="absolute inset-0"
            :class="backgroundImage
                ? 'bg-gradient-to-t from-charcoal-900 via-charcoal-900/75 to-charcoal-900/55'
                : 'bg-gradient-to-br from-charcoal-900 via-charcoal-900 to-coral-700/40'"
            aria-hidden="true"
        />

        <div class="relative mx-auto max-w-6xl px-4 py-16 text-center sm:px-6 sm:py-20 lg:py-24">
            <Breadcrumbs v-if="breadcrumbs.length" :items="breadcrumbs" variant="dark" class="mb-5 justify-center hidden sm:flex" />

            <p v-if="eyebrow" class="text-sm font-semibold tracking-wide text-coral-100 uppercase">{{ eyebrow }}</p>
            <h1 class="mt-2 text-3xl leading-tight font-extrabold text-white sm:text-4xl lg:text-5xl">{{ title }}</h1>
            <span class="mx-auto mt-4 block h-1 w-14 rounded-full bg-coral-500" aria-hidden="true" />
            <p v-if="subtitle" class="mx-auto mt-4 max-w-xl text-base leading-relaxed text-cream-100/70 sm:text-lg">
                {{ subtitle }}
            </p>
        </div>
    </section>
</template>
