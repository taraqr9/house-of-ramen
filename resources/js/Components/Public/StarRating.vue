<script setup>
const props = defineProps({
    stars: { type: Number, required: true }, // 0-5, half-star resolution
    score: { type: Number, default: null }, // 0-100, optional - drives the thin progress bar
    label: { type: String, required: true }, // used only for the aria-label, e.g. "Performance"
    // Unique per rendered instance - a page comparing several phones
    // renders many of these side by side, and each half-star's SVG
    // gradient needs an id that doesn't collide with any other.
    idPrefix: { type: String, required: true },
    showBar: { type: Boolean, default: true },
});

function starFill(slot) {
    if (props.stars >= slot) return 'full';
    if (props.stars >= slot - 0.5) return 'half';

    return 'empty';
}
</script>

<template>
    <span class="flex items-center gap-3">
        <span class="flex items-center gap-0.5" role="img" :aria-label="`${label}: ${stars} out of 5`">
            <svg
                v-for="slot in 5" :key="slot"
                class="h-4 w-4 shrink-0 sm:h-5 sm:w-5"
                :class="starFill(slot) === 'empty' ? 'text-stone-200' : 'text-amber-400'"
                viewBox="0 0 20 20"
                aria-hidden="true"
            >
                <defs v-if="starFill(slot) === 'half'">
                    <linearGradient :id="`${idPrefix}-${slot}`">
                        <stop offset="50%" stop-color="currentColor" />
                        <stop offset="50%" stop-color="transparent" />
                    </linearGradient>
                </defs>
                <path
                    :fill="starFill(slot) === 'half' ? `url(#${idPrefix}-${slot})` : (starFill(slot) === 'full' ? 'currentColor' : 'none')"
                    stroke="currentColor" stroke-width="1"
                    d="M10 1.5l2.6 5.27 5.82.85-4.21 4.1.99 5.79L10 14.9l-5.2 2.61.99-5.79-4.21-4.1 5.82-.85z"
                />
            </svg>
        </span>

        <span class="shrink-0 text-sm font-semibold text-stone-900">{{ stars }}/5</span>

        <span v-if="showBar && score !== null" class="hidden h-1.5 min-w-0 flex-1 overflow-hidden rounded-full bg-stone-100 sm:block">
            <span class="block h-full rounded-full bg-emerald-600" :style="{ width: `${score}%` }" />
        </span>
    </span>
</template>
