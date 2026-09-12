<?php

namespace App\Console\Commands;

use App\Models\PhoneDataReview;
use App\Services\PhoneImport\ReviewResolutionService;
use Illuminate\Console\Command;

/**
 * Scripted equivalent of clicking Approve/Reject/Merge in the Data Review
 * admin UI - same ReviewResolutionService, same outcome. For resolving a
 * PhoneDataReview after real evidence-based research (e.g. a catalogue-wide
 * verification pass) where doing it through the browser isn't practical.
 */
class ResolveReviewCommand extends Command
{
    protected $signature = 'phones:resolve-review
        {review : PhoneDataReview id}
        {action : approve|reject|merge|ignore}
        {--note= : Evidence/reasoning for this decision, stored on the review for audit.}';

    protected $description = 'Resolve one pending PhoneDataReview with an explicit evidence-based decision.';

    public function handle(ReviewResolutionService $resolver): int
    {
        $action = $this->argument('action');

        if (! in_array($action, ['approve', 'reject', 'merge', 'ignore'], true)) {
            $this->error("Unknown action '{$action}' - expected approve|reject|merge|ignore.");

            return self::FAILURE;
        }

        $review = PhoneDataReview::find($this->argument('review'));

        if (! $review) {
            $this->error("Review #{$this->argument('review')} not found.");

            return self::FAILURE;
        }

        if ($review->status->value !== 'pending') {
            $this->warn("Review #{$review->id} is already '{$review->status->value}' - skipping.");

            return self::SUCCESS;
        }

        $note = $this->option('note');

        match ($action) {
            'approve' => $resolver->approve($review, null, $note),
            'merge' => $resolver->merge($review, null, $note),
            'reject' => $resolver->reject($review, null, $note),
            'ignore' => $resolver->ignore($review, null, $note),
        };

        $this->info("Review #{$review->id} ({$review->reason->value}) resolved as {$review->fresh()->status->value}.");

        return self::SUCCESS;
    }
}
