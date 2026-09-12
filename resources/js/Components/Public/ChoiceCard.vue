<script setup>
defineProps({
    label: { type: String, required: true },
    description: { type: String, default: '' },
    selected: { type: Boolean, default: false },
});

defineEmits(['click']);
</script>

<template>
    <button
        type="button"
        class="flex w-full items-center justify-between gap-3 rounded-2xl border px-4 py-3.5 text-left transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
        :class="selected
            ? 'border-emerald-700 bg-emerald-50 ring-1 ring-emerald-700'
            : 'border-stone-200 bg-white hover:border-emerald-300'"
        :aria-pressed="selected"
        @click="$emit('click')"
    >
        <span>
            <span class="block text-[15px] font-semibold text-stone-900">{{ label }}</span>
            <span v-if="description" class="mt-0.5 block text-sm text-stone-500">{{ description }}</span>
        </span>

        <span
            class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full border-2"
            :class="selected ? 'border-emerald-700 bg-emerald-700' : 'border-stone-300'"
            aria-hidden="true"
        >
            <svg v-if="selected" viewBox="0 0 24 24" class="h-3.5 w-3.5 text-white" fill="none" stroke="currentColor" stroke-width="3">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
            </svg>
        </span>
    </button>
</template>
