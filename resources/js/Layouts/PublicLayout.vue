<script setup>
import { computed, ref } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { jsonLdVNode } from '../utils/jsonLd';
import HeaderSearch from '../Components/Public/HeaderSearch.vue';

const page = usePage();
const mobileMenuOpen = ref(false);

const navLinks = [
    { label: 'Find My Phone', href: '/#find-my-phone' },
    { label: 'Phones', href: '/phones' },
    { label: 'Compare', href: '/compare' },
    { label: 'About', href: '/about' },
];

function isActive(href) {
    const path = page.url.split('?')[0];
    return href === '/' ? path === '/' : path.startsWith(href);
}

// Sitewide Organization + WebSite JSON-LD - present on every public page
// (repeating it per page is normal/expected for these two schema types,
// unlike Product/BreadcrumbList which are genuinely page-specific).
//
// `siteMeta` is shared globally (see HandleInertiaRequests, appended in
// bootstrap/app.php) so it should always be present, but this layout also
// wraps the error page rendered from the exception handler's respond()
// callback for a request that matched no route at all - the one path
// where "should always be present" is worth not fully trusting. A single
// bad render here doesn't just break one page: it throws inside the SSR
// process (see resources/js/ssr.js) with no per-request error boundary,
// which was observed to crash the entire Node SSR server, taking every
// visitor's SSR down until it's manually restarted - so this and the
// other three page.props.siteMeta reads (Error.vue, Breadcrumbs.vue,
// Pages/Public/SeoLanding/Show.vue) fall back rather than throw.
const siteJsonLd = computed(() => {
    const site = page.props.siteMeta ?? {};

    return [
        {
            '@context': 'https://schema.org',
            '@type': 'Organization',
            name: site.organization_name,
            url: site.base_url,
            logo: site.default_og_image,
        },
        {
            '@context': 'https://schema.org',
            '@type': 'WebSite',
            name: site.site_name,
            url: site.base_url,
        },
    ];
});
</script>

