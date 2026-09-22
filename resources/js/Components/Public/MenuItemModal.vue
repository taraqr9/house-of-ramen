<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { formatTaka } from '../../utils/format';

const props = defineProps({
    item: { type: Object, default: null }, // { id, name, description, price, price_note, image_url, gallery_image_urls? }
});

const emit = defineEmits(['close']);

const active = ref(0);
let touchStartX = null;

// Homepage cards only ever have the one image_url (gallery_image_urls
// isn't loaded there - see HomeController), while the full /menu page
// loads each item's extra photos too - so this works for both without
// the caller needing to know the difference.
const images = computed(() => [props.item?.image_url, ...(props.item?.gallery_image_urls || [])].filter(Boolean));

function goTo(index) {
    active.value = (index + images.value.length) % images.value.length;
}

function next() {
    goTo(active.value + 1);
}

function prev() {
    goTo(active.value - 1);
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

watch(
    () => props.item,
    (item) => {
        if (item) {
            active.value = 0;
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
    if (event.key === 'ArrowRight') next();
    if (event.key === 'ArrowLeft') prev();
}
</script>

<template>
    <div
        v-if="item"
        class="fixed inset-0 z-50 flex items-center justify-center bg-charcoal-900/80 p-4 backdrop-blur-sm"
        role="dialog"
        aria-modal="true"
        :aria-label="item.name"
        @click.self="close"
    >
        <div class="relative max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white shadow-2xl">
            <button
                type="button"
                class="absolute top-3 right-3 z-20 flex h-9 w-9 items-center justify-center rounded-full bg-charcoal-900/60 text-white backdrop-blur transition hover:bg-charcoal-900/80"
                aria-label="Close"
                @click="close"
            >
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>

            <div
                class="relative flex max-h-[45vh] w-full items-center justify-center overflow-hidden bg-cream-100"
                @touchstart.passive="onTouchStart"
                @touchend.passive="onTouchEnd"
            >
                <img
                    v-if="images.length"
                    :src="images[active]"
                    :alt="item.name"
                    class="max-h-[45vh] w-full object-contain"
                />
                <div v-else class="flex h-56 w-full items-center justify-center text-coral-400">
                    <svg class="h-16 w-16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3c-3.87 0-7 1.5-7 3.5V17c0 2 3.13 3.5 7 3.5s7-1.5 7-3.5V6.5C19 4.5 15.87 3 12 3Z" /></svg>
                </div>

                <template v-if="images.length > 1">
                    <button
                        type="button"
                        class="absolute top-1/2 left-2 z-10 flex h-9 w-9 -translate-y-1/2 items-center justify-center rounded-full bg-white/85 text-charcoal-900 shadow transition hover:bg-white"
                        aria-label="Previous photo"
                        @click="prev"
                    >
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
                    </button>
                    <button
                        type="button"
                        class="absolute top-1/2 right-2 z-10 flex h-9 w-9 -translate-y-1/2 items-center justify-center rounded-full bg-white/85 text-charcoal-900 shadow transition hover:bg-white"
                        aria-label="Next photo"
                        @click="next"
                    >
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                    </button>

                    <div class="absolute bottom-2 left-1/2 flex -translate-x-1/2 gap-2" role="tablist" aria-label="Photos">
                        <button
                            v-for="(image, index) in images"
                            :key="`dot-${image}`"
                            type="button"
                            class="h-2 rounded-full transition-all"
                            :class="index === active ? 'w-6 bg-coral-500' : 'w-2 bg-charcoal-900/20 hover:bg-charcoal-900/40'"
                            :aria-label="`Go to photo ${index + 1}`"
                            :aria-selected="index === active"
                            role="tab"
                            @click="goTo(index)"
                        />
                    </div>
                </template>
            </div>

            <div class="p-5 sm:p-6">
                <div class="flex items-start justify-between gap-4">
                    <h3 class="text-xl font-bold text-charcoal-900">{{ item.name }}</h3>
                    <span class="shrink-0 text-lg font-semibold text-coral-700">{{ formatTaka(item.price) }}</span>
                </div>
                <p v-if="item.price_note" class="mt-1 text-sm text-charcoal-900/60">{{ item.price_note }}</p>
                <p v-if="item.description" class="mt-3 leading-relaxed text-charcoal-900/80">{{ item.description }}</p>
            </div>
        </div>
    </div>
</template>
