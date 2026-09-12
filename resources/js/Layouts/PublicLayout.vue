<script setup>
import { computed, ref } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { jsonLdVNode } from '../utils/jsonLd';

const page = usePage();
const mobileMenuOpen = ref(false);

const navLinks = [
    { label: 'Home', href: '/' },
    { label: 'Menu', href: '/menu' },
    { label: 'Gallery', href: '/gallery' },
    { label: 'About', href: '/about' },
    { label: 'Contact', href: '/contact' },
];

// Every public page loads the one active Restaurant row and shares it as
// the `restaurant` Inertia prop (see App\Support\RestaurantPresenter) -
// reading it here means the header/footer never need it passed down
// explicitly from each page's own template.
const restaurant = computed(() => page.props.restaurant ?? {});

function isActive(href) {
    const path = page.url.split('?')[0];
    return href === '/' ? path === '/' : path.startsWith(href);
}

// Sitewide Organization + WebSite JSON-LD - present on every public page.
const siteJsonLd = computed(() => {
    const site = page.props.siteMeta ?? {};

    return [
        {
            '@context': 'https://schema.org',
            '@type': 'Restaurant',
            name: restaurant.value.name ?? site.organization_name,
            url: site.base_url,
            image: restaurant.value.cover_image_url,
            telephone: restaurant.value.phone,
            servesCuisine: ['Japanese', 'Korean', 'Ramen'],
            address: restaurant.value.address
                ? { '@type': 'PostalAddress', streetAddress: restaurant.value.address, addressLocality: restaurant.value.area }
                : undefined,
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

    <div class="flex min-h-screen flex-col bg-cream-50 text-charcoal-900">
        <a
            href="#main-content"
            class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:rounded-lg focus:bg-coral-600 focus:px-4 focus:py-2 focus:text-white"
        >
            Skip to content
        </a>

        <header class="sticky top-0 z-40 border-b border-coral-100 bg-cream-50/95 backdrop-blur">
            <div class="mx-auto flex h-18 max-w-6xl items-center gap-3 px-4 sm:px-6">
                <Link href="/" class="flex shrink-0 items-center gap-2.5 text-lg font-bold tracking-tight text-charcoal-900">
                    <img src="/images/restaurant/logo-icon.png" alt="" width="40" height="40" class="h-10 w-10" />
                    <span class="leading-none">House of Ramen</span>
                </Link>

                <nav class="hidden flex-1 items-center justify-center gap-1 md:flex" aria-label="Primary">
                    <Link
                        v-for="link in navLinks"
                        :key="link.href"
                        :href="link.href"
                        class="rounded-lg px-4 py-2 text-[15px] font-medium transition-colors"
                        :class="isActive(link.href) ? 'bg-coral-100 text-coral-700' : 'text-charcoal-900/70 hover:bg-coral-50 hover:text-charcoal-900'"
                        :aria-current="isActive(link.href) ? 'page' : undefined"
                    >
                        {{ link.label }}
                    </Link>
                </nav>

                <Link
                    href="/menu"
                    class="ml-auto hidden rounded-full bg-coral-500 px-5 py-2.5 text-[15px] font-semibold text-white shadow-sm transition-colors hover:bg-coral-600 md:inline-block"
                >
                    View Menu
                </Link>

                <div class="ml-auto flex md:hidden">
                    <button
                        type="button"
                        class="inline-flex h-11 w-11 items-center justify-center rounded-lg text-charcoal-900 hover:bg-coral-50"
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

            <nav
                v-show="mobileMenuOpen"
                id="mobile-nav"
                class="border-t border-coral-100 bg-cream-50 px-4 py-3 md:hidden"
                aria-label="Primary"
            >
                <Link
                    v-for="link in navLinks"
                    :key="link.href"
                    :href="link.href"
                    class="block rounded-lg px-3 py-3 text-base font-medium"
                    :class="isActive(link.href) ? 'bg-coral-100 text-coral-700' : 'text-charcoal-900/80 hover:bg-coral-50'"
                    :aria-current="isActive(link.href) ? 'page' : undefined"
                    @click="mobileMenuOpen = false"
                >
                    {{ link.label }}
                </Link>
                <Link
                    href="/menu"
                    class="mt-2 block rounded-full bg-coral-500 px-4 py-3 text-center text-base font-semibold text-white"
                    @click="mobileMenuOpen = false"
                >
                    View Menu
                </Link>
            </nav>
        </header>

        <main id="main-content" class="flex-1">
            <slot />
        </main>

        <footer class="border-t border-coral-100 bg-charcoal-900 text-cream-100">
            <div class="mx-auto max-w-6xl px-4 py-12 sm:px-6">
                <div class="grid gap-10 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="lg:col-span-2">
                        <div class="flex items-center gap-2.5 text-base font-bold text-white">
                            <img src="/images/restaurant/logo-icon.png" alt="" width="36" height="36" class="h-9 w-9" />
                            House of Ramen
                        </div>
                        <p v-if="restaurant.tagline" class="mt-3 max-w-sm text-sm leading-relaxed text-cream-100/70">
                            {{ restaurant.tagline }}
                        </p>
                        <div v-if="restaurant.delivery_platforms?.length" class="mt-4 flex flex-wrap gap-2">
                            <span class="text-xs text-cream-100/60">Order delivery via</span>
                            <span
                                v-for="platform in restaurant.delivery_platforms"
                                :key="platform"
                                class="rounded-full border border-cream-100/20 px-2.5 py-0.5 text-xs font-medium text-cream-100/80"
                            >
                                {{ platform }}
                            </span>
                        </div>
                        <div v-if="restaurant.facebook_url || restaurant.instagram_url" class="mt-4 flex gap-3">
                            <a v-if="restaurant.facebook_url" :href="restaurant.facebook_url" target="_blank" rel="noopener" class="text-cream-100/70 hover:text-white" aria-label="Facebook">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M22 12.06C22 6.5 17.52 2 12 2S2 6.5 2 12.06c0 5 3.66 9.15 8.44 9.94v-7.03H7.9v-2.9h2.54V9.85c0-2.5 1.49-3.89 3.78-3.89 1.1 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56v1.88h2.78l-.44 2.9h-2.34V22c4.78-.8 8.44-4.94 8.44-9.94Z"/></svg>
                            </a>
                            <a v-if="restaurant.instagram_url" :href="restaurant.instagram_url" target="_blank" rel="noopener" class="text-cream-100/70 hover:text-white" aria-label="Instagram">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2c2.72 0 3.06.01 4.12.06 1.06.05 1.79.22 2.43.47.66.26 1.21.6 1.76 1.15.55.55.9 1.1 1.15 1.76.25.64.42 1.37.47 2.43.05 1.06.06 1.4.06 4.13s-.01 3.06-.06 4.12c-.05 1.06-.22 1.79-.47 2.43a4.9 4.9 0 0 1-1.15 1.76 4.9 4.9 0 0 1-1.76 1.15c-.64.25-1.37.42-2.43.47-1.06.05-1.4.06-4.12.06s-3.06-.01-4.13-.06c-1.06-.05-1.79-.22-2.43-.47a4.9 4.9 0 0 1-1.76-1.15 4.9 4.9 0 0 1-1.15-1.76c-.25-.64-.42-1.37-.47-2.43C2.01 15.06 2 14.72 2 12s.01-3.06.06-4.12c.05-1.06.22-1.79.47-2.43.26-.66.6-1.21 1.15-1.76A4.9 4.9 0 0 1 5.44.54c.64-.25 1.37-.42 2.43-.47C8.93.02 9.28.01 12 .01Zm0 5.35A4.64 4.64 0 1 0 12 16.63 4.64 4.64 0 0 0 12 7.35Zm0 7.65a3 3 0 1 1 0-6 3 3 0 0 1 0 6Zm4.84-7.84a1.08 1.08 0 1 1 0-2.17 1.08 1.08 0 0 1 0 2.17Z"/></svg>
                            </a>
                        </div>
                    </div>

                    <nav aria-label="Footer">
                        <span class="text-sm font-semibold text-white">Explore</span>
                        <ul class="mt-3 flex flex-col gap-2 text-sm text-cream-100/70">
                            <li><Link href="/menu" class="hover:text-white">Menu</Link></li>
                            <li><Link href="/gallery" class="hover:text-white">Gallery</Link></li>
                            <li><Link href="/about" class="hover:text-white">About</Link></li>
                            <li><Link href="/contact" class="hover:text-white">Contact</Link></li>
                        </ul>
                    </nav>

                    <div>
                        <span class="text-sm font-semibold text-white">Visit Us</span>
                        <ul class="mt-3 flex flex-col gap-2 text-sm text-cream-100/70">
                            <li v-if="restaurant.address">{{ restaurant.address }}</li>
                            <li v-if="restaurant.area">{{ restaurant.area }}</li>
                            <li v-if="restaurant.phone">
                                <a :href="`tel:${restaurant.phone}`" class="hover:text-white">{{ restaurant.phone }}</a>
                            </li>
                            <li v-if="!restaurant.address">Location details coming soon.</li>
                        </ul>
                    </div>
                </div>

                <p class="mt-10 border-t border-white/10 pt-6 text-xs text-cream-100/50">
                    © {{ new Date().getFullYear() }} House of Ramen. All rights reserved.
                </p>
            </div>
        </footer>
    </div>
</template>
