<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import PhoneImage from './PhoneImage.vue';
import { formatTaka } from '../../utils/format';
import { trackEvent } from '../../utils/analytics';

const props = defineProps({
    // Rendered as a full-width bar (mobile, below the logo row) vs a
    // fixed-width inline field (desktop, between the logo and nav).
    variant: { type: String, default: 'inline' }, // 'inline' | 'full'
});

const MIN_CHARS = 2;
const DEBOUNCE_MS = 300;

const query = ref('');
const results = ref([]);
const status = ref('idle'); // idle | loading | done | error
const open = ref(false);
const activeIndex = ref(-1);
const root = ref(null);

let debounceTimer = null;
let requestToken = 0;

function onInput() {
    open.value = true;
    activeIndex.value = -1;

    clearTimeout(debounceTimer);

    const term = query.value.trim();

    if (term.length < MIN_CHARS) {
        status.value = 'idle';
        results.value = [];

        return;
    }

    debounceTimer = setTimeout(() => runSearch(term), DEBOUNCE_MS);
}

async function runSearch(term) {
    const token = ++requestToken;
    status.value = 'loading';

    try {
        const response = await fetch(`/phones/search?q=${encodeURIComponent(term)}`, {
            headers: { Accept: 'application/json' },
        });

        if (token !== requestToken) return; // a newer keystroke already superseded this request

        if (!response.ok) {
            status.value = 'error';
            results.value = [];

            return;
        }

        const data = await response.json();

        if (token !== requestToken) return;

        results.value = data.results ?? [];
        status.value = 'done';
        trackEvent('search', { search_term: term, result_count: results.value.length });
    } catch {
        if (token === requestToken) {
            status.value = 'error';
            results.value = [];
        }
    }
}

function goTo(slug) {
    open.value = false;
    query.value = '';
    results.value = [];
    router.visit(`/phones/${slug}`);
}

function onKeydown(event) {
    if (!open.value || results.value.length === 0) return;

    if (event.key === 'ArrowDown') {
        event.preventDefault();
        activeIndex.value = (activeIndex.value + 1) % results.value.length;
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        activeIndex.value = (activeIndex.value - 1 + results.value.length) % results.value.length;
    } else if (event.key === 'Enter' && activeIndex.value >= 0) {
        event.preventDefault();
        goTo(results.value[activeIndex.value].slug);
    } else if (event.key === 'Escape') {
        open.value = false;
    }
}

function onFocus() {
    if (query.value.trim().length >= MIN_CHARS) open.value = true;
}

function onClickOutside(event) {
    if (root.value && !root.value.contains(event.target)) {
        open.value = false;
    }
}

onMounted(() => document.addEventListener('click', onClickOutside));
onBeforeUnmount(() => {
    document.removeEventListener('click', onClickOutside);
    clearTimeout(debounceTimer);
});

const showDropdown = () => open.value && query.value.trim().length >= MIN_CHARS;
</script>

<template>
    <div ref="root" class="relative" :class="variant === 'full' ? 'w-full' : 'w-full max-w-xs'">
        <div class="relative">
            <svg
                class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-stone-400"
                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"
            >
                <circle cx="11" cy="11" r="7" />
                <path stroke-linecap="round" d="M21 21l-4.35-4.35" />
            </svg>
            <input
                v-model="query"
                type="search"
                role="combobox"
                :aria-expanded="showDropdown()"
                aria-controls="header-search-results"
                aria-autocomplete="list"
                autocomplete="off"
                placeholder="Search phones, e.g. Galaxy A55"
                class="h-10 w-full rounded-full border border-stone-200 bg-white pl-9 pr-3 text-sm text-stone-900 placeholder:text-stone-400 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 focus:outline-none"
                @input="onInput"
                @focus="onFocus"
                @keydown="onKeydown"
            >
        </div>

        <div
            v-if="showDropdown()"
            id="header-search-results"
            role="listbox"
            class="absolute top-full left-0 z-50 mt-2 max-h-96 w-full min-w-72 overflow-y-auto rounded-2xl border border-stone-200 bg-white p-2 shadow-lg"
        >
            <p v-if="status === 'loading'" class="px-3 py-4 text-sm text-stone-400">Searching…</p>

            <p v-else-if="status === 'error'" class="px-3 py-4 text-sm text-stone-400">
                Something went wrong. Please try again.
            </p>

            <p v-else-if="status === 'done' && results.length === 0" class="px-3 py-6 text-center text-sm text-stone-500">
                No phones found for "{{ query }}".
            </p>

            <ul v-else-if="results.length" class="flex flex-col gap-1">
                <li v-for="(result, index) in results" :key="result.slug" role="option" :aria-selected="index === activeIndex">
                    <Link
                        :href="`/phones/${result.slug}`"
                        class="flex items-center gap-3 rounded-xl px-2 py-2 transition-colors"
                        :class="index === activeIndex ? 'bg-emerald-50' : 'hover:bg-stone-50'"
                        @mouseenter="activeIndex = index"
                        @click="open = false"
                    >
                        <PhoneImage :src="result.image_url" :label="result.name" size="sm" />
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-semibold text-stone-900">{{ result.name }}</span>
                            <span class="block text-xs text-stone-400">{{ result.brand }}</span>
                        </span>
                        <span class="shrink-0 text-sm font-semibold text-emerald-700">{{ formatTaka(result.price) }}</span>
                    </Link>
                </li>
            </ul>
        </div>
    </div>
</template>
