<script setup>
import { onBeforeUnmount, watch } from 'vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    phone: { type: String, default: null },
});

const emit = defineEmits(['close']);

const features = [
    { icon: '🔥', text: 'Freshly prepared' },
    { icon: '🍜', text: 'Delivered hot & ready to enjoy' },
    { icon: '🛵', text: 'Our own personal delivery service' },
    { icon: '❤️', text: 'The same taste you love, now at home!' },
];

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            document.addEventListener('keydown', onKeydown);
            document.body.style.overflow = 'hidden';
        } else {
            document.removeEventListener('keydown', onKeydown);
            document.body.style.overflow = '';
        }
    },
);

onBeforeUnmount(() => {
    document.removeEventListener('keydown', onKeydown);
    document.body.style.overflow = '';
});

function close() {
    emit('close');
}

function onKeydown(event) {
    if (event.key === 'Escape') close();
}
</script>

<template>
    <div
        v-if="open"
        class="fixed inset-0 z-[60] flex items-center justify-center bg-charcoal-900/80 p-4 backdrop-blur-sm"
        role="dialog"
        aria-modal="true"
        aria-label="Free delivery in Uttara"
        @click.self="close"
    >
        <button
            type="button"
            class="fixed top-4 right-4 z-20 flex h-10 w-10 items-center justify-center rounded-full bg-charcoal-900/60 text-white backdrop-blur transition hover:bg-charcoal-900/80"
            aria-label="Close"
            @click="close"
        >
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
        </button>

        <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white shadow-2xl">
            <img
                src="/images/restaurant/free-delivery.jpg"
                alt="Free delivery in Uttara - Sectors 7, 9, 10, 11, 12, 13 & 14"
                loading="eager"
                class="w-full"
            />

            <div class="px-6 py-6 sm:px-8">
                <h2 class="text-center text-xl font-bold text-charcoal-900">
                    🍜 Ramen, Now At Your Doorstep! 🛵🔥
                </h2>
                <p class="mt-3 text-center text-sm leading-relaxed text-charcoal-900/80">
                    Your favorite ramen just got easier to enjoy! ❤️ We're excited to announce that House of Ramen
                    now offers our very own <strong>PERSONAL DELIVERY SERVICE</strong>! 🎉 No third-party hassle, no
                    complicated process — just fresh, hot, delicious ramen delivered straight from our kitchen to
                    your doorstep. 🍜🥢
                </p>

                <h3 class="mt-5 text-sm font-bold text-coral-600">✨ Why order directly from us?</h3>
                <ul class="mt-2 space-y-1.5">
                    <li v-for="feature in features" :key="feature.text" class="flex items-center gap-2 text-sm text-charcoal-900/80">
                        <span aria-hidden="true">{{ feature.icon }}</span>
                        {{ feature.text }}
                    </li>
                </ul>

                <div class="mt-5 rounded-xl bg-coral-50 p-4 text-sm text-charcoal-900">
                    <p>📍 <strong>FREE DELIVERY</strong> in Uttara — Sector 7, 9, 10, 11, 12, 13 &amp; 14</p>
                    <p class="mt-1.5">🛵 Delivery charge applies to Sector 3 &amp; 5</p>
                </div>

                <a
                    v-if="phone"
                    :href="`tel:${phone}`"
                    class="mt-5 block rounded-full bg-coral-500 px-5 py-3 text-center text-sm font-semibold text-white shadow-sm transition hover:bg-coral-600"
                >
                    📞 Call/Inbox us to place your order: {{ phone }}
                </a>

                <p class="mt-4 text-center text-xs text-charcoal-900/50">Terms &amp; Conditions Apply.</p>

                <p class="mt-4 text-center text-sm italic text-charcoal-900/70">
                    Because sometimes, you don't need to go out for your favourite items…
                    <br />
                    <span class="font-semibold text-coral-600 not-italic">RAMEN COMES TO YOU! 🍜🛵❤️</span>
                </p>
            </div>
        </div>
    </div>
</template>