<template>
    <Head>
        <component
            v-for="entry in siteJsonLd"
            :key="entry['@type']"
            :is="jsonLdVNode(entry, `json-ld-${entry['@type']}`)"
        />
    </Head>

    <div class="flex min-h-screen flex-col bg-stone-50">
        <a
            href="#main-content"
            class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:rounded-lg focus:bg-emerald-700 focus:px-4 focus:py-2 focus:text-white"
        >
            Skip to content
        </a>

        <header class="sticky top-0 z-40 border-b border-stone-200 bg-stone-50/90 backdrop-blur">
            <div class="mx-auto flex h-16 max-w-6xl items-center gap-3 px-4 sm:px-6">
                <Link href="/" class="flex shrink-0 items-center gap-2 text-lg font-semibold text-stone-900">
                    <svg viewBox="0 0 56 38" width="34" height="23" aria-hidden="true">
                        <rect x="8" y="0" width="40" height="10" rx="5" fill="#BFDDD1" />
                        <rect x="0" y="14" width="56" height="10" rx="5" fill="#00845A" />
                        <rect x="8" y="28" width="40" height="10" rx="5" fill="#BFDDD1" />
                    </svg>
                    <span class="hidden sm:inline">Phone Kinbo</span>
                </Link>

                <!-- Desktop search sits right after the logo; mobile gets its
                     own full-width row below so it never fights the logo/
                     hamburger for space on a narrow screen. -->
                <div class="hidden flex-1 justify-center md:flex">
                    <HeaderSearch variant="inline" />
                </div>

                <nav class="hidden items-center gap-1 md:flex" aria-label="Primary">
                    <Link
                        v-for="link in navLinks"
                        :key="link.href"
                        :href="link.href"
                        class="rounded-lg px-4 py-2 text-[15px] font-medium transition-colors"
                        :class="isActive(link.href) ? 'bg-emerald-50 text-emerald-800' : 'text-stone-600 hover:bg-stone-100 hover:text-stone-900'"
                        :aria-current="isActive(link.href) ? 'page' : undefined"
                    >
                        {{ link.label }}
                    </Link>
                </nav>

                <Link
                    href="/#find-my-phone"
                    class="hidden rounded-full bg-emerald-700 px-5 py-2.5 text-[15px] font-semibold text-white shadow-sm transition-colors hover:bg-emerald-800 md:inline-block"
                >
                    Find My Phone
                </Link>

                <div class="flex flex-1 justify-end md:hidden">
                    <button
                        type="button"
                        class="inline-flex h-11 w-11 items-center justify-center rounded-lg text-stone-700 hover:bg-stone-100"
                        :aria-expanded="mobileMenuOpen"
                        aria-controls="mobile-nav"
                        aria-label="Toggle menu"
                        @click="mobileMenuOpen = !mobileMenuOpen"
                    >
                        <svg v-if="!mobileMenuOpen" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                        <svg v-else class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            <div class="border-t border-stone-200 px-4 py-3 sm:px-6 md:hidden">
                <HeaderSearch variant="full" />
            </div>

            <nav
                v-show="mobileMenuOpen"
                id="mobile-nav"
                class="border-t border-stone-200 bg-white px-4 py-3 md:hidden"
                aria-label="Primary"
            >
                <Link
                    v-for="link in navLinks"
                    :key="link.href"
                    :href="link.href"
                    class="block rounded-lg px-3 py-3 text-base font-medium"
                    :class="isActive(link.href) ? 'bg-emerald-50 text-emerald-800' : 'text-stone-700 hover:bg-stone-100'"
                    :aria-current="isActive(link.href) ? 'page' : undefined"
                    @click="mobileMenuOpen = false"
                >
                    {{ link.label }}
                </Link>
                <Link
                    href="/#find-my-phone"
                    class="mt-2 block rounded-full bg-emerald-700 px-4 py-3 text-center text-base font-semibold text-white"
                    @click="mobileMenuOpen = false"
                >
                    Find My Phone
                </Link>
            </nav>
        </header>

        <main id="main-content" class="flex-1">
            <slot />
        </main>

        <footer class="border-t border-stone-200 bg-white">
            <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6">
                <div class="flex flex-col gap-8 sm:flex-row sm:justify-between">
                    <div class="max-w-sm">
                        <div class="flex items-center gap-2 text-base font-semibold text-stone-900">
                            <svg viewBox="0 0 56 38" width="30" height="20" aria-hidden="true">
                                <rect x="8" y="0" width="40" height="10" rx="5" fill="#BFDDD1" />
                                <rect x="0" y="14" width="56" height="10" rx="5" fill="#00845A" />
                                <rect x="8" y="28" width="40" height="10" rx="5" fill="#BFDDD1" />
                            </svg>
                            Phone Kinbo
                        </div>
                        <p class="mt-3 text-sm leading-relaxed text-stone-500">
                            A simple way to find a phone that fits your budget and what you actually
                            need it for — built for buyers in Bangladesh.
                        </p>
                    </div>

                    <nav class="flex gap-8 text-sm" aria-label="Footer">
                        <div class="flex flex-col gap-2">
                            <span class="font-semibold text-stone-900">Explore</span>
                            <Link href="/#find-my-phone" class="text-stone-500 hover:text-emerald-700">Find My Phone</Link>
                            <Link href="/phones" class="text-stone-500 hover:text-emerald-700">Browse phones</Link>
                            <Link href="/compare" class="text-stone-500 hover:text-emerald-700">Compare phones</Link>
                            <Link href="/about" class="text-stone-500 hover:text-emerald-700">About us</Link>
                        </div>
                    </nav>
                </div>

                <p class="mt-8 border-t border-stone-100 pt-6 text-xs text-stone-400">
                    © {{ new Date().getFullYear() }} Phone Kinbo. Prices and specifications are collected
                    from public listings and may change without notice.
                </p>
            </div>
        </footer>
    </div>
</template>
