<script setup>
import { computed, onBeforeUnmount, watch } from 'vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    embedUrl: { type: String, default: null },
    title: { type: String, default: '' },
    // 'youtube' | 'facebook' | 'instagram' | null - changes the player's
    // box shape below, since forcing every platform into the same 16:9
    // frame crops/zooms Facebook and Instagram clips, which are commonly
    // shot vertically (unlike YouTube, whose own player always letterboxes
    // a portrait video safely inside a 16:9 iframe on its own).
    platform: { type: String, default: null },
});

const emit = defineEmits(['close']);

// Facebook's and Instagram's embeds fill whatever box they're given
// rather than letterboxing a mismatched source video the way YouTube's
// player does, so a portrait clip forced into a landscape 16:9 frame
// gets scaled/cropped to fill it - hence the "zoomed in" look. A taller,
// portrait-friendly frame lets those clips show at their own size
// instead of being stretched to fit.
const frameClass = computed(() => {
    if (props.platform === 'facebook' || props.platform === 'instagram') {
        return 'aspect-[9/16] max-h-[85vh] w-auto max-w-sm sm:max-w-md';
    }

    return 'aspect-video w-full max-w-3xl';
});

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
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

function close() {
    emit('close');
}

function onKeydown(event) {
    if (event.key === 'Escape') close();
}
</script>

<template>
    <div
        v-if="open"
        class="fixed inset-0 z-50 flex items-center justify-center bg-charcoal-900/90 p-4"
        role="dialog"
        aria-modal="true"
        :aria-label="title || 'Video player'"
        @click.self="close"
    >
        <button
            type="button"
            class="absolute top-4 right-4 flex h-11 w-11 items-center justify-center rounded-full bg-white/10 text-white hover:bg-white/20"
            aria-label="Close"
            @click="close"
        >
            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
        </button>

        <div class="overflow-hidden rounded-lg bg-black shadow-2xl" :class="frameClass">
            <!-- embedUrl already comes fully formed (including autoplay
                 params where the platform supports them) from
                 RestaurantPresenter::videoFeature() - it differs per
                 platform (YouTube/Facebook/Instagram), so nothing is
                 appended here. -->
            <iframe
                v-if="open && embedUrl"
                :src="embedUrl"
                :title="title || 'Video player'"
                class="h-full w-full"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                allowfullscreen
            ></iframe>
        </div>
    </div>
</template>
