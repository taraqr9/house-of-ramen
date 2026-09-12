<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    slides: { type: Array, required: true }, // [{ image_url, caption }]
    intervalMs: { type: Number, default: 4000 },
});

const active = ref(0);
let timer = null;
let touchStartX = null;

function goTo(index) {
    active.value = (index + props.slides.length) % props.slides.length;
}

function next() {
    goTo(active.value + 1);
}

function prev() {
    goTo(active.value - 1);
}

function restartAutoplay() {
    stopAutoplay();
    if (props.slides.length > 1) {
        timer = setInterval(next, props.intervalMs);
    }
}

function stopAutoplay() {
    if (timer) {
        clearInterval(timer);
        timer = null;
    }
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

onMounted(restartAutoplay);
onBeforeUnmount(stopAutoplay);
</script>

<template>
    <div
        class="relative isolate overflow-hidden bg-charcoal-900"
        @mouseenter="stopAutoplay"
        @mouseleave="restartAutoplay"
        @touchstart.passive="onTouchStart"
        @touchend.passive="onTouchEnd"
    >
        <div class="relative aspect-[4/5] w-full sm:aspect-[16/9] lg:aspect-[21/9]">
            <img
                v-for="(slide, index) in slides"
                :key="slide.image_url"
                :src="slide.image_url"
                :alt="slide.caption || 'House of Ramen'"
                class="absolute inset-0 h-full w-full object-cover transition-opacity duration-700 ease-in-out"
                :class="index === active ? 'opacity-100' : 'opacity-0'"
                :loading="index === 0 ? 'eager' : 'lazy'"
                :fetchpriority="index === 0 ? 'high' : 'auto'"
            />

            <div class="absolute inset-0 bg-gradient-to-t from-charcoal-900/80 via-charcoal-900/20 to-charcoal-900/40" aria-hidden="true" />

            <div class="absolute inset-0 flex flex-col items-start justify-end px-5 pb-10 sm:px-10 sm:pb-16 lg:px-16">
                <slot />
            </div>

            <!-- Both arrows live on the right edge (never the left) since the
                 overlay text/CTAs are always left-anchored (see the slot
                 wrapper's items-start above) - a vertically centered left
                 arrow would sit right on top of short captions/kickers on
                 some image aspect ratios. -->
            <div v-if="slides.length > 1" class="absolute top-1/2 right-3 hidden -translate-y-1/2 flex-col gap-2 sm:flex">
                <button
                    type="button"
                    class="flex h-10 w-10 items-center justify-center rounded-full bg-white/15 text-white backdrop-blur transition hover:bg-white/25"
                    aria-label="Previous slide"
                    @click="prev"
                >
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" /></svg>
                </button>
                <button
                    type="button"
                    class="flex h-10 w-10 items-center justify-center rounded-full bg-white/15 text-white backdrop-blur transition hover:bg-white/25"
                    aria-label="Next slide"
                    @click="next"
                >
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                </button>
            </div>

            <div v-if="slides.length > 1" class="absolute bottom-4 left-1/2 flex -translate-x-1/2 gap-2" role="tablist" aria-label="Slides">
                <button
                    v-for="(slide, index) in slides"
                    :key="`dot-${slide.image_url}`"
                    type="button"
                    class="h-2 rounded-full transition-all"
                    :class="index === active ? 'w-6 bg-white' : 'w-2 bg-white/50 hover:bg-white/75'"
                    :aria-label="`Go to slide ${index + 1}`"
                    :aria-selected="index === active"
                    role="tab"
                    @click="goTo(index)"
                />
            </div>
        </div>
    </div>
</template>
