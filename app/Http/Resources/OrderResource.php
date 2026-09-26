<?php

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An order with server-calculated totals (never recomputed here). items /
 * payments are included when loaded; "completion" when the controller
 * passes the service's completion blockers via additional().
 *
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = fn ($relation) => $this->whenLoaded($relation, fn () => $this->{$relation} ? ['id' => $this->{$relation}->id, 'name' => $this->{$relation}->name] : null);

        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'order_type' => $this->order_type->value,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'is_active' => $this->isActive(),
            'table' => $this->dining_table_id || $this->table_name ? [
                'id' => $this->dining_table_id,
                'name' => $this->displayTable(),
            ] : null,
            'guest_count' => $this->guest_count,
            'general_note' => $this->general_note,
            'totals' => [
                'subtotal' => (string) $this->subtotal,
                'discount_type' => $this->discount_type?->value,
                'discount_value' => (string) $this->discount_value,
                'discount' => (string) $this->discount,
                'service_charge_percent' => (string) $this->service_charge_percent,
                'service_charge' => (string) $this->service_charge,
                'vat_percent' => (string) $this->vat_percent,
                'vat' => (string) $this->vat,
                'grand_total' => (string) $this->grand_total,
                'paid' => (string) $this->paid_total,
                'due' => number_format(max(0, $this->balanceDue()), 2, '.', ''),
            ],
            'payment_status' => $this->paymentStatus(),
            'opened_at' => $this->opened_at?->toIso8601String(),
            'opened_by' => $user('openedBy'),
            'bill_requested_at' => $this->bill_requested_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'completed_by' => $user('completedBy'),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancelled_by' => $user('cancelledBy'),
            'cancellation_reason' => $this->cancellation_reason,
            'updated_at' => $this->updated_at?->toIso8601String(),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
            'payment_method_summary' => $this->when($this->relationLoaded('payments'), fn () => $this->paymentMethodSummaryFrom($this->payments)),
        ];
    }

    private function paymentMethodSummaryFrom($payments): ?string
    {
        $methods = $payments->where('status.value', 'completed')->map(fn ($p) => $p->payment_method->value)->unique();

        return match ($methods->count()) {
            0 => null,
            1 => $methods->first(),
            default => 'mixed',
        };
    }
}
