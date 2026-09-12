<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import ChoiceCard from './ChoiceCard.vue';
import ImportanceRow from './ImportanceRow.vue';
import { formatTaka } from '../../utils/format';
import { trackEvent } from '../../utils/analytics';
import { budgetRangeFor } from '../../utils/budget';

const props = defineProps({
    brands: { type: Array, default: () => [] },
    priceBrackets: { type: Array, default: () => [] },
    dimensions: { type: Array, default: () => [] },
});

const submitting = ref(false);

const form = reactive({
    max_budget: undefined, // undefined = not yet answered; null = "any budget"
    primary_usage: null,
    importance: Object.fromEntries(props.dimensions.map((d) => [d, 3])),
    price_preference: 'both', // 'official' | 'both' | 'unofficial' - reflects the broader BD market by default
    required_5g: false,
    required_nfc: false,
    required_storage_gb: null,
    preferred_brand_ids: [],
    excluded_brand_ids: [],
});

// Displayed, comma-formatted text for the custom budget field - kept
// separate from form.max_budget, which always stays a clean number.
const customBudgetText = ref('');

// The questionnaire is embedded on the homepage and always rendered, so
// "started" can't just mean "the component mounted" - it's the first
// real answer (always budget, step 0) that counts as starting it.
const hasStartedFunnel = ref(false);

function trackFunnelStart() {
    if (hasStartedFunnel.value) return;
    hasStartedFunnel.value = true;
    trackEvent('find_phone_started');
}

function trackBudgetSelected(amount) {
    const { budget_min, budget_max, budget_range } = budgetRangeFor(amount, props.priceBrackets);
    trackEvent('budget_selected', { budget_min, budget_max, budget_range, source: 'find_my_phone' });
}

function chooseBudgetBracket(value) {
    trackFunnelStart();
    form.max_budget = value;
    customBudgetText.value = '';
    trackBudgetSelected(value);
}

function onCustomBudgetInput(event) {
    const digits = event.target.value.replace(/[^\d]/g, '');

    if (digits === '') {
        customBudgetText.value = '';

        return;
    }

    const value = parseInt(digits, 10);

    if (Number.isFinite(value) && value > 0) {
        trackFunnelStart();
        form.max_budget = value;
        customBudgetText.value = value.toLocaleString('en-US');
    }
}

// budget_selected fires on blur (not per keystroke) - otherwise typing a
// 5-digit amount would send five separate events for the one answer.
function onCustomBudgetBlur() {
    if (customBudgetText.value !== '' && form.max_budget !== undefined) {
        trackBudgetSelected(form.max_budget);
    }
}

const priceOptions = [
    { value: 'official', label: 'Official only', description: 'Full manufacturer warranty, usually pricier' },
    { value: 'both', label: 'Official or unofficial', description: "Whichever fits your budget best — most common choice in Bangladesh" },
    { value: 'unofficial', label: 'Unofficial only', description: 'Grey-market import, usually cheaper' },
];

const usageOptions = [
    { value: 'general', label: 'General use', description: 'Calls, messaging, browsing, social media' },
    { value: 'gaming', label: 'Gaming', description: 'Smooth performance for mobile games' },
    { value: 'camera', label: 'Camera & photography', description: 'Better photos and videos' },
    { value: 'productivity', label: 'Work & productivity', description: 'Emails, documents, multitasking' },
    { value: 'social', label: 'Social media & browsing', description: 'Scrolling, watching, chatting' },
];

const usagePresets = {
    gaming: { performance: 5, gaming: 5 },
    camera: { camera: 5 },
    productivity: { performance: 4, software: 4 },
    social: { battery: 4, display: 4 },
    general: {},
};

function chooseUsage(value) {
    form.primary_usage = value;
    Object.assign(form.importance, usagePresets[value] ?? {});
}

