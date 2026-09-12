<?php

namespace App\Filters;

use App\Enums\ImageStatusEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class PhoneIndexFilter
{
    public static function applyFilters(Builder $query, Request $request): Builder
    {
        if ($request->filled('keyword')) {
            $query->where('name', 'like', '%'.$request->keyword.'%');
        }

        if ($request->filled('brand_id')) {
            $query->where('brand_id', $request->input('brand_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        if ($request->filled('low_confidence')) {
            $query->where(function (Builder $q) {
                $q->where('overall_confidence', '<', config('phone_confidence.auto_approve_threshold'))
                    ->orWhereNull('overall_confidence');
            });
        }

        // No verified primary image - i.e. what the public site would
        // actually render as blank/placeholder (see Phone::primaryImage()),
        // not just "no image rows at all" - a phone with only an
        // unverified/needs-review upload still has nothing showing live.
        if ($request->filled('no_image')) {
            $query->whereDoesntHave('images', fn (Builder $q) => $q
                ->where('is_primary', true)
                ->where('status', ImageStatusEnum::VERIFIED));
        }

        // No price at all on any variant - matches this page's own "BD
        // Price (from)" column, which is built from the same
        // variants.prices collection (see phones/index.blade.php).
        if ($request->filled('no_price')) {
            $query->whereDoesntHave('variants.prices');
        }

        return $query;
    }
}
