<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    images: { type: Array, default: () => [] }, // list of url strings
    startIndex: { type: Number, default: 0 },
    title: { type: String, default: '' },
});

const emit = defineEmits(['close']);

const index = ref(props.startIndex);
let touchStartX = null;

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            index.value = props.startIndex;
            document.addEventListener('keydown', onKeydown);
            document.body.style.overflow = 'hidden';
        } else {
            document.removeEventListener('keydown', onKeydown);
            document.body.style.overflow = '';
        }
    },
);

onBeforeUnmount(() => {
    document.removeEventListener('keydown', onKeydown);
    document.body.style.overflow = '';
});

const current = computed(() => props.images[index.value]);

function close() {
    emit('close');
}

function next() {
    index.value = (index.value + 1) % props.images.length;
}

function prev() {
    index.value = (index.value - 1 + props.images.length) % props.images.length;
}

function onKeydown(event) {
    if (event.key === 'Escape') close();
    if (event.key === 'ArrowRight') next();
    if (event.key === 'ArrowLeft') prev();
}

function onTouchStart(event) {
    touchStartX = event.touches[0].clientX;
}

function onTouchEnd(event) {
    if (touchStartX === null) return;

    const delta = event.changedTouches[0].clientX - touchStartX;
    if (Math.abs(delta) > 40) {
        delta > 0 ? prev() : next();
    }
    touchStartX = null;
}
</script>

<template>
    <div
        v-if="open"
        class="fixed inset-0 z-50 flex items-center justify-center bg-charcoal-900/90 p-4"
        role="dialog"
        aria-modal="true"
        :aria-label="title || 'Image viewer'"
        @click.self="close"
        @touchstart.passive="onTouchStart"
        @touchend.passive="onTouchEnd"
    >
        <button
            type="button"
            class="absolute top-4 right-4 flex h-11 w-11 items-center justify-center rounded-full bg-white/10 text-white hover:bg-white/20"
            aria-label="Close"
            @click="close"
        >
            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
        </button>

        <button
            v-if="images.length > 1"
            type="button"
            class="absolute top-1/2 left-2 flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full bg-white/10 text-white hover:bg-white/20 sm:left-4"
            aria-label="Previous image"
            @click="prev"
        >
            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
        </button>

        <img :src="current" :alt="title" class="max-h-[85vh] max-w-full rounded-lg object-contain shadow-2xl" />

        <button
            v-if="images.length > 1"
            type="button"
            class="absolute top-1/2 right-2 flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full bg-white/10 text-white hover:bg-white/20 sm:right-4"
            aria-label="Next image"
            @click="next"
        >
            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
        </button>

        <p v-if="images.length > 1" class="absolute bottom-4 left-1/2 -translate-x-1/2 text-sm text-white/70">
            {{ index + 1 }} / {{ images.length }}
        </p>
    </div>
</template>
