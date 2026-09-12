<script setup>
const props = defineProps({
    label: { type: String, required: true },
    modelValue: { type: Number, required: true },
});

const emit = defineEmits(['update:modelValue']);

const levels = [1, 2, 3, 4, 5];
</script>

<template>
    <div class="flex flex-col gap-2 py-3 sm:flex-row sm:items-center sm:justify-between">
        <span class="text-[15px] font-medium text-stone-800">{{ label }}</span>

        <div class="flex items-center gap-2" role="radiogroup" :aria-label="label">
            <button
                v-for="level in levels"
                :key="level"
                type="button"
                role="radio"
                :aria-checked="modelValue === level"
                :aria-label="`${level} out of 5`"
                class="flex h-9 w-9 items-center justify-center rounded-full border text-sm font-semibold transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
                :class="modelValue === level
                    ? 'border-emerald-700 bg-emerald-700 text-white'
                    : 'border-stone-200 bg-white text-stone-500 hover:border-emerald-300'"
                @click="emit('update:modelValue', level)"
            >
                {{ level }}
            </button>
        </div>
    </div>
</template>
