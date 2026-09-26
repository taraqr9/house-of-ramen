<?php

namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PaymentIndexFilter
{
    public static function applyFilters(Builder $query, Request $request): Builder
    {
        if ($request->filled('date_from')) {
            $query->where('paid_at', '>=', Carbon::parse($request->input('date_from'))->startOfDay());
        }

        if ($request->filled('date_to')) {
            $query->where('paid_at', '<=', Carbon::parse($request->input('date_to'))->endOfDay());
        }

        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->input('payment_method'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('received_by')) {
            $query->where('received_by', $request->integer('received_by'));
        }

        if ($request->filled('keyword')) {
            $keyword = $request->input('keyword');

            $query->where(function (Builder $q) use ($keyword) {
                $q->where('reference_no', 'like', '%'.$keyword.'%')
                    ->orWhere('remarks', 'like', '%'.$keyword.'%')
                    ->orWhereHas('order', fn (Builder $oq) => $oq->where('order_number', 'like', '%'.$keyword.'%')
                        ->orWhere('table_name', 'like', '%'.$keyword.'%'));
            });
        }

        return $query;
    }
}
