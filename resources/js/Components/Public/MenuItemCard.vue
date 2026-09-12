<script setup>
import { computed } from 'vue';
import { formatTaka } from '../../utils/format';

const props = defineProps({
    item: { type: Object, required: true },
});

const emit = defineEmits(['open-gallery']);

const allImages = computed(() => [props.item.image_url, ...(props.item.gallery_image_urls || [])].filter(Boolean));

function openGallery() {
    if (allImages.value.length) {
        emit('open-gallery', { images: allImages.value, title: props.item.name });
    }
}
</script>

<template>
    <article class="flex gap-4 rounded-2xl border border-coral-100 bg-white p-3 transition-shadow hover:shadow-md sm:p-4">
        <button
            type="button"
            class="relative h-24 w-24 shrink-0 overflow-hidden rounded-xl bg-cream-100 sm:h-28 sm:w-28"
            :class="allImages.length ? 'cursor-zoom-in' : 'cursor-default'"
            :aria-label="allImages.length ? `View photos of ${item.name}` : undefined"
            @click="openGallery"
        >
            <img
                v-if="item.image_url"
                :src="item.image_url"
                :alt="item.name"
                loading="lazy"
                class="h-full w-full object-cover"
            />
            <div v-else class="flex h-full w-full items-center justify-center text-coral-300">
                <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3c-3.87 0-7 1.5-7 3.5V17c0 2 3.13 3.5 7 3.5s7-1.5 7-3.5V6.5C19 4.5 15.87 3 12 3Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M5 6.5c0 2 3.13 3.5 7 3.5s7-1.5 7-3.5" /></svg>
            </div>
            <span
                v-if="item.gallery_image_urls?.length"
                class="absolute right-1 bottom-1 rounded-full bg-charcoal-900/70 px-1.5 py-0.5 text-[10px] font-medium text-white"
            >
                +{{ item.gallery_image_urls.length }}
            </span>
        </button>

        <div class="flex flex-1 flex-col">
            <div class="flex items-start justify-between gap-3">
                <h3 class="font-semibold text-charcoal-900">{{ item.name }}</h3>
                <div class="shrink-0 text-right">
                    <span class="font-semibold whitespace-nowrap text-coral-700">{{ formatTaka(item.price) }}</span>
                    <p v-if="item.price_note" class="text-xs whitespace-nowrap text-charcoal-900/50">{{ item.price_note }}</p>
                </div>
            </div>
            <p v-if="item.description" class="mt-1 text-sm leading-relaxed text-charcoal-900/60">{{ item.description }}</p>
        </div>
    </article>
</template>
