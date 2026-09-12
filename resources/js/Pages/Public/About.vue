<script setup>
import { Link } from '@inertiajs/vue3';
import PublicLayout from '../../Layouts/PublicLayout.vue';
import SeoHead from '../../Components/Public/SeoHead.vue';
import PageHeader from '../../Components/Public/PageHeader.vue';

defineOptions({ layout: PublicLayout });

defineProps({
    restaurant: { type: Object, required: true },
    interiorImages: { type: Array, default: () => [] },
    seo: { type: Object, required: true },
});
</script>

<template>
    <SeoHead :seo="seo" />

    <PageHeader
        eyebrow="About Us"
        :title="`About ${restaurant.name}`"
        :subtitle="restaurant.tagline"
        :background-image="interiorImages[0]?.image_url ?? restaurant.cover_image_url"
        :breadcrumbs="[{ label: 'Home', href: '/' }, { label: 'About' }]"
    />

    <section class="mx-auto max-w-6xl px-4 py-14 sm:px-6">
        <div class="grid gap-10 lg:grid-cols-2 lg:items-center">
            <div>
                <h2 class="text-2xl font-bold text-charcoal-900">Our Story</h2>
                <p v-if="restaurant.description" class="mt-4 leading-relaxed whitespace-pre-line text-charcoal-900/70">
                    {{ restaurant.description }}
                </p>
                <p v-else class="mt-4 leading-relaxed text-charcoal-900/60">
                    Our story is coming soon - check back for more about what makes {{ restaurant.name }} special.
                </p>
            </div>

            <div v-if="interiorImages.length" class="grid gap-3" :class="interiorImages.length > 1 ? 'grid-cols-2' : ''">
                <img
                    v-for="image in interiorImages"
                    :key="image.id"
                    :src="image.image_url"
                    :alt="image.caption || `Inside ${restaurant.name}`"
                    loading="lazy"
                    class="aspect-[4/3] w-full rounded-2xl object-cover"
                    :class="interiorImages.length === 1 ? 'col-span-2' : ''"
                />
            </div>
        </div>
    </section>

    <section class="bg-coral-500">
        <div class="mx-auto max-w-6xl px-4 py-14 text-center sm:px-6">
            <h2 class="text-2xl font-bold text-white sm:text-3xl">Come see us</h2>
            <p class="mx-auto mt-2 max-w-md text-coral-50">Browse the menu or find our location and hours.</p>
            <div class="mt-6 flex flex-wrap justify-center gap-3">
                <Link href="/menu" class="rounded-full bg-white px-6 py-3 text-sm font-semibold text-coral-700 hover:bg-cream-50">
                    View Menu
                </Link>
                <Link href="/contact" class="rounded-full bg-white/10 px-6 py-3 text-sm font-semibold text-white hover:bg-white/20">
                    Contact &amp; Location
                </Link>
            </div>
        </div>
    </section>
</template>
