<script setup>
import { Link } from '@inertiajs/vue3';
import PublicLayout from '../../Layouts/PublicLayout.vue';
import SeoHead from '../../Components/Public/SeoHead.vue';
import HeroSlider from '../../Components/Public/HeroSlider.vue';
import RestaurantInfoStrip from '../../Components/Public/RestaurantInfoStrip.vue';
import { formatTaka } from '../../utils/format';

defineOptions({ layout: PublicLayout });

defineProps({
    restaurant: { type: Object, required: true },
    heroSlides: { type: Array, default: () => [] },
    featuredItems: { type: Array, default: () => [] },
    seo: { type: Object, required: true },
});
</script>

<template>
    <SeoHead :seo="seo" />

    <!-- 1. Hero / Main Slider -->
    <HeroSlider v-if="heroSlides.length" :slides="heroSlides">
        <p class="text-sm font-semibold tracking-wide text-coral-300 uppercase">Modern Japanese-Korean Ramen</p>
        <h1 class="mt-2 max-w-xl text-3xl leading-tight font-extrabold text-white sm:text-4xl md:text-5xl">
            {{ restaurant.tagline || restaurant.name }}
        </h1>
        <div class="mt-6 flex flex-wrap gap-3">
            <Link href="/menu" class="rounded-full bg-coral-500 px-6 py-3 text-sm font-semibold text-white shadow-lg transition-colors hover:bg-coral-600 sm:text-base">
                View Menu
            </Link>
            <Link href="/contact" class="rounded-full bg-white/10 px-6 py-3 text-sm font-semibold text-white backdrop-blur transition-colors hover:bg-white/20 sm:text-base">
                Visit Us
            </Link>
        </div>
    </HeroSlider>

    <!-- Fallback hero for a fresh install with no gallery photos yet -->
    <section v-else class="bg-charcoal-900 px-5 py-20 text-center sm:px-10">
        <p class="text-sm font-semibold tracking-wide text-coral-300 uppercase">Modern Japanese-Korean Ramen</p>
        <h1 class="mx-auto mt-2 max-w-xl text-3xl font-extrabold text-white sm:text-4xl">{{ restaurant.name }}</h1>
        <Link href="/menu" class="mt-6 inline-block rounded-full bg-coral-500 px-6 py-3 text-sm font-semibold text-white hover:bg-coral-600">
            View Menu
        </Link>
    </section>

    <!-- 2. Restaurant Introduction -->
    <section class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
        <div class="mx-auto max-w-2xl text-center">
            <p class="text-sm font-semibold tracking-wide text-coral-600 uppercase">Welcome to {{ restaurant.name }}</p>
            <p v-if="restaurant.description" class="mt-4 text-lg leading-relaxed text-charcoal-900/80">
                {{ restaurant.description }}
            </p>
            <p v-else class="mt-4 text-lg leading-relaxed text-charcoal-900/60">
                A modern take on ramen and Japanese-Korean comfort food, made for sharing with friends and family.
            </p>
        </div>
    </section>

    <!-- 3. Featured Menu -->
    <section v-if="featuredItems.length" class="bg-cream-100 py-16">
        <div class="mx-auto max-w-6xl px-4 sm:px-6">
            <div class="flex items-end justify-between gap-4">
                <div>
                    <p class="text-sm font-semibold tracking-wide text-coral-600 uppercase">Signature Dishes</p>
                    <h2 class="mt-1 text-2xl font-bold text-charcoal-900 sm:text-3xl">Featured on the Menu</h2>
                </div>
                <Link href="/menu" class="hidden shrink-0 text-sm font-semibold text-coral-700 hover:underline sm:inline-block">
                    View Full Menu &rarr;
                </Link>
            </div>

            <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <Link
                    v-for="item in featuredItems"
                    :key="item.id"
                    href="/menu"
                    class="group overflow-hidden rounded-2xl bg-white shadow-sm transition-shadow hover:shadow-lg"
                >
                    <div class="aspect-[4/3] overflow-hidden bg-cream-200">
                        <img
                            v-if="item.image_url"
                            :src="item.image_url"
                            :alt="item.name"
                            loading="lazy"
                            class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
                        />
                        <div v-else class="flex h-full w-full items-center justify-center text-coral-300">
                            <svg class="h-10 w-10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3c-3.87 0-7 1.5-7 3.5V17c0 2 3.13 3.5 7 3.5s7-1.5 7-3.5V6.5C19 4.5 15.87 3 12 3Z" /></svg>
                        </div>
                    </div>
                    <div class="p-4">
                        <div class="flex items-start justify-between gap-3">
                            <h3 class="font-semibold text-charcoal-900">{{ item.name }}</h3>
                            <span class="shrink-0 font-semibold text-coral-700">{{ formatTaka(item.price) }}</span>
                        </div>
                        <p v-if="item.description" class="mt-1 line-clamp-2 text-sm text-charcoal-900/60">{{ item.description }}</p>
                    </div>
                </Link>
            </div>

            <div class="mt-8 text-center sm:hidden">
                <Link href="/menu" class="inline-block rounded-full bg-coral-500 px-6 py-3 text-sm font-semibold text-white hover:bg-coral-600">
                    View Full Menu
                </Link>
            </div>
        </div>
    </section>

    <!-- Info strip -->
    <section class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
        <RestaurantInfoStrip :restaurant="restaurant" />
    </section>
</template>
