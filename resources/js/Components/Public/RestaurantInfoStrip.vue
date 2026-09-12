<script setup>
defineProps({
    restaurant: { type: Object, required: true },
    mapsSearchUrl: { type: String, default: null },
});
</script>

<template>
    <div class="grid gap-6 rounded-2xl bg-cream-100 p-6 sm:grid-cols-3 sm:p-8">
        <div>
            <p class="text-xs font-semibold tracking-wide text-coral-600 uppercase">Visit Us</p>
            <p v-if="restaurant.address" class="mt-1 text-charcoal-900">{{ restaurant.address }}</p>
            <p v-if="restaurant.area" class="text-charcoal-900/70">{{ restaurant.area }}</p>
            <p v-if="!restaurant.address" class="mt-1 text-charcoal-900/60">Location details coming soon.</p>
            <a
                v-if="mapsSearchUrl"
                :href="mapsSearchUrl"
                target="_blank"
                rel="noopener"
                class="mt-2 inline-block text-sm font-semibold text-coral-700 hover:underline"
            >
                Get directions &rarr;
            </a>
        </div>

        <div>
            <p class="text-xs font-semibold tracking-wide text-coral-600 uppercase">Contact</p>
            <p v-if="restaurant.phone" class="mt-1">
                <a :href="`tel:${restaurant.phone}`" class="text-charcoal-900 hover:text-coral-700">{{ restaurant.phone }}</a>
            </p>
            <p v-if="restaurant.email" class="mt-0.5">
                <a :href="`mailto:${restaurant.email}`" class="text-charcoal-900/70 hover:text-coral-700">{{ restaurant.email }}</a>
            </p>
            <p v-if="!restaurant.phone && !restaurant.email" class="mt-1 text-charcoal-900/60">Contact details coming soon.</p>
        </div>

        <div>
            <p class="text-xs font-semibold tracking-wide text-coral-600 uppercase">Opening Hours</p>
            <div v-if="restaurant.opening_hours && Object.keys(restaurant.opening_hours).length" class="mt-1 space-y-0.5 text-charcoal-900/80">
                <div v-for="(hours, day) in restaurant.opening_hours" :key="day" class="flex justify-between gap-3 text-sm">
                    <span>{{ day }}</span>
                    <span>{{ hours }}</span>
                </div>
            </div>
            <p v-else class="mt-1 text-charcoal-900/60">Please call us for current hours.</p>

            <div v-if="restaurant.delivery_platforms?.length" class="mt-3 flex flex-wrap gap-2">
                <span
                    v-for="platform in restaurant.delivery_platforms"
                    :key="platform"
                    class="rounded-full bg-white px-2.5 py-1 text-xs font-medium text-charcoal-900/80"
                >
                    {{ platform }}
                </span>
            </div>
        </div>
    </div>
</template>
