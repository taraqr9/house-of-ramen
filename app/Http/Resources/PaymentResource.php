<?php

namespace App\Http\Resources;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One tender. amount is what was applied to the bill; for cash,
 * tendered_amount - change_amount = amount.
 *
 * @mixin Payment
 */
class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'method' => $this->payment_method->value,
            'amount' => (string) $this->amount,
            'tendered_amount' => $this->tendered_amount !== null ? (string) $this->tendered_amount : null,
            'change_amount' => (string) $this->change_amount,
            'reference_no' => $this->reference_no,
            'remarks' => $this->remarks,
            'status' => $this->status->value,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'received_by' => $this->whenLoaded('receivedBy', fn () => $this->receivedBy ? ['id' => $this->receivedBy->id, 'name' => $this->receivedBy->name] : null),
            'voided_at' => $this->voided_at?->toIso8601String(),
            'voided_by' => $this->whenLoaded('voidedBy', fn () => $this->voidedBy ? ['id' => $this->voidedBy->id, 'name' => $this->voidedBy->name] : null),
            'void_reason' => $this->void_reason,
        ];
    }
}
