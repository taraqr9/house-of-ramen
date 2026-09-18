<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    offers: { type: Array, default: () => [] }, // [{ id, image_url }]
});

// Deliberately no sessionStorage/localStorage dismissal memory - every
// fresh Home page load (including a plain browser reload) should show
// the popup again. Closing it only hides it for the current page view.
const isOpen = ref(false);
const active = ref(0);
let autoplayTimer = null;
let touchStartX = null;

// Only the current slide is ever in the DOM (no fixed-size letterbox
// strip to hold every slide at once) - so the image always renders at
// its own natural size, up to the max-height/max-width in the template,
// with nothing else showing around it.
const currentOffer = computed(() => props.offers[active.value] ?? null);

function goTo(index) {
    active.value = (index + props.offers.length) % props.offers.length;
}

// Manual navigation resets the autoplay countdown rather than fighting
// it, so a click right before the timer would have fired doesn't cause
// a double-advance.
function goToManually(index) {
    goTo(index);
    restartAutoplay();
}

function next() {
    goTo(active.value + 1);
}

function prev() {
    goToManually(active.value - 1);
}

function nextManually() {
    goToManually(active.value + 1);
}

function restartAutoplay() {
    stopAutoplay();
    if (props.offers.length > 1) {
        autoplayTimer = setInterval(next, 2500);
    }
}

function stopAutoplay() {
    if (autoplayTimer) {
        clearInterval(autoplayTimer);
        autoplayTimer = null;
    }
}

function onKeydown(event) {
    if (event.key === 'Escape') close();
    if (event.key === 'ArrowRight') nextManually();
    if (event.key === 'ArrowLeft') prev();
}

function open() {
    isOpen.value = true;
    document.addEventListener('keydown', onKeydown);
    document.body.style.overflow = 'hidden';
    restartAutoplay();
}

function close() {
    isOpen.value = false;
    document.removeEventListener('keydown', onKeydown);
    document.body.style.overflow = '';
    stopAutoplay();
}

function onTouchStart(event) {
    touchStartX = event.touches[0].clientX;
}

function onTouchEnd(event) {
    if (touchStartX === null) return;

    const delta = event.changedTouches[0].clientX - touchStartX;
    if (Math.abs(delta) > 40) {
        delta > 0 ? prev() : nextManually();
    }
    touchStartX = null;
}

onMounted(() => {
    if (props.offers.length) {
        open();
    }
});

onBeforeUnmount(() => {
    document.removeEventListener('keydown', onKeydown);
    document.body.style.overflow = '';
    stopAutoplay();
});
</script>

<template>
    <div
        v-if="isOpen"
        class="fixed inset-0 z-[60] flex items-center justify-center bg-charcoal-900/80 p-4 backdrop-blur-sm"
        role="dialog"
        aria-modal="true"
        aria-label="Special offer"
        @click.self="close"
    >
        <!-- Pinned to the backdrop's corner, not the image wrapper below,
             so it stays put in the same spot on screen even though the
             image (and therefore its wrapper) changes size between
             slides of different aspect ratios. -->
        <button
            type="button"
            class="fixed top-4 right-4 z-20 flex h-10 w-10 items-center justify-center rounded-full bg-charcoal-900/60 text-white backdrop-blur transition hover:bg-charcoal-900/80"
            aria-label="Close"
            @click="close"
        >
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
        </button>

        <!-- Also pinned to the backdrop, not the image wrapper, for the
             same reason as the close button above - otherwise these jump
             to a different spot each time the active image is a
             different size. -->
        <template v-if="offers.length > 1">
            <button
                type="button"
                class="fixed top-1/2 left-2 z-20 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-white/85 text-charcoal-900 shadow transition hover:bg-white sm:left-4"
                aria-label="Previous offer"
                @click="prev"
            >
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
            </button>
            <button
                type="button"
                class="fixed top-1/2 right-2 z-20 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-white/85 text-charcoal-900 shadow transition hover:bg-white sm:right-4"
                aria-label="Next offer"
                @click="nextManually"
            >
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
            </button>
        </template>

        <div
            class="relative inline-block max-h-[88vh] max-w-[92vw]"
            @touchstart.passive="onTouchStart"
            @touchend.passive="onTouchEnd"
        >
            <Transition name="fade" mode="out-in">
                <img
                    v-if="currentOffer"
                    :key="currentOffer.id"
                    :src="currentOffer.image_url"
                    alt="Special offer"
                    loading="eager"
                    class="block max-h-[88vh] max-w-[92vw] w-auto rounded-2xl shadow-2xl"
                />
            </Transition>

            <div v-if="offers.length > 1" class="absolute bottom-3 left-1/2 flex -translate-x-1/2 gap-2" role="tablist" aria-label="Offers">
                <button
                    v-for="(offer, index) in offers"
                    :key="`dot-${offer.id}`"
                    type="button"
                    class="h-2 rounded-full transition-all"
                    :class="index === active ? 'w-6 bg-coral-500' : 'w-2 bg-white/60 hover:bg-white/80'"
                    :aria-label="`Go to offer ${index + 1}`"
                    :aria-selected="index === active"
                    role="tab"
                    @click="goToManually(index)"
                />
            </div>
        </div>
    </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
