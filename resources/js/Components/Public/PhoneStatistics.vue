<script setup>
import StarRating from './StarRating.vue';

defineProps({
    profile: { type: Array, default: null },
});
</script>

<template>
    <div v-if="profile && profile.length" class="rounded-2xl border border-stone-200 bg-white p-5 sm:p-6">
        <h2 class="text-lg font-semibold text-stone-900">Phone statistics</h2>
        <p class="mt-1 text-sm text-stone-500">
            How this phone scores on each dimension, from the same rules Phone Kinbo uses to recommend phones.
        </p>

        <dl class="mt-5 space-y-4">
            <div v-for="dim in profile" :key="dim.key" class="flex items-center gap-4">
                <dt class="w-24 shrink-0 text-sm font-medium text-stone-700 sm:w-28">{{ dim.label }}</dt>
                <dd class="min-w-0 flex-1">
                    <StarRating :stars="dim.stars" :score="dim.score" :label="dim.label" :id-prefix="`stat-${dim.key}`" />
                </dd>
            </div>
        </dl>
    </div>

    <div v-else class="rounded-2xl border border-stone-200 bg-white p-5 text-sm text-stone-500 sm:p-6">
        Not enough data yet to show phone statistics for this phone.
    </div>
</template>
