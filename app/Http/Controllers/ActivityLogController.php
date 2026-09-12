<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(auth()->user()->can('activity_log-view'), 403);

        $page_title = 'Activity Logs';

        $query = Activity::query()
            ->with('causer')
            ->latest();

        if ($request->filled('log_name')) {
            $query->where('log_name', $request->log_name);
        }

        if ($request->filled('event')) {
            $query->where('event', $request->event);
        }

        if ($request->filled('description')) {
            $query->where('description', 'like', '%'.$request->description.'%');
        }

        if ($request->filled('causer')) {
            $query->whereHasMorph(
                'causer',
                ['App\Models\User'],
                function ($q) use ($request) {
                    $q->where('name', 'like', '%'.$request->causer.'%')
                        ->orWhere('username', 'like', '%'.$request->causer.'%')
                        ->orWhere('email', 'like', '%'.$request->causer.'%');
                }
            );
        }

        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $logs = $query->paginate(30)->appends($request->query());

        $logNames = Activity::query()
            ->whereNotNull('log_name')
            ->select('log_name')
            ->distinct()
            ->orderBy('log_name')
            ->pluck('log_name');

        $events = Activity::query()
            ->whereNotNull('event')
            ->select('event')
            ->distinct()
            ->orderBy('event')
            ->pluck('event');

        return view('logs.activity', compact(
            'page_title',
            'logs',
            'logNames',
            'events'
        ));
    }
}
