<?php

namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class RestaurantMenuItemIndexFilter
{
    public static function applyFilters(Builder $query, Request $request): Builder
    {
        if ($request->filled('keyword')) {
            $query->where('name', 'like', '%'.$request->keyword.'%');
        }

        if ($request->filled('restaurant_menu_category_id')) {
            $query->where('restaurant_menu_category_id', $request->input('restaurant_menu_category_id'));
        }

        if ($request->filled('is_available')) {
            $query->where('is_available', (bool) $request->input('is_available'));
        }

        if ($request->filled('is_featured')) {
            $query->where('is_featured', (bool) $request->input('is_featured'));
        }

        return $query;
    }
}