const dimensionMeta = {
    performance: { label: 'Performance & gaming', description: 'Smooth for everyday apps and games alike.' },
    camera: { label: 'Camera quality', description: 'Sharp photos and good low-light shots.' },
    battery: { label: 'Battery life', description: 'Lasts you a full day without worry.' },
    display: { label: 'Display quality', description: 'Sharp, smooth, easy on the eyes.' },
    software: { label: 'Long-term software updates', description: 'Stays secure and up to date for years.' },
    build: { label: 'Build quality & design', description: 'Feels solid, looks good.' },
    charging: { label: 'Fast charging', description: "Quick top-ups when you're in a hurry." },
    value: { label: 'Value for money', description: 'Getting the most for what you spend.' },
};

// One row per dimension the backend supports, except "gaming" is folded
// into the "performance" row (users don't naturally separate the two) -
// selecting a level there sets both underneath.
const importanceRows = computed(() => {
    const rows = [];
    const handled = new Set();

    for (const dim of props.dimensions) {
        if (handled.has(dim)) continue;

        if (dim === 'performance' && props.dimensions.includes('gaming')) {
            rows.push({ key: 'performance', linked: ['performance', 'gaming'], ...dimensionMeta.performance });
            handled.add('performance');
            handled.add('gaming');
            continue;
        }

        const meta = dimensionMeta[dim] ?? { label: dim, description: '' };
        rows.push({ key: dim, linked: [dim], ...meta });
        handled.add(dim);
    }

    return rows;
});

function setImportance(row, level) {
    row.linked.forEach((key) => (form.importance[key] = level));
}

const storageOptions = [
    { value: null, label: 'No preference' },
    { value: 64, label: '64GB+' },
    { value: 128, label: '128GB+' },
    { value: 256, label: '256GB+' },
    { value: 512, label: '512GB+' },
];

function toggleBrand(list, brandId) {
    const other = list === form.preferred_brand_ids ? form.excluded_brand_ids : form.preferred_brand_ids;
    const otherIndex = other.indexOf(brandId);
    if (otherIndex !== -1) other.splice(otherIndex, 1);

    const index = list.indexOf(brandId);
    if (index === -1) list.push(brandId);
    else list.splice(index, 1);
}

// The only two questions the questionnaire actually requires an answer to -
// everything else is optional and the recommendation engine handles it
// being blank. Steps 1 and 2 below gate "Continue" on these.
const missingFields = computed(() => {
    const missing = [];
    if (form.max_budget === undefined) missing.push('your budget');
    if (form.primary_usage === null) missing.push('what you\'ll mainly use it for');

    return missing;
});
const canSubmit = computed(() => missingFields.value.length === 0);

// --- Guided wizard: one focus area per screen, "Step X of N" progress,
// Back/Continue navigation. Every step still just edits the same `form`
// object above - nothing about validation, scoring, or the submitted
// payload changes, only how the fields are presented and stepped through.
const steps = [
    { key: 'budget', label: 'Budget' },
    { key: 'usage', label: 'Main usage' },
    { key: 'musthave', label: 'Must-have features' },
    { key: 'importance', label: 'What matters most' },
    { key: 'brand', label: 'Brand preference' },
];
const currentStep = ref(0);
const stepAttempted = ref(false);
const isFirstStep = computed(() => currentStep.value === 0);
const isLastStep = computed(() => currentStep.value === steps.length - 1);

// Only budget (step 0) and usage (step 1) block progress - every later
// step is optional, same as before.
const canAdvance = computed(() => {
    if (currentStep.value === 0) return form.max_budget !== undefined;
    if (currentStep.value === 1) return form.primary_usage !== null;

    return true;
});

watch(currentStep, () => {
    stepAttempted.value = false;
});

function goBack() {
    if (!isFirstStep.value) currentStep.value -= 1;
}

function goNext() {
    if (isLastStep.value) {
        submit();

        return;
    }

    if (!canAdvance.value) {
        stepAttempted.value = true;

        return;
    }

    trackEvent('find_phone_step_completed', {
        step_number: currentStep.value + 1,
        step_name: steps[currentStep.value].key,
    });

    currentStep.value += 1;
}

