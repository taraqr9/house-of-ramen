<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $page_title = 'Notifications';

        $query = auth()->user()->notifications();

        if ($request->filled('status') && $request->status === 'unread') {
            $query->whereNull('read_at');
        } elseif ($request->filled('status') && $request->status === 'read') {
            $query->whereNotNull('read_at');
        }

        $notifications = $query->latest()->paginate(20)->withQueryString();

        return view('notifications.index', compact('page_title', 'notifications'));
    }

    public function markAllRead(): RedirectResponse
    {
        auth()->user()->unreadNotifications->markAsRead();

        return back()->with('message', 'All notifications marked as read.');
    }

    public function read(string $notification): Response
    {
        $notification = auth()->user()->notifications()->findOrFail($notification);

        $notification->markAsRead();

        return response()->noContent();
    }
}
