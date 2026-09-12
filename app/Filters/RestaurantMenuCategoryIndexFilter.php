<?php

namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class RestaurantMenuCategoryIndexFilter
{
    public static function applyFilters(Builder $query, Request $request): Builder
    {
        if ($request->filled('keyword')) {
            $query->where('name', 'like', '%'.$request->keyword.'%');
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', (bool) $request->input('is_active'));
        }

        return $query;
    }
}
