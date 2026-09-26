<?php

namespace App\Filters;

use App\Enums\OrderStatusEnum;
use App\Enums\PaymentStatusEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Closed-order history. Dates filter on when the order was closed
 * (completed_at, or cancelled_at for cancelled orders).
 */
class CompletedOrderIndexFilter
{
    public static function applyFilters(Builder $query, Request $request): Builder
    {
        $status = $request->input('status', OrderStatusEnum::COMPLETED->value);
        $query->where('status', $status);

        $dateColumn = $status === OrderStatusEnum::CANCELLED->value ? 'cancelled_at' : 'completed_at';

        if ($request->filled('date_from')) {
            $query->where($dateColumn, '>=', Carbon::parse($request->input('date_from'))->startOfDay());
        }

        if ($request->filled('date_to')) {
            $query->where($dateColumn, '<=', Carbon::parse($request->input('date_to'))->endOfDay());
        }

        if ($request->filled('dining_table_id')) {
            $query->where('dining_table_id', $request->integer('dining_table_id'));
        }

        if ($request->filled('order_number')) {
            $query->where('order_number', 'like', '%'.$request->input('order_number').'%');
        }

        if ($request->filled('completed_by')) {
            $query->where('completed_by', $request->integer('completed_by'));
        }

        $completed = PaymentStatusEnum::COMPLETED->value;

        match ($request->input('payment_method')) {
            'cash', 'card' => $query
                ->whereHas('payments', fn (Builder $q) => $q->where('status', $completed)->where('payment_method', $request->input('payment_method')))
                ->whereDoesntHave('payments', fn (Builder $q) => $q->where('status', $completed)->where('payment_method', '!=', $request->input('payment_method'))),
            'mixed' => $query
                ->whereHas('payments', fn (Builder $q) => $q->where('status', $completed)->where('payment_method', 'cash'))
                ->whereHas('payments', fn (Builder $q) => $q->where('status', $completed)->where('payment_method', 'card')),
            default => null,
        };

        if ($request->filled('keyword')) {
            $keyword = $request->input('keyword');

            $query->where(function (Builder $q) use ($keyword) {
                $q->where('order_number', 'like', '%'.$keyword.'%')
                    ->orWhere('table_name', 'like', '%'.$keyword.'%')
                    ->orWhere('general_note', 'like', '%'.$keyword.'%')
                    ->orWhereHas('items', fn (Builder $iq) => $iq->where('item_name', 'like', '%'.$keyword.'%'));
            });
        }

        return $query;
    }
}
