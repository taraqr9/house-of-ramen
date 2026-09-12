<script setup>
import { Link } from '@inertiajs/vue3';
import PublicLayout from '../../Layouts/PublicLayout.vue';
import SeoHead from '../../Components/Public/SeoHead.vue';

defineOptions({ layout: PublicLayout });

defineProps({
    founderPhotoUrl: { type: String, required: true },
    seo: { type: Object, required: true },
});

const howItWorks = [
    {
        title: 'Find My Phone',
        body: 'Answer a few quick questions about your budget and what matters to you, and get phones matched to your actual needs.',
        href: '/#find-my-phone',
        cta: 'Try it now',
    },
    {
        title: 'Browse the Phone Catalogue',
        body: 'Explore every phone Phone Kinbo tracks in Bangladesh, filterable by brand and budget.',
        href: '/phones',
        cta: 'Browse phones',
    },
    {
        title: 'Compare Phones',
        body: 'Put phones side by side and see specs, performance, and pricing lined up column by column.',
        href: '/compare',
        cta: 'Compare phones',
    },
    {
        title: 'Check Official & Unofficial Market Prices',
        body: 'See official Bangladesh retailer pricing and unofficial/grey-market pricing shown separately, so you know exactly what you\'re comparing.',
        href: '/phones',
        cta: 'See current prices',
    },
];
</script>

<template>
    <SeoHead :seo="seo" />

    <!-- Hero -->
    <section class="border-b border-stone-200 bg-white">
        <div class="mx-auto max-w-6xl px-4 py-14 sm:px-6 sm:py-20">
            <div class="max-w-2xl">
                <p class="text-sm font-semibold tracking-wide text-emerald-700 uppercase">About Us</p>
                <h1 class="mt-1 text-2xl leading-tight font-bold text-stone-900 sm:text-3xl md:text-4xl">
                    About Phone Kinbo
                </h1>
                <p class="mt-4 text-base leading-relaxed text-stone-600 sm:text-lg">
                    Phone Kinbo is a Bangladesh-focused platform that helps you choose the right smartphone —
                    matched to your budget, your needs, and what you actually care about, using real prices and
                    specs from the Bangladesh market.
                </p>
            </div>
        </div>
    </section>

    <!-- Our Mission -->
    <section class="mx-auto max-w-6xl px-4 py-14 sm:px-6">
        <h2 class="text-2xl font-bold text-stone-900 sm:text-3xl">Our Mission</h2>
        <div class="mt-4 max-w-2xl space-y-4 leading-relaxed text-stone-600">
            <p>
                Buying a phone in Bangladesh means wading through scattered listings, inconsistent pricing, and
                specs that are hard to compare side by side. Phone Kinbo's mission is to make that simpler —
                so you can make an informed decision without hours of research.
            </p>
            <p>
                On Phone Kinbo, you can explore the phone catalogue, compare specifications and prices between
                models, and get a personalised recommendation based on your budget and priorities — all without
                creating an account.
            </p>
        </div>
    </section>

    <!-- How Phone Kinbo Works -->
    <section class="border-y border-stone-200 bg-stone-50">
        <div class="mx-auto max-w-6xl px-4 py-14 sm:px-6">
            <h2 class="text-2xl font-bold text-stone-900 sm:text-3xl">How Phone Kinbo Works</h2>
            <div class="mt-8 grid gap-6 sm:grid-cols-2">
                <div v-for="step in howItWorks" :key="step.title" class="flex flex-col rounded-2xl border border-stone-200 bg-white p-6">
                    <h3 class="text-base font-semibold text-stone-900">{{ step.title }}</h3>
                    <p class="mt-2 flex-1 text-sm leading-relaxed text-stone-600">{{ step.body }}</p>
                    <Link :href="step.href" class="mt-4 inline-flex items-center text-sm font-semibold text-emerald-700 hover:text-emerald-800">
                        {{ step.cta }} →
                    </Link>
                </div>
            </div>
        </div>
    </section>

    <!-- Data & Pricing -->
    <section class="mx-auto max-w-6xl px-4 py-14 sm:px-6">
        <h2 class="text-2xl font-bold text-stone-900 sm:text-3xl">Data &amp; Pricing</h2>
        <div class="mt-4 max-w-2xl space-y-4 leading-relaxed text-stone-600">
            <p>
                Prices and availability shown on Phone Kinbo are collected from public retailer listings and
                can vary between retailers and change over time. We do our best to keep them current, but we
                can't guarantee that every price is accurate at the exact moment you're browsing — always
                confirm the final price with the retailer before you buy.
            </p>
            <p>
                Where both exist for a phone, official Bangladesh retailer pricing and unofficial (grey-market
                import) pricing are shown separately, since they represent different purchases — an unofficial
                import typically has no manufacturer warranty in Bangladesh.
            </p>
        </div>
    </section>

    <!-- Founder -->
    <section class="border-y border-stone-200 bg-stone-50">
        <div class="mx-auto max-w-6xl px-4 py-14 sm:px-6">
            <h2 class="text-2xl font-bold text-stone-900 sm:text-3xl">Founder</h2>
            <div class="mt-8 flex flex-col items-center gap-6 sm:flex-row sm:items-start sm:gap-8">
                <img
                    :src="founderPhotoUrl"
                    alt="MD. Taraq Rahman"
                    width="160"
                    height="160"
                    class="h-32 w-32 shrink-0 rounded-full border border-stone-200 object-cover shadow-sm sm:h-40 sm:w-40"
                >
                <div class="text-center sm:text-left">
                    <h3 class="text-lg font-semibold text-stone-900">MD. Taraq Rahman</h3>
                    <p class="text-sm font-medium text-emerald-700">Founder &amp; Developer</p>
                    <p class="mt-3 max-w-xl leading-relaxed text-stone-600">
                        Taraq built Phone Kinbo to make smartphone research easier for buyers in Bangladesh —
                        from the recommendation engine to the phone catalogue and price tracking behind it.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact -->
    <section class="mx-auto max-w-6xl px-4 py-14 text-center sm:px-6">
        <h2 class="text-2xl font-bold text-stone-900 sm:text-3xl">Have a question or feedback?</h2>
        <p class="mt-3 text-stone-600">We'd like to hear from you.</p>
        <a
            href="mailto:taraqr9@gmail.com"
            class="mt-5 inline-flex items-center justify-center rounded-full border border-stone-200 px-6 py-3 text-[15px] font-semibold text-emerald-700 hover:border-emerald-300 hover:bg-emerald-50"
        >
            taraqr9@gmail.com
        </a>
    </section>

    <!-- CTA -->
    <section class="bg-emerald-700">
        <div class="mx-auto max-w-6xl px-4 py-14 text-center sm:px-6">
            <h2 class="text-2xl font-bold text-white sm:text-3xl">Ready to find your phone?</h2>
            <p class="mx-auto mt-2 max-w-md text-emerald-50">
                Tell us your budget and what matters to you, or browse the full catalogue yourself.
            </p>
            <div class="mt-6 flex flex-col justify-center gap-3 sm:flex-row">
                <Link
                    href="/#find-my-phone"
                    class="inline-flex items-center justify-center rounded-full bg-white px-7 py-3.5 text-base font-semibold text-emerald-800 shadow-sm transition-colors hover:bg-emerald-50"
                >
                    Find My Phone
                </Link>
                <Link
                    href="/phones"
                    class="inline-flex items-center justify-center rounded-full border border-emerald-500 px-7 py-3.5 text-base font-semibold text-white transition-colors hover:bg-emerald-600"
                >
                    Browse Phones
                </Link>
            </div>
        </div>
    </section>
</template>
