import { createInertiaApp } from '@inertiajs/vue3';
import createServer from '@inertiajs/vue3/server';
import { renderToString } from '@vue/server-renderer';
import { createSSRApp, h } from 'vue';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';

// Server-side render entry point (see php artisan inertia:start-ssr /
// config/inertia.php). Without this, per-page <title>/meta/canonical/OG/
// JSON-LD set via <Head> (see Components/Public/SeoHead.vue) only exist
// after client-side hydration - invisible to crawlers and scrapers that
// don't execute JS (most social-card scrapers, some SEO tooling). This
// mirrors resources/js/app.js's client setup exactly, swapping createApp
// for createSSRApp and rendering to a string instead of mounting to the DOM.
createServer((page) =>
    createInertiaApp({
        page,
        render: renderToString,
        title: (title) => (title ? `${title} — House of Ramen` : 'House of Ramen — Modern Ramen & Japanese-Korean Comfort Food in Dhaka'),
        resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
        setup({ App, props, plugin }) {
            return createSSRApp({ render: () => h(App, props) }).use(plugin);
        },
    }),
);
