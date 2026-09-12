<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import PublicLayout from '../../Layouts/PublicLayout.vue';
import ResultCard from '../../Components/Public/ResultCard.vue';
import FindMyPhoneForm from '../../Components/Public/FindMyPhoneForm.vue';
import SeoHead from '../../Components/Public/SeoHead.vue';
import { formatTaka } from '../../utils/format';
import { trackEvent } from '../../utils/analytics';
import { budgetRangeFor } from '../../utils/budget';

defineOptions({ layout: PublicLayout });

const props = defineProps({
    exampleResult: { type: Object, default: null },
    priceBrackets: { type: Array, default: () => [] },
    brands: { type: Array, default: () => [] },
    dimensions: { type: Array, default: () => [] },
    popularBrands: { type: Array, default: () => [] },
    popularSearches: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
    seo: { type: Object, required: true },
});

// "Enter your own amount" - kept as plain text so a stray comma/space
// (e.g. pasted as "20,000") doesn't fail the number check below; only
// digits are extracted, exactly the same convention as the Find My Phone
// wizard's own custom-budget field.
const MIN_BUDGET = 1000; // matches PhoneController::index()'s server-side rule

const customBudgetText = ref('');
const customBudgetError = ref('');

function onCustomBudgetInput(event) {
    customBudgetText.value = event.target.value.replace(/[^\d]/g, '').replace(/^0+(?=\d)/, '');
    customBudgetError.value = '';
}

function submitCustomBudget() {
    const amount = parseInt(customBudgetText.value, 10);

    if (!Number.isFinite(amount) || amount <= 0) {
        customBudgetError.value = 'Please enter an amount.';

        return;
    }

    if (amount < MIN_BUDGET) {
        customBudgetError.value = `Please enter at least ${formatTaka(MIN_BUDGET)}.`;

        return;
    }

    trackBudget(amount);
    router.visit(`/phones?max_budget=${amount}`);
}

function trackBudget(amount) {
    const { budget_min, budget_max, budget_range } = budgetRangeFor(amount, props.priceBrackets);
    trackEvent('budget_selected', { budget_min, budget_max, budget_range, source: 'home' });
}

const steps = [
    {
        title: 'Tell us your budget and needs',
        body: 'A few quick questions about what you want to spend and what matters most to you — gaming, camera, battery, whatever it is.',
    },
    {
        title: 'We compare real phones for you',
        body: 'Phone Kinbo checks current Bangladesh prices and specs against what you told us — no guesswork, no sponsored picks.',
    },
    {
        title: 'Get matches you can trust',
        body: 'See your best match plus a couple of solid alternatives, each with plain-language reasons so you know exactly why.',
    },
];
</script>

