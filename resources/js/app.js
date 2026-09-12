import { createApp, h } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { initAnalytics, trackPageview } from './utils/analytics';

initAnalytics();

// Fires once per real Inertia page change - the initial load and every
// subsequent visit (including browser back/forward), but not a
// preserve-state reload of the same URL (Inertia only fires `navigate`
// when the URL actually changes) - see utils/analytics.js for why this
// pairs with `send_page_view: false` instead of gtag's own automatic
// page_view.
router.on('navigate', (event) => {
    const page = event.detail.page;
    trackPageview(page.url, document.title);
});

createInertiaApp({
    title: (title) => (title ? `${title} — Phone Kinbo` : 'Phone Kinbo — Find the right phone for you'),
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
});
