<?php

namespace App\Http\Controllers;

use App\Enums\ConflictStatusEnum;
use App\Enums\ReviewStatusEnum;
use App\Models\PhoneDataConflict;
use App\Models\PhoneDataReview;
use App\Models\PhoneSource;
use App\Models\PhoneSpec;
use App\Services\PhoneImport\ReviewResolutionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DataReviewController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', PhoneDataReview::class);

        $reviews = PhoneDataReview::with('phone', 'matchedPhone', 'importRecord.source')
            ->where('status', ReviewStatusEnum::PENDING)
            ->latest('id')
            ->paginate(15, ['*'], 'reviews_page');

        $conflicts = PhoneDataConflict::with('phone', 'existingSource', 'newSource')
            ->where('status', ConflictStatusEnum::OPEN)
            ->latest('id')
            ->paginate(15, ['*'], 'conflicts_page');

        return view('data-review.index', [
            'page_title' => 'Data Review',
            'reviews' => $reviews,
            'conflicts' => $conflicts,
        ]);
    }

    public function resolveReview(Request $request, PhoneDataReview $review, ReviewResolutionService $resolver): RedirectResponse
    {
        $this->authorize('review', $review);

        $data = $request->validate([
            'action' => ['required', 'in:approve,reject,merge,ignore'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        match ($data['action']) {
            'approve' => $resolver->approve($review, auth()->id(), $data['note'] ?? null),
            'merge' => $resolver->merge($review, auth()->id(), $data['note'] ?? null),
            'reject' => $resolver->reject($review, auth()->id(), $data['note'] ?? null),
            'ignore' => $resolver->ignore($review, auth()->id(), $data['note'] ?? null),
        };

        return redirect()->route('data-review.index')->with('success', 'Review updated.');
    }

    public function resolveConflict(Request $request, PhoneDataConflict $conflict): RedirectResponse
    {
        $this->authorize('resolve', $conflict);

        $action = $request->validate(['action' => ['required', 'in:accept_new,keep_existing,ignore']])['action'];

        if ($action !== 'ignore') {
            $value = $action === 'accept_new' ? $conflict->new_value : $conflict->existing_value;

            if ($conflict->table_name === 'phone_specs') {
                PhoneSpec::query()->where('phone_id', $conflict->phone_id)->update([$conflict->field => $value]);
            }

            $conflict->update(['resolved_value' => $value]);
        }

        $conflict->update([
            'status' => $action === 'ignore' ? ConflictStatusEnum::IGNORED : ConflictStatusEnum::RESOLVED,
            'resolved_by' => auth()->id(),
            'resolved_at' => now(),
        ]);

        return redirect()->route('data-review.index')->with('success', 'Conflict resolved.');
    }

    public function markSourceUnreliable(PhoneSource $phone_source): RedirectResponse
    {
        $this->authorize('update', $phone_source);

        $phone_source->update([
            'reliability_score' => min($phone_source->reliability_score, 20),
            'notes' => trim(($phone_source->notes ? $phone_source->notes."\n" : '')
                .'Marked unreliable by '.auth()->user()->name.' on '.now()->toDateTimeString().'.'),
        ]);

        return redirect()->route('data-review.index')->with('success', "Source '{$phone_source->name}' marked unreliable.");
    }
}
