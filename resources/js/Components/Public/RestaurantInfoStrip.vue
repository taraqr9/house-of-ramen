<script setup>
defineProps({
    restaurant: { type: Object, required: true },
    mapsSearchUrl: { type: String, default: null },
});
</script>

<template>
    <div class="grid divide-y divide-charcoal-900/10 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-charcoal-900/5 sm:grid-cols-3 sm:divide-x sm:divide-y-0 sm:p-8">
        <div class="pb-6 sm:pr-8 sm:pb-0">
            <div class="flex items-center gap-2 text-xs font-semibold tracking-wide text-coral-600 uppercase">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 21s-7-6.1-7-11.5A7 7 0 0 1 19 9.5C19 14.9 12 21 12 21Z" />
                    <circle cx="12" cy="9.5" r="2.5" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
                Visit Us
            </div>
            <p v-if="restaurant.address" class="mt-2 text-charcoal-900">{{ restaurant.address }}</p>
            <p v-if="restaurant.area" class="text-charcoal-900/70">{{ restaurant.area }}</p>
            <p v-if="!restaurant.address" class="mt-2 text-charcoal-900/60">Location details coming soon.</p>
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

        <div class="py-6 sm:px-8 sm:py-0">
            <div class="flex items-center gap-2 text-xs font-semibold tracking-wide text-coral-600 uppercase">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 5.5c0-1 .8-1.5 1.5-1.5H7c.6 0 1.1.4 1.4 1l1 2.3c.2.5.1 1.1-.3 1.5l-1.3 1.3a12 12 0 0 0 5.6 5.6l1.3-1.3c.4-.4 1-.5 1.5-.3l2.3 1c.6.3 1 .8 1 1.4v2.5c0 .7-.5 1.5-1.5 1.5C10.5 20.5 3.5 13.5 3.5 6c0-.2 0-.3.5-.5Z" />
                </svg>
                Contact
            </div>
            <p v-if="restaurant.phone" class="mt-2">
                <a :href="`tel:${restaurant.phone}`" class="text-charcoal-900 hover:text-coral-700">{{ restaurant.phone }}</a>
            </p>
            <p v-if="restaurant.email" class="mt-0.5">
                <a :href="`mailto:${restaurant.email}`" class="text-charcoal-900/70 hover:text-coral-700">{{ restaurant.email }}</a>
            </p>
            <p v-if="!restaurant.phone && !restaurant.email" class="mt-2 text-charcoal-900/60">Contact details coming soon.</p>
        </div>

        <div class="pt-6 sm:pt-0 sm:pl-8">
            <div class="flex items-center gap-2 text-xs font-semibold tracking-wide text-coral-600 uppercase">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                    <circle cx="12" cy="12" r="8.5" stroke-linecap="round" stroke-linejoin="round" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 7.5V12l3 2" />
                </svg>
                Opening Hours
            </div>
            <div v-if="restaurant.opening_hours && Object.keys(restaurant.opening_hours).length" class="mt-2 space-y-0.5 text-charcoal-900/80">
                <div v-for="(hours, day) in restaurant.opening_hours" :key="day" class="flex justify-between gap-3 text-sm">
                    <span>{{ day }}</span>
                    <span>{{ hours }}</span>
                </div>
            </div>
            <p v-else class="mt-2 text-charcoal-900/60">Please call us for current hours.</p>

            <div v-if="restaurant.delivery_platforms?.length" class="mt-3 flex flex-wrap gap-2">
                <span
                    v-for="platform in restaurant.delivery_platforms"
                    :key="platform"
                    class="rounded-full bg-cream-100 px-2.5 py-1 text-xs font-medium text-charcoal-900/80"
                >
                    {{ platform }}
                </span>
            </div>
        </div>
    </div>
</template>
