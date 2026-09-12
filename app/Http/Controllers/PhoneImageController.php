<?php

namespace App\Http\Controllers;

use App\Enums\ImageCollectionOutcomeEnum;
use App\Enums\ImageStatusEnum;
use App\Enums\ReviewReasonEnum;
use App\Enums\ReviewStatusEnum;
use App\Http\Requests\PhoneImageUploadRequest;
use App\Models\Phone;
use App\Models\PhoneDataReview;
use App\Models\PhoneImage;
use App\Services\PhoneImage\PhoneImageCollector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;

class PhoneImageController extends Controller
{
    /**
     * Manual upload from the phone edit screen - reuses the same
     * resize/optimize/store pipeline as the automated collector
     * (App\Services\PhoneImage\PhoneImageCollector) so an admin-uploaded
     * image is stored identically to one found automatically, and stays
     * verified/live immediately since an admin picking this exact file for
     * this exact phone is itself the verification (same reasoning as
     * PhoneImageCollector::attachManual()).
     */
    public function store(PhoneImageUploadRequest $request, Phone $phone, PhoneImageCollector $collector): RedirectResponse
    {
        $this->authorize('update', $phone);

        $outcome = $collector->attachUploaded(
            $phone,
            file_get_contents($request->file('image')->getRealPath()),
            $request->integer('variant_id') ?: null,
        );

        if ($outcome !== ImageCollectionOutcomeEnum::VERIFIED) {
            return redirect()->route('phones.edit', $phone->id)
                ->with('error', 'Could not process the uploaded image - please try a different file.');
        }

        return redirect()->route('phones.edit', $phone->id)->with('success', 'Image uploaded and published.');
    }

    public function setPrimary(Phone $phone, PhoneImage $image): RedirectResponse
    {
        $this->authorize('update', $phone);
        abort_unless($image->phone_id === $phone->id, 404);

        $phone->images()->update(['is_primary' => false]);
        $image->update(['is_primary' => true]);

        return redirect()->route('phones.edit', $phone->id)->with('success', 'Primary image updated.');
    }

    public function verify(Phone $phone, PhoneImage $image): RedirectResponse
    {
        $this->authorize('update', $phone);
        abort_unless($image->phone_id === $phone->id, 404);

        // Explicitly promotes to primary rather than trusting a pre-existing
        // is_primary flag on the row - a needs_review candidate is no
        // longer born primary (see PhoneImageCollector::collect()), so this
        // is what actually makes a manually-verified image live.
        $phone->images()->where('is_primary', true)->update(['is_primary' => false]);
        $image->update(['status' => ImageStatusEnum::VERIFIED, 'is_primary' => true, 'updated_by' => auth()->id()]);

        $this->resolveLinkedReview($image, ReviewStatusEnum::APPROVED, 'Image manually verified via phone edit screen.');

        return redirect()->route('phones.edit', $phone->id)->with('success', 'Image verified and published.');
    }

    public function reject(Phone $phone, PhoneImage $image): RedirectResponse
    {
        $this->authorize('update', $phone);
        abort_unless($image->phone_id === $phone->id, 404);

        $image->update(['status' => ImageStatusEnum::REJECTED, 'is_primary' => false, 'updated_by' => auth()->id()]);

        $this->resolveLinkedReview($image, ReviewStatusEnum::REJECTED, 'Image manually rejected via phone edit screen.');

        return redirect()->route('phones.edit', $phone->id)->with('success', 'Image rejected.');
    }

    /**
     * PhoneImageCollector::collect() opens an image_needs_review
     * PhoneDataReview row (details.image_id) whenever it stores a
     * below-threshold candidate - see that class for why. Acting on the
     * image directly here (the only UI path to do so) previously left
     * that review stuck pending forever, since this controller and the
     * review queue only ever touched their own record. Closing it here
     * keeps "pending review count" and "images awaiting a decision" from
     * silently diverging.
     */
    protected function resolveLinkedReview(PhoneImage $image, ReviewStatusEnum $status, string $note): void
    {
        PhoneDataReview::query()
            ->where('reason', ReviewReasonEnum::IMAGE_NEEDS_REVIEW)
            ->where('status', ReviewStatusEnum::PENDING)
            ->where('details->image_id', $image->id)
            ->update([
                'status' => $status,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
                'resolution_note' => $note,
            ]);
    }

    public function destroy(Phone $phone, PhoneImage $image): RedirectResponse
    {
        $this->authorize('update', $phone);
        abort_unless($image->phone_id === $phone->id, 404);

        if ($image->path) {
            Storage::disk($image->disk)->delete($image->path);
        }

        $image->delete();

        return redirect()->route('phones.edit', $phone->id)->with('success', 'Image deleted.');
    }
}
