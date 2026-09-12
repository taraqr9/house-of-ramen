<script setup>
import { ref } from 'vue';
import PublicLayout from '../../../Layouts/PublicLayout.vue';
import SeoHead from '../../../Components/Public/SeoHead.vue';
import PageHeader from '../../../Components/Public/PageHeader.vue';
import MenuItemCard from '../../../Components/Public/MenuItemCard.vue';
import Lightbox from '../../../Components/Public/Lightbox.vue';

defineOptions({ layout: PublicLayout });

defineProps({
    restaurant: { type: Object, required: true },
    categories: { type: Array, default: () => [] },
    seo: { type: Object, required: true },
});

const lightboxOpen = ref(false);
const lightboxImages = ref([]);
const lightboxTitle = ref('');

function openGallery({ images, title }) {
    lightboxImages.value = images;
    lightboxTitle.value = title;
    lightboxOpen.value = true;
}

function slugify(text) {
    return text
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/(^-|-$)/g, '');
}
</script>

<template>
    <SeoHead :seo="seo" />

    <PageHeader
        :eyebrow="restaurant.name"
        title="Our Menu"
        subtitle="Ramen, rice, noodles, sushi, and more - every price shown in Bangladeshi Taka."
        :background-image="restaurant.cover_image_url"
        :breadcrumbs="[{ label: 'Home', href: '/' }, { label: 'Menu' }]"
    />

    <nav
        v-if="categories.length"
        class="sticky top-18 z-30 overflow-x-auto border-b border-coral-100 bg-cream-50/95 backdrop-blur"
        aria-label="Menu categories"
    >
        <div class="mx-auto flex max-w-6xl gap-1 px-4 py-2 sm:px-6">
            <a
                v-for="category in categories"
                :key="category.id"
                :href="`#${slugify(category.slug)}`"
                class="shrink-0 rounded-full px-4 py-2 text-sm font-medium whitespace-nowrap text-charcoal-900/70 transition-colors hover:bg-coral-50 hover:text-coral-700"
            >
                {{ category.name }}
            </a>
        </div>
    </nav>

    <div class="mx-auto max-w-6xl px-4 py-12 sm:px-6">
        <p v-if="!categories.length" class="py-16 text-center text-charcoal-900/60">
            The menu is being updated - please check back shortly.
        </p>

        <section
            v-for="category in categories"
            :id="slugify(category.slug)"
            :key="category.id"
            class="scroll-mt-32 border-b border-coral-100 py-10 last:border-b-0"
        >
            <h2 class="text-2xl font-bold text-charcoal-900">{{ category.name }}</h2>
            <p v-if="category.description" class="mt-1 max-w-2xl text-charcoal-900/60">{{ category.description }}</p>

            <div class="mt-6 grid gap-4 sm:grid-cols-2">
                <MenuItemCard
                    v-for="item in category.items"
                    :key="item.id"
                    :item="item"
                    @open-gallery="openGallery"
                />
            </div>
        </section>
    </div>

    <Lightbox
        :open="lightboxOpen"
        :images="lightboxImages"
        :title="lightboxTitle"
        @close="lightboxOpen = false"
    />
</template>
