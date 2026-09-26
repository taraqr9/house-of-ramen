<?php

namespace App\Models;

use App\Enums\PaymentMethodEnum;
use App\Enums\PaymentStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * One tender against an order (an order may have several - split/mixed
 * payment). Never deleted; mistakes are voided so the method breakdown
 * stays permanent. Create/void only via App\Services\Pos\PaymentService.
 */
class Payment extends Model
{
    use LogsActivity;

    protected $fillable = [
        'order_id',
        'payment_method',
        'amount',
        'tendered_amount',
        'change_amount',
        'reference_no',
        'remarks',
        'status',
        'idempotency_key',
        'received_by',
        'paid_at',
        'voided_at',
        'voided_by',
        'void_reason',
    ];

    protected function casts(): array
    {
        return [
            'payment_method' => PaymentMethodEnum::class,
            'status' => PaymentStatusEnum::class,
            'amount' => 'decimal:2',
            'tendered_amount' => 'decimal:2',
            'change_amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
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