function submit() {
    if (!canSubmit.value) {
        stepAttempted.value = true;
        currentStep.value = form.max_budget === undefined ? 0 : 1;

        return;
    }

    submitting.value = true;
    router.post(
        '/find-my-phone/results',
        {
            max_budget: form.max_budget ?? null,
            primary_usage: form.primary_usage,
            importance: form.importance,
            price_preference: form.price_preference,
            required_5g: form.required_5g,
            required_nfc: form.required_nfc,
            required_storage_gb: form.required_storage_gb,
            preferred_brand_ids: form.preferred_brand_ids,
            excluded_brand_ids: form.excluded_brand_ids,
        },
        {
            onSuccess: () => {
                // Only the preference/category shape of the answers, never
                // the raw questionnaire - no free-text, no identifiers.
                trackEvent('find_phone_completed', {
                    budget_range: budgetRangeFor(form.max_budget, props.priceBrackets).budget_range,
                    usage_category: form.primary_usage,
                    preferred_brands: form.preferred_brand_ids
                        .map((id) => props.brands.find((b) => b.id === id)?.name)
                        .filter(Boolean),
                    required_5g: form.required_5g,
                    required_nfc: form.required_nfc,
                    storage_requirement: form.required_storage_gb,
                });
            },
            onFinish: () => (submitting.value = false),
        },
    );
}
</script>

