<script setup>
import { computed, ref } from 'vue';
import PublicLayout from '../../Layouts/PublicLayout.vue';
import SeoHead from '../../Components/Public/SeoHead.vue';
import PageHeader from '../../Components/Public/PageHeader.vue';
import Lightbox from '../../Components/Public/Lightbox.vue';

defineOptions({ layout: PublicLayout });

const props = defineProps({
    restaurant: { type: Object, required: true },
    images: { type: Array, default: () => [] },
    seo: { type: Object, required: true },
});

const CATEGORY_LABELS = {
    interior: 'Interior',
    exterior: 'Exterior',
    food: 'Food',
    event: 'Events',
    other: 'More',
};

const activeFilter = ref('all');

const availableCategories = computed(() => [...new Set(props.images.map((image) => image.category))]);

const filteredImages = computed(() =>
    activeFilter.value === 'all' ? props.images : props.images.filter((image) => image.category === activeFilter.value),
);

const lightboxOpen = ref(false);
const lightboxIndex = ref(0);
const lightboxImages = computed(() => filteredImages.value.map((image) => image.image_url));

// Prefer a food shot for the header banner (the interior already gets its
// own dedicated tile in the grid below) - falls back to the restaurant's
// own cover photo if no food photo has been uploaded yet.
const headerImage = computed(
    () => props.images.find((image) => image.category === 'food')?.image_url ?? props.restaurant.cover_image_url,
);

function openAt(index) {
    lightboxIndex.value = index;
    lightboxOpen.value = true;
}
</script>

<template>
    <SeoHead :seo="seo" />

    <PageHeader
        :eyebrow="restaurant.name"
        title="Gallery"
        subtitle="The food, the atmosphere, and the space."
        :background-image="headerImage"
        :breadcrumbs="[{ label: 'Home', href: '/' }, { label: 'Gallery' }]"
    />

    <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6">
        <div v-if="availableCategories.length > 1" class="flex flex-wrap gap-2">
            <button
                type="button"
                class="rounded-full px-4 py-2 text-sm font-medium transition-colors"
                :class="activeFilter === 'all' ? 'bg-coral-500 text-white' : 'bg-cream-100 text-charcoal-900/70 hover:bg-coral-50'"
                @click="activeFilter = 'all'"
            >
                All
            </button>
            <button
                v-for="category in availableCategories"
                :key="category"
                type="button"
                class="rounded-full px-4 py-2 text-sm font-medium transition-colors"
                :class="activeFilter === category ? 'bg-coral-500 text-white' : 'bg-cream-100 text-charcoal-900/70 hover:bg-coral-50'"
                @click="activeFilter = category"
            >
                {{ CATEGORY_LABELS[category] ?? category }}
            </button>
        </div>

        <p v-if="!filteredImages.length" class="py-16 text-center text-charcoal-900/60">
            More photos are on the way - please check back shortly.
        </p>

        <div v-else class="mt-6 columns-2 gap-3 sm:columns-3 [&>*]:mb-3">
            <button
                v-for="(image, index) in filteredImages"
                :key="image.id"
                type="button"
                class="block w-full overflow-hidden rounded-xl bg-cream-100"
                @click="openAt(index)"
            >
                <img
                    :src="image.image_url"
                    :alt="image.caption || `${restaurant.name} ${CATEGORY_LABELS[image.category] ?? ''}`"
                    loading="lazy"
                    class="w-full transition-transform duration-300 hover:scale-105"
                />
            </button>
        </div>
    </div>

    <Lightbox
        :open="lightboxOpen"
        :images="lightboxImages"
        :start-index="lightboxIndex"
        :title="restaurant.name"
        @close="lightboxOpen = false"
    />
</template>