<template>
    <SeoHead :seo="seo" />

    <!-- Hero + primary CTA -->
    <section class="border-b border-stone-200 bg-white">
        <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 sm:py-10">
            <div class="max-w-2xl">
                <h1 class="text-2xl leading-tight font-bold text-stone-900 sm:text-3xl md:text-4xl">
                    Not sure which phone to buy?
                </h1>
                <p class="mt-3 text-base text-stone-600 sm:text-lg">
                    Tell us your budget and what matters to you. Phone Kinbo will find the phones that
                    fit you best — in a couple of minutes, with no sign-up needed.
                </p>

                <div class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-center">
                    <a
                        href="#find-my-phone"
                        class="inline-flex items-center justify-center rounded-full bg-emerald-700 px-7 py-3.5 text-base font-semibold text-white shadow-sm transition-colors hover:bg-emerald-800"
                    >
                        Find My Phone
                    </a>
                    <Link
                        href="/phones"
                        class="inline-flex items-center justify-center rounded-full px-7 py-3.5 text-base font-semibold text-stone-700 hover:text-emerald-700"
                    >
                        Or browse all phones →
                    </Link>
                </div>

                <p v-if="stats.phone_count" class="mt-4 text-sm text-stone-400">
                    Comparing {{ stats.phone_count }}+ phones from {{ stats.brand_count }} brands available in
                    Bangladesh.
                </p>
            </div>
        </div>
    </section>

    <!-- Find My Phone questionnaire, right here on the homepage -->
    <section id="find-my-phone" class="scroll-mt-6 bg-stone-50">
        <div class="mx-auto max-w-6xl px-4 py-14 sm:px-6">
            <h2 class="text-2xl font-bold text-stone-900 sm:text-3xl">Find My Phone</h2>
            <p class="mt-2 max-w-2xl text-stone-600">
                Not sure which phone is right for you? Answer a few quick questions and we'll recommend
                real phones available in Bangladesh that fit your needs — no sign-up needed.
            </p>

            <div class="mt-8">
                <FindMyPhoneForm :brands="brands" :price-brackets="priceBrackets" :dimensions="dimensions" />
            </div>
        </div>
    </section>

    <!-- How it works -->
    <section class="mx-auto max-w-6xl px-4 py-14 sm:px-6">
        <h2 class="text-2xl font-bold text-stone-900 sm:text-3xl">How it works</h2>
        <div class="mt-8 grid gap-6 sm:grid-cols-3">
            <div v-for="(step, index) in steps" :key="step.title" class="rounded-2xl border border-stone-200 bg-white p-6">
                <span
                    class="flex h-9 w-9 items-center justify-center rounded-full bg-emerald-50 text-sm font-bold text-emerald-700"
                    aria-hidden="true"
                >
                    {{ index + 1 }}
                </span>
                <h3 class="mt-4 text-base font-semibold text-stone-900">{{ step.title }}</h3>
                <p class="mt-2 text-sm leading-relaxed text-stone-600">{{ step.body }}</p>
            </div>
        </div>
    </section>

    <!-- Example result -->
    <section v-if="exampleResult" class="border-y border-stone-200 bg-white">
        <div class="mx-auto max-w-6xl px-4 py-14 sm:px-6">
            <h2 class="text-2xl font-bold text-stone-900 sm:text-3xl">See it in action</h2>
            <p class="mt-2 max-w-2xl text-stone-600">
                For example, someone with a {{ formatTaka(25000) }} budget who cares about camera and
                battery life the most might see this as their top match:
            </p>

            <div class="mt-6 max-w-md">
                <ResultCard :result="exampleResult" featured />
            </div>
        </div>
    </section>

    <!-- Discovery / popular categories -->
    <section class="mx-auto max-w-6xl px-4 py-14 sm:px-6">
        <h2 class="text-2xl font-bold text-stone-900 sm:text-3xl">Browse by budget</h2>
        <p class="mt-2 text-stone-600">Know roughly what you want to spend? Jump straight to a price range.</p>

        <!-- Enter your own amount - the primary path through this section,
             ahead of the predefined ranges below. -->
        <form class="mt-6 max-w-lg rounded-2xl border border-stone-200 bg-white p-5" @submit.prevent="submitCustomBudget">
            <label for="home-custom-budget" class="text-sm font-semibold text-stone-900">Enter your own amount</label>
            <div class="mt-2 flex flex-col gap-2 sm:flex-row">
                <div class="flex flex-1 items-center rounded-2xl border border-stone-200 bg-white px-4 py-3 focus-within:border-emerald-500 focus-within:ring-1 focus-within:ring-emerald-500">
                    <span class="mr-1 text-stone-400" aria-hidden="true">৳</span>
                    <input
                        id="home-custom-budget"
                        type="text"
                        inputmode="numeric"
                        autocomplete="off"
                        placeholder="e.g. 25,000"
                        class="w-full border-0 p-0 text-[15px] text-stone-900 placeholder:text-stone-400 focus:ring-0 focus:outline-none"
                        :value="customBudgetText"
                        @input="onCustomBudgetInput"
                    >
                </div>
                <button
                    type="submit"
                    class="inline-flex h-12 shrink-0 items-center justify-center rounded-full bg-emerald-700 px-6 text-[15px] font-semibold text-white shadow-sm transition-colors hover:bg-emerald-800"
                >
                    See phones →
                </button>
            </div>
            <p v-if="customBudgetError" class="mt-2 text-sm font-medium text-red-600">{{ customBudgetError }}</p>
            <p v-else class="mt-2 text-sm text-stone-400">We'll show every phone at or under this price.</p>
        </form>

        <p class="mt-8 text-sm font-semibold text-stone-500">Or pick a common range</p>
        <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <Link
                v-for="bracket in priceBrackets"
                :key="bracket.label"
                :href="bracket.max_budget ? `/phones?max_budget=${bracket.max_budget}` : '/phones'"
                class="flex items-center justify-between rounded-2xl border border-stone-200 bg-white px-5 py-4 transition-colors hover:border-emerald-300 hover:bg-emerald-50/40"
                @click="bracket.max_budget && trackBudget(bracket.max_budget)"
            >
                <span class="font-semibold text-stone-900">{{ bracket.label }}</span>
                <span class="text-sm text-stone-400">{{ bracket.count }} phones</span>
            </Link>
        </div>
    </section>

    <!-- Discovery / popular brands -->
    <section v-if="popularBrands.length" class="mx-auto max-w-6xl px-4 py-14 sm:px-6">
        <h2 class="text-2xl font-bold text-stone-900 sm:text-3xl">Browse by brand</h2>
        <p class="mt-2 text-stone-600">Looking for a specific brand? Jump straight to its lineup.</p>

        <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <Link
                v-for="brand in popularBrands"
                :key="brand.slug"
                :href="`/phones/brand/${brand.slug}`"
                class="flex items-center justify-between rounded-2xl border border-stone-200 bg-white px-5 py-4 transition-colors hover:border-emerald-300 hover:bg-emerald-50/40"
            >
                <span class="font-semibold text-stone-900">{{ brand.name }}</span>
                <span class="text-sm text-stone-400">{{ brand.count }} phones</span>
            </Link>
        </div>
    </section>

    <!-- Discovery / popular searches -->
    <section v-if="popularSearches.length" class="mx-auto max-w-6xl px-4 py-14 sm:px-6">
        <h2 class="text-2xl font-bold text-stone-900 sm:text-3xl">Popular searches</h2>
        <p class="mt-2 text-stone-600">Curated picks for common budgets and needs, updated with today's prices.</p>

        <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <Link
                v-for="search in popularSearches"
                :key="search.slug"
                :href="`/${search.slug}`"
                class="rounded-2xl border border-stone-200 bg-white px-5 py-4 font-semibold text-stone-900 transition-colors hover:border-emerald-300 hover:bg-emerald-50/40"
            >
                {{ search.heading }}
            </Link>
        </div>
    </section>

    <!-- Trust / data explanation -->
    <section class="border-t border-stone-200 bg-white">
        <div class="mx-auto max-w-6xl px-4 py-14 sm:px-6">
            <div class="max-w-2xl">
                <h2 class="text-2xl font-bold text-stone-900 sm:text-3xl">Where this data comes from</h2>
                <p class="mt-3 leading-relaxed text-stone-600">
                    Phone Kinbo tracks specs and current Bangladesh prices for each phone and scores them
                    against what you say matters to you. There are no sponsored placements or paid
                    rankings — a match is shown because it fits your answers, not because a retailer paid
                    for it.
                </p>
            </div>
        </div>
    </section>

    <!-- Final CTA -->
    <section class="bg-emerald-700">
        <div class="mx-auto max-w-6xl px-4 py-14 text-center sm:px-6">
            <h2 class="text-2xl font-bold text-white sm:text-3xl">Ready to find your phone?</h2>
            <p class="mx-auto mt-2 max-w-md text-emerald-50">
                It takes about two minutes, and you'll see real matches from the current market.
            </p>
            <a
                href="#find-my-phone"
                class="mt-6 inline-flex items-center justify-center rounded-full bg-white px-7 py-3.5 text-base font-semibold text-emerald-800 shadow-sm transition-colors hover:bg-emerald-50"
            >
                Find My Phone
            </a>
        </div>
    </section>
</template>
