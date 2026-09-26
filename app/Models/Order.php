<?php

namespace App\Models;

use App\Enums\DiscountTypeEnum;
use App\Enums\OrderStatusEnum;
use App\Enums\OrderTypeEnum;
use App\Enums\PaymentStatusEnum;
use App\Traits\HasUserStamps;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A POS order. All state changes go through App\Services\Pos\OrderService /
 * PaymentService (never update totals or status directly) so the same rules
 * apply to the web screens and the future REST API.
 */
class Order extends Model
{
    use HasUserStamps, LogsActivity;

    protected $fillable = [
        'order_number',
        'order_type',
        'dining_table_id',
        'active_table_id',
        'table_name',
        'guest_count',
        'status',
        'subtotal',
        'discount_type',
        'discount_value',
        'discount',
        'vat_percent',
        'vat',
        'service_charge_percent',
        'service_charge',
        'grand_total',
        'paid_total',
        'general_note',
        'opened_by',
        'opened_at',
        'bill_requested_at',
        'completed_by',
        'completed_at',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'order_type' => OrderTypeEnum::class,
            'status' => OrderStatusEnum::class,
            'discount_type' => DiscountTypeEnum::class,
            'guest_count' => 'integer',
            'subtotal' => 'decimal:2',
            'discount_value' => 'decimal:2',
            'discount' => 'decimal:2',
            'vat_percent' => 'decimal:2',
            'vat' => 'decimal:2',
            'service_charge_percent' => 'decimal:2',
            'service_charge' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'paid_total' => 'decimal:2',
            'opened_at' => 'datetime',
            'bill_requested_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function diningTable(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class)->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('round_no')->orderBy('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('id');
    }

    public function completedPayments(): HasMany
    {
        return $this->payments()->where('status', PaymentStatusEnum::COMPLETED->value);
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', OrderStatusEnum::activeValues());
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    public function balanceDue(): float
    {
        return round((float) $this->grand_total - (float) $this->paid_total, 2);
    }

    /**
     * True for a zero-total (fully discounted/complimentary) bill too -
     * OrderService::complete() separately requires served items.
     */
    public function isFullyPaid(): bool
    {
        return $this->balanceDue() <= 0;
    }

    /**
     * "T5" for dine-in, "Takeaway" otherwise - uses the name snapshot so a
     * renamed/deleted table still reads correctly on old orders.
     */
    public function displayTable(): string
    {
        return $this->order_type === OrderTypeEnum::TAKEAWAY
            ? 'Takeaway'
            : ($this->table_name ?? $this->diningTable?->name ?? '—');
    }

    /**
     * "cash", "card", or "mixed" from the completed payment rows.
     */
    public function paymentMethodSummary(): ?string
    {
        $methods = $this->completedPayments->pluck('payment_method')->map(fn ($m) => $m->value)->unique();

        return match ($methods->count()) {
            0 => null,
            1 => $methods->first(),
            default => 'mixed',
        };
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName(strtolower(class_basename($this)))
            ->logAll()
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $eventName) => class_basename($this)." {$eventName}<br>".
                '<strong>Table:</strong> '.$this->getTable());
    }
}
