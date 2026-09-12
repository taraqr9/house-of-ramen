<?php

namespace App\Http\Controllers;

use App\Enums\ConflictStatusEnum;
use App\Enums\ImageStatusEnum;
use App\Enums\ImportRunTypeEnum;
use App\Enums\ReviewStatusEnum;
use App\Models\Phone;
use App\Models\PhoneDataConflict;
use App\Models\PhoneDataReview;
use App\Models\PhoneImportRun;
use App\Models\PhonePriceHistory;
use Illuminate\View\View;

class PhoneDataDashboardController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()->can('phone-view'), 403);

        $lastImport = PhoneImportRun::latest('id')->first();
        $lastNightlyRun = PhoneImportRun::where('type', ImportRunTypeEnum::NIGHTLY)->latest('id')->first();

        return view('phone-data.dashboard', [
            'page_title' => 'Phone Data Status',
            'totalPhones' => Phone::count(),
            'activePhones' => Phone::where('is_active', true)->count(),
            'lastTwoYears' => Phone::where('release_date', '>=', now()->subYears(2))->count(),
            'pendingReview' => PhoneDataReview::where('status', ReviewStatusEnum::PENDING)->count(),
            'openConflicts' => PhoneDataConflict::where('status', ConflictStatusEnum::OPEN)->count(),
            'phonesWithVerifiedImage' => Phone::whereHas('images', fn ($q) => $q->where('is_primary', true)->where('status', ImageStatusEnum::VERIFIED))->count(),
            'phonesNeedingImageReview' => Phone::whereHas('images', fn ($q) => $q->where('status', ImageStatusEnum::NEEDS_REVIEW))
                ->whereDoesntHave('images', fn ($q) => $q->where('status', ImageStatusEnum::VERIFIED))
                ->count(),
            'priceUpdatesLast7Days' => PhonePriceHistory::where('changed_at', '>=', now()->subDays(7))->count(),
            'lastImport' => $lastImport,
            'lastNightlyRun' => $lastNightlyRun,
        ]);
    }
}
