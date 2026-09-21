<script setup>
defineProps({
    review: { type: Object, required: true },
});

function initials(name) {
    return (name ?? '')
        .split(' ')
        .filter(Boolean)
        .map((part) => part[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();
}
</script>

<template>
    <div class="flex h-full flex-col rounded-2xl bg-white p-5 shadow-sm ring-1 ring-charcoal-900/5">
        <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-coral-100 text-sm font-semibold text-coral-700">
                {{ initials(review.author_name) }}
            </div>
            <div class="min-w-0">
                <p class="truncate text-sm font-semibold text-charcoal-900">{{ review.author_name }}</p>
                <p v-if="review.relative_time" class="text-xs text-charcoal-900/50">{{ review.relative_time }}</p>
            </div>
        </div>

        <div v-if="review.rating" class="mt-3 flex gap-0.5" aria-hidden="true">
            <svg
                v-for="n in 5"
                :key="n"
                class="h-4 w-4"
                :class="n <= review.rating ? 'text-yellow-400' : 'text-charcoal-900/15'"
                viewBox="0 0 20 20"
                fill="currentColor"
            >
                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.286 3.958a1 1 0 0 0 .95.69h4.162c.969 0 1.371 1.24.588 1.81l-3.368 2.447a1 1 0 0 0-.363 1.118l1.287 3.957c.299.922-.755 1.688-1.539 1.118l-3.367-2.446a1 1 0 0 0-1.176 0l-3.367 2.446c-.784.57-1.838-.196-1.539-1.118l1.287-3.957a1 1 0 0 0-.363-1.118L2.063 9.385c-.783-.57-.38-1.81.588-1.81h4.163a1 1 0 0 0 .95-.69z" />
            </svg>
        </div>

        <p class="mt-3 line-clamp-5 flex-1 text-sm leading-relaxed text-charcoal-900/80">{{ review.text }}</p>
    </div>
</template>
