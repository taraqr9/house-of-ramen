<script setup>
import PublicLayout from '../../Layouts/PublicLayout.vue';
import SeoHead from '../../Components/Public/SeoHead.vue';
import PageHeader from '../../Components/Public/PageHeader.vue';

defineOptions({ layout: PublicLayout });

defineProps({
    restaurant: { type: Object, required: true },
    mapsSearchUrl: { type: String, default: null },
    seo: { type: Object, required: true },
});
</script>

<template>
    <SeoHead :seo="seo" />

    <PageHeader
        :eyebrow="restaurant.name"
        title="Contact & Location"
        subtitle="We'd love to see you - here's how to find us."
        :background-image="restaurant.cover_image_url"
        :breadcrumbs="[{ label: 'Home', href: '/' }, { label: 'Contact' }]"
    />

    <div class="mx-auto grid max-w-6xl gap-10 px-4 py-14 sm:px-6 lg:grid-cols-2">
        <div class="space-y-8">
            <div>
                <h2 class="text-sm font-semibold tracking-wide text-coral-600 uppercase">Address</h2>
                <p v-if="restaurant.address" class="mt-2 text-lg text-charcoal-900">{{ restaurant.address }}</p>
                <p v-if="restaurant.area" class="text-charcoal-900/70">{{ restaurant.area }}</p>
                <p v-if="!restaurant.address" class="mt-2 text-charcoal-900/60">Location details coming soon.</p>
                <a
                    v-if="mapsSearchUrl"
                    :href="mapsSearchUrl"
                    target="_blank"
                    rel="noopener"
                    class="mt-3 inline-flex items-center gap-1 text-sm font-semibold text-coral-700 hover:underline"
                >
                    Get directions on Google Maps &rarr;
                </a>
            </div>

            <div>
                <h2 class="text-sm font-semibold tracking-wide text-coral-600 uppercase">Phone &amp; Email</h2>
                <p v-if="restaurant.phone" class="mt-2">
                    <a :href="`tel:${restaurant.phone}`" class="text-lg text-charcoal-900 hover:text-coral-700">{{ restaurant.phone }}</a>
                </p>
                <p v-if="restaurant.email" class="mt-1">
                    <a :href="`mailto:${restaurant.email}`" class="text-charcoal-900/70 hover:text-coral-700">{{ restaurant.email }}</a>
                </p>
                <p v-if="!restaurant.phone && !restaurant.email" class="mt-2 text-charcoal-900/60">Contact details coming soon.</p>
            </div>

            <div>
                <h2 class="text-sm font-semibold tracking-wide text-coral-600 uppercase">Opening Hours</h2>
                <div v-if="restaurant.opening_hours && Object.keys(restaurant.opening_hours).length" class="mt-2 max-w-xs space-y-1">
                    <div v-for="(hours, day) in restaurant.opening_hours" :key="day" class="flex justify-between gap-4 text-charcoal-900/80">
                        <span>{{ day }}</span>
                        <span>{{ hours }}</span>
                    </div>
                </div>
                <p v-else class="mt-2 text-charcoal-900/60">Please call us for current opening hours.</p>
            </div>

            <div v-if="restaurant.delivery_platforms?.length">
                <h2 class="text-sm font-semibold tracking-wide text-coral-600 uppercase">Order Delivery</h2>
                <div class="mt-2 flex flex-wrap gap-2">
                    <span
                        v-for="platform in restaurant.delivery_platforms"
                        :key="platform"
                        class="rounded-full bg-cream-100 px-3 py-1 text-sm font-medium text-charcoal-900/80"
                    >
                        {{ platform }}
                    </span>
                </div>
            </div>

            <div v-if="restaurant.facebook_url || restaurant.instagram_url">
                <h2 class="text-sm font-semibold tracking-wide text-coral-600 uppercase">Follow Us</h2>
                <div class="mt-2 flex gap-4">
                    <a v-if="restaurant.facebook_url" :href="restaurant.facebook_url" target="_blank" rel="noopener" class="text-charcoal-900/70 hover:text-coral-700">Facebook</a>
                    <a v-if="restaurant.instagram_url" :href="restaurant.instagram_url" target="_blank" rel="noopener" class="text-charcoal-900/70 hover:text-coral-700">Instagram</a>
                </div>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl bg-cream-100">
            <iframe
                v-if="restaurant.address"
                :src="`https://www.google.com/maps?q=${encodeURIComponent(restaurant.address + ', ' + (restaurant.area || ''))}&output=embed`"
                class="h-full min-h-80 w-full border-0"
                loading="lazy"
                referrerpolicy="no-referrer-when-downgrade"
                :title="`Map to ${restaurant.name}`"
            />
            <div v-else class="flex h-full min-h-80 items-center justify-center text-charcoal-900/50">
                Map coming soon.
            </div>
        </div>
    </div>
</template>
