<script setup>
import { ref, watch } from 'vue';
import PhoneImagePlaceholder from './PhoneImagePlaceholder.vue';

const props = defineProps({
    src: { type: String, default: null },
    label: { type: String, default: '' },
    size: { type: String, default: 'md' }, // sm | md | lg
});

const sizes = {
    sm: 'h-14 w-14',
    md: 'h-20 w-20',
    lg: 'h-32 w-32',
};

// A stored image can still 404 (disk moved, file cleaned up) - fall back
// to the same honest placeholder as "no image collected yet" rather than
// showing a broken-image icon.
const failed = ref(false);
watch(() => props.src, () => { failed.value = false; });
</script>

<template>
    <div
        v-if="src && !failed"
        class="flex shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-stone-50"
        :class="sizes[size] || sizes.md"
    >
        <img
            :src="src"
            :alt="label"
            class="h-full w-full object-contain"
            loading="lazy"
            @error="failed = true"
        >
    </div>
    <PhoneImagePlaceholder v-else :label="label" :size="size" />
</template>
