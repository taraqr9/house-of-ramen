<?php

namespace App\Models;

use App\Enums\OrderItemStatusEnum;
use App\Traits\HasUserStamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line sent to the kitchen in a given round. Name/price/category are
 * snapshots taken when the round is sent - later menu changes never alter
 * a placed order.
 */
class OrderItem extends Model
{
    use HasUserStamps;

    protected $fillable = [
        'order_id',
        'restaurant_menu_item_id',
        'restaurant_menu_category_id',
        'item_name',
        'category_name',
        'quantity',
        'unit_price',
        'line_total',
        'note',
        'round_no',
        'submission_key',
        'kitchen_status',
        'sent_at',
        'preparing_at',
        'ready_at',
        'ready_acknowledged_at',
        'served_at',
        'served_by',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'kitchen_status' => OrderItemStatusEnum::class,
            'quantity' => 'integer',
            'round_no' => 'integer',
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
            'sent_at' => 'datetime',
            'preparing_at' => 'datetime',
            'ready_at' => 'datetime',
            'ready_acknowledged_at' => 'datetime',
            'served_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(RestaurantMenuItem::class, 'restaurant_menu_item_id')->withTrashed();
    }

    public function servedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'served_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function isCancelled(): bool
    {
        return $this->kitchen_status === OrderItemStatusEnum::CANCELLED;
    }
}
