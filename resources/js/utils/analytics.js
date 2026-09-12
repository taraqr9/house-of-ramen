// Google Analytics 4 (GA4) integration for the public Phone Kinbo site
// only - this module is imported from resources/js/app.js (the public
// site's client entry point), never from resources/js/ssr.js (no GA in
// the Node SSR process) and never referenced by the Bootstrap admin
// panel, which doesn't use Vite/Inertia at all (see CLAUDE.md).
//
// The Measurement ID comes from VITE_GA_MEASUREMENT_ID (see .env.example).
// It intentionally never appears hardcoded here: with it unset (the
// default for local dev), every function below silently no-ops, so local
// development never sends real traffic to GA4.
const MEASUREMENT_ID = import.meta.env.VITE_GA_MEASUREMENT_ID;

// Gate on a production build too, not just the presence of an ID - a
// local `npm run build` with a real ID pasted into a personal .env
// shouldn't start reporting local traffic either.
const ENABLED = Boolean(MEASUREMENT_ID) && import.meta.env.PROD;

let initialized = false;

/**
 * Loads gtag.js asynchronously and configures GA4 for SPA-style page
 * views. `send_page_view: false` disables gtag's own automatic page_view
 * (fired from a single, static <script> position) - page views are sent
 * manually instead, once per real Inertia navigation (see
 * trackPageview()/router.on('navigate', ...) in app.js), which is GA4's
 * documented approach for a client-rendered app and avoids counting
 * every partial Inertia prop update as a new page view.
 */
export function initAnalytics() {
    if (!ENABLED || initialized) return;
    initialized = true;

    window.dataLayer = window.dataLayer || [];
    window.gtag = function gtag() {
        // eslint-disable-next-line prefer-rest-params
        window.dataLayer.push(arguments);
    };

    const script = document.createElement('script');
    script.async = true;
    script.src = `https://www.googletagmanager.com/gtag/js?id=${MEASUREMENT_ID}`;
    document.head.appendChild(script);

    window.gtag('js', new Date());
    window.gtag('config', MEASUREMENT_ID, {
        send_page_view: false,
        // GA4 already truncates/hashes IPs by default in the EU; this
        // keeps the same behaviour everywhere rather than relying on
        // region detection - one less thing to get wrong on privacy.
        anonymize_ip: true,
    });
}

/**
 * One page_view per real Inertia page change (see the `navigate` event
 * wiring in app.js) - covers the initial load and every subsequent
 * visit, but not partial/prop-only reloads of the same URL (Inertia
 * itself doesn't fire `navigate` for those).
 */
export function trackPageview(path, title) {
    if (!ENABLED) return;

    window.gtag?.('event', 'page_view', sanitize({
        page_location: window.location.origin + path,
        page_path: path,
        page_title: title,
    }));
}

/**
 * Only string/number/boolean values are forwarded, undefined/null are
 * dropped, and arrays are flattened to a comma-joined string (GA4 event
 * parameters are scalars) - a cheap backstop against a caller
 * accidentally spreading a whole object (e.g. the raw questionnaire
 * form) into event params. Strings are capped at GA4's own 100-character
 * parameter value limit.
 */
function sanitize(params) {
    const clean = {};

    for (const [key, value] of Object.entries(params ?? {})) {
        if (value === null || value === undefined) continue;

        if (Array.isArray(value)) {
            clean[key] = value.join(',').slice(0, 100);
            continue;
        }

        if (typeof value === 'string') {
            clean[key] = value.slice(0, 100);
            continue;
        }

        if (typeof value === 'number' || typeof value === 'boolean') {
            clean[key] = value;
        }
    }

    return clean;
}

/**
 * Fire-and-forget custom event. Safe to call unconditionally from any
 * component - it's a no-op whenever analytics isn't enabled (no ID, or a
 * non-production build).
 */
export function trackEvent(name, params = {}) {
    if (!ENABLED) return;

    window.gtag?.('event', name, sanitize(params));
}