<template>
    <div>
        <!-- Progress: "Step X of N" + a slim bar, not a technical form label -->
        <div class="mx-auto max-w-3xl">
            <div class="flex items-center justify-between text-sm font-medium text-stone-500">
                <span>Step {{ currentStep + 1 }} of {{ steps.length }}</span>
                <span>{{ steps[currentStep].label }}</span>
            </div>
            <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-stone-200">
                <div
                    class="h-full rounded-full bg-emerald-700 transition-all duration-300 ease-out"
                    :style="{ width: `${((currentStep + 1) / steps.length) * 100}%` }"
                />
            </div>
        </div>

        <Transition name="step-fade" mode="out-in">
            <div :key="currentStep" class="mx-auto mt-5 max-w-3xl rounded-2xl border border-stone-200 bg-white p-6 shadow-sm sm:p-8">
                <!-- 1. Budget -->
                <div v-if="currentStep === 0">
                    <h3 class="text-xl font-bold text-stone-900 sm:text-2xl">What's your budget?</h3>
                    <p class="mt-1 text-[15px] text-stone-600">The most you'd like to spend — we'll match phones within reach.</p>

                    <div class="mt-5">
                        <label for="custom-budget" class="text-sm font-medium text-stone-800">enter your own amount</label>
                        <div class="mt-2 flex items-center rounded-2xl border border-stone-200 bg-white px-4 py-3 focus-within:border-emerald-500 focus-within:ring-1 focus-within:ring-emerald-500">
                            <span class="mr-1 text-stone-400" aria-hidden="true">৳</span>
                            <input
                                id="custom-budget"
                                type="text"
                                inputmode="numeric"
                                autocomplete="off"
                                placeholder="e.g. 18,000"
                                class="w-full border-0 p-0 text-[15px] text-stone-900 placeholder:text-stone-400 focus:ring-0 focus:outline-none"
                                :value="customBudgetText"
                                @input="onCustomBudgetInput"
                                @blur="onCustomBudgetBlur"
                            />
                        </div>
                        <p v-if="form.max_budget !== undefined" class="mt-2 text-sm text-stone-500">
                            {{ form.max_budget ? `Up to ${formatTaka(form.max_budget)}` : 'No budget limit' }}
                        </p>
                    </div>

                    <div class="mt-6 grid gap-3 sm:grid-cols-2">
                        <ChoiceCard
                            v-for="bracket in priceBrackets"
                            :key="bracket.label"
                            :label="bracket.label"
                            :selected="form.max_budget === bracket.max_budget && customBudgetText === ''"
                            @click="chooseBudgetBracket(bracket.max_budget)"
                        />
                    </div>

                    <p v-if="stepAttempted && form.max_budget === undefined" class="mt-4 text-sm font-medium text-red-600">Please choose a budget to continue.</p>
                </div>

                <!-- 2. Main usage -->
                <div v-else-if="currentStep === 1">
                    <h3 class="text-xl font-bold text-stone-900 sm:text-2xl">What will you mainly use it for?</h3>
                    <p class="mt-1 text-[15px] text-stone-600">Pick the closest match — we'll fine-tune your matches based on this.</p>

                    <div class="mt-6 grid gap-3 sm:grid-cols-2">
                        <ChoiceCard
                            v-for="option in usageOptions"
                            :key="option.value"
                            :label="option.label"
                            :description="option.description"
                            :selected="form.primary_usage === option.value"
                            @click="chooseUsage(option.value)"
                        />
                    </div>

                    <p v-if="stepAttempted && form.primary_usage === null" class="mt-4 text-sm font-medium text-red-600">Please pick what you'll mainly use it for.</p>
                </div>

                <!-- 3. Must-have features -->
                <div v-else-if="currentStep === 2">
                    <h3 class="text-xl font-bold text-stone-900 sm:text-2xl">Any must-haves?</h3>
                    <p class="mt-1 text-[15px] text-stone-600">Optional — we'll only show phones that meet what you tick.</p>

                    <div class="mt-6">
                        <p class="text-sm font-medium text-stone-800">Where do you want to buy?</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <button
                                v-for="option in priceOptions"
                                :key="option.value"
                                type="button"
                                :title="option.description"
                                class="rounded-full border px-4 py-2.5 text-sm font-semibold transition-colors focus:outline-none focus:ring-1 focus:ring-emerald-500"
                                :aria-pressed="form.price_preference === option.value"
                                :class="form.price_preference === option.value
                                    ? 'border-emerald-700 bg-emerald-700 text-white'
                                    : 'border-stone-200 bg-white text-stone-600 hover:border-emerald-300'"
                                @click="form.price_preference = option.value"
                            >
                                {{ option.label }}
                            </button>
                        </div>
                    </div>

                    <div class="mt-5 flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="rounded-full border px-4 py-2.5 text-sm font-semibold transition-colors focus:outline-none focus:ring-1 focus:ring-emerald-500"
                            :aria-pressed="form.required_5g"
                            :class="form.required_5g
                                ? 'border-emerald-700 bg-emerald-700 text-white'
                                : 'border-stone-200 bg-white text-stone-600 hover:border-emerald-300'"
                            @click="form.required_5g = !form.required_5g"
                        >
                            5G support
                        </button>
                        <button
                            type="button"
                            class="rounded-full border px-4 py-2.5 text-sm font-semibold transition-colors focus:outline-none focus:ring-1 focus:ring-emerald-500"
                            :aria-pressed="form.required_nfc"
                            :class="form.required_nfc
                                ? 'border-emerald-700 bg-emerald-700 text-white'
                                : 'border-stone-200 bg-white text-stone-600 hover:border-emerald-300'"
                            @click="form.required_nfc = !form.required_nfc"
                        >
                            NFC
                        </button>
                    </div>

                    <div class="mt-5">
                        <p class="text-sm font-medium text-stone-800">Minimum storage</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <button
                                v-for="option in storageOptions"
                                :key="option.label"
                                type="button"
                                class="rounded-full border px-4 py-2.5 text-sm font-semibold transition-colors focus:outline-none focus:ring-1 focus:ring-emerald-500"
                                :aria-pressed="form.required_storage_gb === option.value"
                                :class="form.required_storage_gb === option.value
                                    ? 'border-emerald-700 bg-emerald-700 text-white'
                                    : 'border-stone-200 bg-white text-stone-600 hover:border-emerald-300'"
                                @click="form.required_storage_gb = option.value"
                            >
                                {{ option.label }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- 4. What matters most to you -->
                <div v-else-if="currentStep === 3">
                    <h3 class="text-xl font-bold text-stone-900 sm:text-2xl">What matters most to you?</h3>
                    <p class="mt-1 text-[15px] text-stone-600">Rate each from 1 to 5. Leave as-is for anything you're not sure about.</p>
                    <p class="mt-2 flex items-center gap-2 text-xs font-medium text-stone-400">
                        <span>1 · Not important</span>
                        <span aria-hidden="true">—</span>
                        <span>5 · Very important</span>
                    </p>

                    <div class="mt-4 divide-y divide-stone-100">
                        <ImportanceRow
                            v-for="row in importanceRows"
                            :key="row.key"
                            :label="row.label"
                            :model-value="form.importance[row.key]"
                            @update:model-value="(level) => setImportance(row, level)"
                        />
                    </div>
                </div>

                <!-- 5. Brand preference -->
                <div v-else>
                    <h3 class="text-xl font-bold text-stone-900 sm:text-2xl">Any brand preference?</h3>
                    <p class="mt-1 text-[15px] text-stone-600">Both are optional. Tap a brand to prioritise or avoid it.</p>

                    <div class="mt-6 grid gap-5 sm:grid-cols-2">
                        <div>
                            <p class="text-sm font-medium text-stone-800">Brands you'd like us to prioritise</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <button
                                    v-for="brand in brands"
                                    :key="`preferred-${brand.id}`"
                                    type="button"
                                    class="rounded-full border px-4 py-2.5 text-sm font-semibold transition-colors focus:outline-none focus:ring-1 focus:ring-emerald-500"
                                    :aria-pressed="form.preferred_brand_ids.includes(brand.id)"
                                    :class="form.preferred_brand_ids.includes(brand.id)
                                        ? 'border-emerald-700 bg-emerald-700 text-white'
                                        : 'border-stone-200 bg-white text-stone-600 hover:border-emerald-300'"
                                    @click="toggleBrand(form.preferred_brand_ids, brand.id)"
                                >
                                    {{ brand.name }}
                                </button>
                            </div>
                        </div>

                        <div>
                            <p class="text-sm font-medium text-stone-800">Brands you'd like to avoid</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <button
                                    v-for="brand in brands"
                                    :key="`excluded-${brand.id}`"
                                    type="button"
                                    class="rounded-full border px-4 py-2.5 text-sm font-semibold transition-colors focus:outline-none focus:ring-1 focus:ring-red-400"
                                    :aria-pressed="form.excluded_brand_ids.includes(brand.id)"
                                    :class="form.excluded_brand_ids.includes(brand.id)
                                        ? 'border-red-600 bg-red-600 text-white'
                                        : 'border-stone-200 bg-white text-stone-600 hover:border-red-300'"
                                    @click="toggleBrand(form.excluded_brand_ids, brand.id)"
                                >
                                    {{ brand.name }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Back / Continue - the natural way through the wizard, not a
                     button the user has to go hunting for at the bottom of a
                     long page. -->
                <div class="mt-8 flex items-center gap-3 border-t border-stone-100 pt-6">
                    <button
                        v-if="!isFirstStep"
                        type="button"
                        class="inline-flex h-12 items-center justify-center rounded-full border border-stone-200 px-6 text-[15px] font-semibold text-stone-700 transition-colors hover:border-emerald-300 hover:text-emerald-700"
                        @click="goBack"
                    >
                        ← Back
                    </button>

                    <button
                        type="button"
                        class="ml-auto inline-flex h-12 items-center justify-center rounded-full bg-emerald-700 px-7 text-[15px] font-semibold text-white shadow-sm transition-colors hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="submitting"
                        @click="goNext"
                    >
                        <span v-if="submitting">Finding your matches…</span>
                        <span v-else-if="isLastStep">Find My Phone →</span>
                        <span v-else>Continue →</span>
                    </button>
                </div>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
.step-fade-enter-active,
.step-fade-leave-active {
    transition: opacity 0.15s ease, transform 0.15s ease;
}
.step-fade-enter-from {
    opacity: 0;
    transform: translateY(6px);
}
.step-fade-leave-to {
    opacity: 0;
    transform: translateY(-6px);
}
</style>
