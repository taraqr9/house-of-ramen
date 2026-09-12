<script setup>
defineProps({
    label: { type: String, default: '' },
    size: { type: String, default: 'md' }, // sm | md | lg
});

const sizes = {
    sm: 'h-14 w-14',
    md: 'h-20 w-20',
    lg: 'h-32 w-32',
};
</script>

<template>
    <!--
        No real product photography exists in the catalogue yet, so this
        is an honest placeholder (a device glyph, not a stock photo
        pretending to be the phone) rather than invented imagery.
    -->
    <div
        class="flex shrink-0 items-center justify-center rounded-2xl bg-emerald-50"
        :class="sizes[size] || sizes.md"
        role="img"
        :aria-label="label ? `${label} — image not yet available` : 'Phone image not yet available'"
    >
        <svg viewBox="0 0 24 24" fill="none" class="h-1/2 w-1/2 text-emerald-600" aria-hidden="true">
            <rect x="6" y="2" width="12" height="20" rx="2.5" stroke="currentColor" stroke-width="1.6" />
            <path d="M10 19h4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
        </svg>
    </div>
</template>
