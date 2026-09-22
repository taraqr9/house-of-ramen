<script setup>
import { onMounted, onUnmounted, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import PublicLayout from '../../Layouts/PublicLayout.vue';
import SeoHead from '../../Components/Public/SeoHead.vue';
import HeroSlider from '../../Components/Public/HeroSlider.vue';
import RestaurantInfoStrip from '../../Components/Public/RestaurantInfoStrip.vue';
import VideoModal from '../../Components/Public/VideoModal.vue';
import PromoPopup from '../../Components/Public/PromoPopup.vue';
import MenuItemModal from '../../Components/Public/MenuItemModal.vue';
import ReviewCard from '../../Components/Public/ReviewCard.vue';
import ReservationForm from '../../Components/Public/ReservationForm.vue';
import { formatTaka } from '../../utils/format';

defineOptions({ layout: PublicLayout });

defineProps({
    restaurant: { type: Object, required: true },
    heroSlides: { type: Array, default: () => [] },
    featuredItems: { type: Array, default: () => [] },
    newItems: { type: Array, default: () => [] },
    videoFeatures: { type: Array, default: () => [] },
    popupOffers: { type: Array, default: () => [] },
    reviews: { type: Array, default: () => [] },
    reviewsSummary: { type: Object, default: () => ({}) },
    seo: { type: Object, required: true },
});

// The promo popup should only greet visitors landing on the homepage
// itself (via the logo or "Home" nav link) - not visitors sent straight
// to the reservation form via the "Reservation" nav/footer link
// (`/#reserve`), where an interruption right before booking is unwelcome.
// Re-checked on every Inertia navigation (not just on mount) because
// Reservation -> Home both resolve to this same page component, which
// Inertia updates in place rather than remounting.
const showPromoPopup = ref(false);

function syncPromoPopupVisibility() {
    showPromoPopup.value = window.location.hash !== '#reserve';
}

let stopNavigateListener;

onMounted(() => {
    syncPromoPopupVisibility();
    stopNavigateListener = router.on('navigate', syncPromoPopupVisibility);
});

onUnmounted(() => {
    stopNavigateListener?.();
});

const activeVideo = ref(null);

function openVideo(video) {
    // Every YouTube/Facebook/Instagram link RestaurantVideoFeature accepts
    // builds an embed_url, but if one somehow doesn't (e.g. the platform
    // couldn't be embedded), fall back to the original link in a new tab
    // rather than opening an empty modal.
    if (!video.embed_url) {
        window.open(video.video_url, '_blank', 'noopener');
        return;
    }

    activeVideo.value = video;
}

function closeVideo() {
    activeVideo.value = null;
}

const activeMenuItem = ref(null);

function openMenuItem(item) {
    activeMenuItem.value = item;
}

function closeMenuItem() {
    activeMenuItem.value = null;
}
</script>

<template>
    <SeoHead :seo="seo" />

    <PromoPopup v-if="showPromoPopup" :offers="popupOffers" />

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
                <button
                    v-for="item in featuredItems"
                    :key="item.id"
                    type="button"
                    class="group overflow-hidden rounded-2xl bg-white text-left shadow-sm transition-shadow hover:shadow-lg"
                    @click="openMenuItem(item)"
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
                </button>
            </div>

            <div class="mt-8 text-center sm:hidden">
                <Link href="/menu" class="inline-block rounded-full bg-coral-500 px-6 py-3 text-sm font-semibold text-white hover:bg-coral-600">
                    View Full Menu
                </Link>
            </div>
        </div>
    </section>

    <!-- 4. New on the Menu - deliberately its own dark "spotlight" band with
         an olive accent (unlike the coral used everywhere else) so it reads
         as visually distinct from Featured Menu at a glance, not a repeat
         of it. -->
    <section v-if="newItems.length" class="bg-charcoal-900 py-16">
        <div class="mx-auto max-w-6xl px-4 sm:px-6">
            <div class="flex items-end justify-between gap-4">
                <div>
                    <p class="flex items-center gap-2 text-sm font-semibold tracking-wide text-olive-500 uppercase">
                        <span class="relative flex h-2 w-2">
                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-olive-500 opacity-75"></span>
                            <span class="relative inline-flex h-2 w-2 rounded-full bg-olive-500"></span>
                        </span>
                        Just Added
                    </p>
                    <h2 class="mt-1 text-2xl font-bold text-white sm:text-3xl">New on the Menu</h2>
                </div>
                <Link href="/menu" class="hidden shrink-0 text-sm font-semibold text-olive-500 hover:underline sm:inline-block">
                    View Full Menu &rarr;
                </Link>
            </div>

            <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <button
                    v-for="item in newItems"
                    :key="item.id"
                    type="button"
                    class="group overflow-hidden rounded-2xl bg-white text-left shadow-lg ring-1 ring-olive-500/40 transition-all hover:-translate-y-1 hover:ring-2 hover:ring-olive-500"
                    @click="openMenuItem(item)"
                >
                    <div class="relative aspect-[4/3] overflow-hidden bg-cream-200">
                        <div class="absolute -left-10 top-4 z-10 w-40 -rotate-45 bg-olive-500 py-1 text-center text-xs font-bold tracking-wider text-white shadow-md">
                            NEW
                        </div>
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
                            <span class="shrink-0 font-semibold text-olive-600">{{ formatTaka(item.price) }}</span>
                        </div>
                        <p v-if="item.description" class="mt-1 line-clamp-2 text-sm text-charcoal-900/60">{{ item.description }}</p>
                    </div>
                </button>
            </div>

            <div class="mt-8 text-center sm:hidden">
                <Link href="/menu" class="inline-block rounded-full bg-olive-500 px-6 py-3 text-sm font-semibold text-white hover:bg-olive-600">
                    View Full Menu
                </Link>
            </div>
        </div>
    </section>

    <!-- 5. Blogger Video Features -->
    <section v-if="videoFeatures.length" class="bg-cream-100 py-16">
        <div class="mx-auto max-w-6xl px-4 sm:px-6">
            <div class="text-center">
                <p class="text-sm font-semibold tracking-wide text-coral-600 uppercase">As Featured By</p>
                <h2 class="mt-1 text-2xl font-bold text-charcoal-900 sm:text-3xl">Blogger Video Features</h2>
            </div>

            <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <button
                    v-for="video in videoFeatures"
                    :key="video.id"
                    type="button"
                    class="group overflow-hidden rounded-2xl bg-white text-left shadow-sm transition-shadow hover:shadow-lg"
                    @click="openVideo(video)"
                >
                    <div class="relative aspect-video overflow-hidden bg-charcoal-900">
                        <img
                            v-if="video.thumbnail_url"
                            :src="video.thumbnail_url"
                            :alt="video.title"
                            loading="lazy"
                            class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
                        />
                        <div class="absolute inset-0 flex items-center justify-center bg-charcoal-900/20 transition-colors group-hover:bg-charcoal-900/30">
                            <span class="flex h-14 w-14 items-center justify-center rounded-full bg-white/90 text-coral-600 shadow-lg">
                                <svg class="h-6 w-6 translate-x-0.5" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z" /></svg>
                            </span>
                        </div>
                    </div>
                    <div class="p-4">
                        <h3 class="font-semibold text-charcoal-900">{{ video.title }}</h3>
                    </div>
                </button>
            </div>
        </div>
    </section>

    <VideoModal
        :open="!!activeVideo"
        :embed-url="activeVideo?.embed_url"
        :title="activeVideo?.title"
        :platform="activeVideo?.platform"
        @close="closeVideo"
    />

    <MenuItemModal :item="activeMenuItem" @close="closeMenuItem" />

    <!-- 6. Customer Reviews (left) + Reserve a Table (right) - real reviews
         the restaurant owner copied over from the actual Google listing
         (see RestaurantSeeder / RestaurantReview), never fabricated or
         scraped. Paired with reservations in one section so a visitor
         reading proof-of-quality reviews can book a table right next to
         them, instead of having to scroll to a separate part of the page. -->
    <section id="reserve" class="border-t border-coral-100 bg-white py-16 sm:py-20">
        <div class="mx-auto max-w-6xl px-4 sm:px-6">
            <div class="grid items-start gap-12 xl:grid-cols-2 xl:gap-10">
                <div>
                    <p class="text-sm font-semibold tracking-wide text-coral-600 uppercase">Real Reviews From Google</p>
                    <h2 class="mt-1 text-2xl font-bold text-charcoal-900 sm:text-3xl">Loved by Our Customers</h2>

                    <div v-if="reviewsSummary?.rating" class="mt-3 flex items-center gap-2">
                        <div class="flex gap-0.5" aria-hidden="true">
                            <svg
                                v-for="n in 5"
                                :key="n"
                                class="h-5 w-5"
                                :class="n <= Math.round(reviewsSummary.rating) ? 'text-yellow-400' : 'text-charcoal-900/15'"
                                viewBox="0 0 20 20"
                                fill="currentColor"
                            >
                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.286 3.958a1 1 0 0 0 .95.69h4.162c.969 0 1.371 1.24.588 1.81l-3.368 2.447a1 1 0 0 0-.363 1.118l1.287 3.957c.299.922-.755 1.688-1.539 1.118l-3.367-2.446a1 1 0 0 0-1.176 0l-3.367 2.446c-.784.57-1.838-.196-1.539-1.118l1.287-3.957a1 1 0 0 0-.363-1.118L2.063 9.385c-.783-.57-.38-1.81.588-1.81h4.163a1 1 0 0 0 .95-.69z" />
                            </svg>
                        </div>
                        <span class="text-sm font-semibold text-charcoal-900">{{ reviewsSummary.rating.toFixed(1) }}</span>
                        <span v-if="reviewsSummary.total" class="text-sm text-charcoal-900/50">({{ reviewsSummary.total }} reviews)</span>
                    </div>

                    <div v-if="reviews.length" class="mt-8 grid gap-5 sm:grid-cols-2">
                        <ReviewCard v-for="(review, index) in reviews.slice(0, 6)" :key="`${review.author_name}-${index}`" :review="review" />
                    </div>

                    <div class="mt-8">
                        <a
                            href="https://share.google/b8QO18pNWQdkn7yAk"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center gap-2 rounded-full bg-coral-500 px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-coral-600 sm:text-base"
                        >
                            {{ reviews.length ? 'Read More Reviews on Google' : 'Read Our Google Reviews' }} &rarr;
                        </a>
                    </div>
                </div>

                <div class="xl:sticky xl:top-24">
                    <p class="text-sm font-semibold tracking-wide text-coral-600 uppercase">Book Ahead</p>
                    <h2 class="mt-1 text-2xl font-bold text-charcoal-900 sm:text-3xl">Reserve a Table</h2>
                    <p class="mt-3 text-charcoal-900/70">
                        Tell us when you're coming and we'll call you shortly to confirm your table.
                    </p>

                    <div class="mt-6">
                        <ReservationForm :phone="restaurant.phone" />
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 7. Visit Us - address, contact, opening hours, and delivery
         platforms, given its own clearly separated section right before
         the footer rather than folded into it, so it reads as real page
         content (and structured Restaurant JSON-LD context) rather than
         boilerplate site furniture. -->
    <section class="border-t border-coral-100 bg-cream-100 py-16 sm:py-20">
        <div class="mx-auto max-w-6xl px-4 sm:px-6">
            <div class="text-center">
                <p class="text-sm font-semibold tracking-wide text-coral-600 uppercase">Find Us</p>
                <h2 class="mt-1 text-2xl font-bold text-charcoal-900 sm:text-3xl">Visit Us</h2>
            </div>

            <div class="mt-8">
                <RestaurantInfoStrip :restaurant="restaurant" />
            </div>
        </div>
    </section>
</template>
