<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    phone: { type: String, default: null },
});

const form = useForm({
    name: '',
    phone: '',
    email: '',
    party_size: 2,
    reservation_date: '',
    reservation_time: '',
    notes: '',
});

const today = computed(() => new Date().toISOString().slice(0, 10));

// Hourly dine-in slots, noon through last seating at 10pm - the value
// sent to the backend is the 24h start time (matches the ReservationStoreRequest's
// `date_format:H:i` rule), the label is the customer-facing hour range.
const timeSlots = [
    { value: '12:00', label: '12:00 PM - 1:00 PM' },
    { value: '13:00', label: '1:00 PM - 2:00 PM' },
    { value: '14:00', label: '2:00 PM - 3:00 PM' },
    { value: '15:00', label: '3:00 PM - 4:00 PM' },
    { value: '16:00', label: '4:00 PM - 5:00 PM' },
    { value: '17:00', label: '5:00 PM - 6:00 PM' },
    { value: '18:00', label: '6:00 PM - 7:00 PM' },
    { value: '19:00', label: '7:00 PM - 8:00 PM' },
    { value: '20:00', label: '8:00 PM - 9:00 PM' },
    { value: '21:00', label: '9:00 PM - 10:00 PM' },
    { value: '22:00', label: '10:00 PM - 11:00 PM' },
];

function submit() {
    form.post('/reservations', {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}
</script>

<template>
    <div class="mx-auto max-w-2xl rounded-2xl bg-white p-6 shadow-sm ring-1 ring-charcoal-900/5 sm:p-8">
        <div v-if="form.recentlySuccessful" class="mb-6 rounded-xl bg-green-50 p-4 text-sm text-green-800" role="status">
            Reservation request received! We'll call you shortly to confirm.
        </div>

        <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="submit">
            <div class="sm:col-span-1">
                <label for="res-name" class="block text-sm font-medium text-charcoal-900">Full Name</label>
                <input
                    id="res-name"
                    v-model="form.name"
                    type="text"
                    required
                    class="mt-1 w-full rounded-lg border border-charcoal-900/15 px-3 py-2 text-charcoal-900 focus:border-coral-500 focus:ring-coral-500"
                />
                <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
            </div>

            <div class="sm:col-span-1">
                <label for="res-phone" class="block text-sm font-medium text-charcoal-900">Phone</label>
                <input
                    id="res-phone"
                    v-model="form.phone"
                    type="tel"
                    required
                    class="mt-1 w-full rounded-lg border border-charcoal-900/15 px-3 py-2 text-charcoal-900 focus:border-coral-500 focus:ring-coral-500"
                />
                <p v-if="form.errors.phone" class="mt-1 text-xs text-red-600">{{ form.errors.phone }}</p>
            </div>

            <div class="sm:col-span-1">
                <label for="res-email" class="block text-sm font-medium text-charcoal-900">Email <span class="text-charcoal-900/40">(optional)</span></label>
                <input
                    id="res-email"
                    v-model="form.email"
                    type="email"
                    class="mt-1 w-full rounded-lg border border-charcoal-900/15 px-3 py-2 text-charcoal-900 focus:border-coral-500 focus:ring-coral-500"
                />
                <p v-if="form.errors.email" class="mt-1 text-xs text-red-600">{{ form.errors.email }}</p>
            </div>

            <div class="sm:col-span-1">
                <label for="res-party" class="block text-sm font-medium text-charcoal-900">Number of People</label>
                <select
                    id="res-party"
                    v-model.number="form.party_size"
                    required
                    class="mt-1 w-full rounded-lg border border-charcoal-900/15 px-3 py-2 text-charcoal-900 focus:border-coral-500 focus:ring-coral-500"
                >
                    <option v-for="n in 20" :key="n" :value="n">{{ n }} {{ n === 1 ? 'person' : 'people' }}</option>
                </select>
                <p v-if="form.errors.party_size" class="mt-1 text-xs text-red-600">{{ form.errors.party_size }}</p>
            </div>

            <div class="sm:col-span-1">
                <label for="res-date" class="block text-sm font-medium text-charcoal-900">Date</label>
                <input
                    id="res-date"
                    v-model="form.reservation_date"
                    type="date"
                    :min="today"
                    required
                    class="mt-1 w-full rounded-lg border border-charcoal-900/15 px-3 py-2 text-charcoal-900 focus:border-coral-500 focus:ring-coral-500"
                />
                <p v-if="form.errors.reservation_date" class="mt-1 text-xs text-red-600">{{ form.errors.reservation_date }}</p>
            </div>

            <div class="sm:col-span-1">
                <label for="res-time" class="block text-sm font-medium text-charcoal-900">Time</label>
                <select
                    id="res-time"
                    v-model="form.reservation_time"
                    required
                    class="mt-1 w-full rounded-lg border border-charcoal-900/15 px-3 py-2 text-charcoal-900 focus:border-coral-500 focus:ring-coral-500"
                >
                    <option value="" disabled>Select a time</option>
                    <option v-for="slot in timeSlots" :key="slot.value" :value="slot.value">{{ slot.label }}</option>
                </select>
                <p v-if="form.errors.reservation_time" class="mt-1 text-xs text-red-600">{{ form.errors.reservation_time }}</p>
            </div>

            <div class="sm:col-span-2">
                <label for="res-notes" class="block text-sm font-medium text-charcoal-900">Special Requests <span class="text-charcoal-900/40">(optional)</span></label>
                <textarea
                    id="res-notes"
                    v-model="form.notes"
                    rows="3"
                    class="mt-1 w-full rounded-lg border border-charcoal-900/15 px-3 py-2 text-charcoal-900 focus:border-coral-500 focus:ring-coral-500"
                    placeholder="e.g. window seat, birthday celebration, allergies"
                ></textarea>
                <p v-if="form.errors.notes" class="mt-1 text-xs text-red-600">{{ form.errors.notes }}</p>
            </div>

            <div class="sm:col-span-2">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full rounded-full bg-coral-500 px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-coral-600 disabled:cursor-not-allowed disabled:opacity-60 sm:text-base"
                >
                    {{ form.processing ? 'Sending...' : 'Request Reservation' }}
                </button>
                <p v-if="phone" class="mt-3 text-center text-xs text-charcoal-900/50">
                    Prefer to call? <a :href="`tel:${phone}`" class="font-medium text-coral-600 hover:underline">{{ phone }}</a>
                </p>
            </div>
        </form>
    </div>
</template>
