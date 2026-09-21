<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\ReservationStoreRequest;
use App\Models\RestaurantReservation;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\WhatsApp\CallMeBotNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;

class ReservationController extends Controller
{
    public function store(ReservationStoreRequest $request): RedirectResponse
    {
        $reservation = RestaurantReservation::create($request->validated());

        $when = $reservation->reservation_date->format('M j, Y').' at '.Carbon::parse($reservation->reservation_time)->format('g:i A');

        $summary = "New Reservation Request\n".
            "Name: {$reservation->name}\n".
            "Phone: {$reservation->phone}\n".
            "Party: {$reservation->party_size} people\n".
            "Date: {$when}\n".
            'Notes: '.($reservation->notes ?: '-');

        // Every admin sees it in the bell dropdown regardless of whether
        // WhatsApp is configured below - this always works, no setup needed.
        NotificationService::sendMany(
            User::role('Super Admin')->get(),
            'New Reservation Request',
            "{$reservation->name} - {$reservation->party_size} people on {$when}",
            route('restaurant-reservations.index'),
            'bx-calendar-check',
            'success',
        );

        // Best-effort only (see CallMeBotNotifier) - a slow/misconfigured
        // WhatsApp integration must never stop the customer's reservation
        // from going through.
        CallMeBotNotifier::send($summary);

        return back()->with('success', 'Reservation request received! We will call you shortly to confirm.');
    }
}
