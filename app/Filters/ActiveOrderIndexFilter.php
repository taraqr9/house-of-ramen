<?php

namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ActiveOrderIndexFilter
{
    public static function applyFilters(Builder $query, Request $request): Builder
    {
        if ($request->filled('order_type')) {
            $query->where('order_type', $request->input('order_type'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('dining_table_id')) {
            $query->where('dining_table_id', $request->integer('dining_table_id'));
        }

        if ($request->filled('keyword')) {
            $keyword = $request->input('keyword');

            $query->where(function (Builder $q) use ($keyword) {
                $q->where('order_number', 'like', '%'.$keyword.'%')
                    ->orWhere('table_name', 'like', '%'.$keyword.'%')
                    ->orWhere('general_note', 'like', '%'.$keyword.'%');
            });
        }

        return $query;
    }
}
