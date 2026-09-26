<?php

namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class DiningTableIndexFilter
{
    public static function applyFilters(Builder $query, Request $request): Builder
    {
        if ($request->filled('area')) {
            $query->where('area', $request->input('area'));
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', (bool) $request->input('is_active'));
        }

        if ($request->input('occupancy') === 'occupied') {
            $query->whereHas('activeOrder');
        } elseif ($request->input('occupancy') === 'available') {
            $query->whereDoesntHave('activeOrder');
        }

        if ($request->filled('keyword')) {
            $keyword = $request->input('keyword');

            $query->where(function (Builder $q) use ($keyword) {
                $q->where('name', 'like', '%'.$keyword.'%')
                    ->orWhere('area', 'like', '%'.$keyword.'%')
                    ->orWhere('remarks', 'like', '%'.$keyword.'%');
            });
        }

        return $query;
    }
}
