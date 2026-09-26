<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DiscountTypeEnum;
use App\Enums\KitchenCancelReasonEnum;
use App\Enums\OrderItemStatusEnum;
use App\Enums\OrderStatusEnum;
use App\Enums\OrderTypeEnum;
use App\Enums\PaymentMethodEnum;
use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\DiningTableResource;
use App\Http\Resources\MenuCategoryResource;
use App\Http\Resources\MenuItemResource;
use App\Http\Resources\RestaurantSettingResource;
use App\Http\Resources\UserResource;
use App\Models\DiningTable;
use App\Models\Restaurant;
use App\Models\RestaurantMenuCategory;
use App\Models\RestaurantMenuItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Reference data for the POS app: one bootstrap call after login, plus
 * tables/menu for refreshing.
 */
class PosCatalogController extends ApiController
{
    /**
     * Any of these makes someone a POS user who may read reference data.
     */
    private const POS_PERMISSIONS = [
        'order-view', 'order-create', 'dining_table-view', 'kitchen-view', 'serving-view',
        'billing-view', 'payment-view', 'payment-create', 'pos_report-view',
    ];

    public function bootstrap(Request $request): JsonResponse
    {
        $this->ensurePosUser($request);

        $restaurant = Restaurant::query()->first();

        return $this->ok([
            'api_version' => 'v1',
            'server_time' => now()->toIso8601String(),
            'poll_interval_seconds' => 4,
            'user' => UserResource::make($request->user())->resolve($request),
            'restaurant' => $restaurant ? RestaurantSettingResource::make($restaurant)->resolve($request) : null,
            'tables' => DiningTableResource::collection($this->tablesQuery()->get())->resolve($request),
            ...$this->menuPayload($request),
            'options' => [
                'order_types' => $this->options(OrderTypeEnum::options()),
                'order_statuses' => $this->options(OrderStatusEnum::options()),
                'item_statuses' => $this->options(OrderItemStatusEnum::options()),
                'payment_methods' => $this->options(PaymentMethodEnum::options()),
                'discount_types' => $this->options(DiscountTypeEnum::options()),
                'kitchen_cancel_reasons' => $this->options(KitchenCancelReasonEnum::options()),
            ],
        ]);
    }

    public function tables(Request $request): JsonResponse
    {
        $this->ensurePosUser($request);

        return $this->ok(DiningTableResource::collection($this->tablesQuery()->get()));
    }

    public function menu(Request $request): JsonResponse
    {
        $this->ensurePosUser($request);

        return $this->ok($this->menuPayload($request));
    }

    private function ensurePosUser(Request $request): void
    {
        abort_unless($request->user()->canAny(self::POS_PERMISSIONS), 403);
    }

    private function tablesQuery()
    {
        return DiningTable::query()->active()->with('activeOrder')->ordered();
    }

    /**
     * Active categories and their (non-deleted) items, flat so the app can
     * cache them in two tables. catalog_updated_at lets the app skip a
     * reload when nothing changed.
     */
    private function menuPayload(Request $request): array
    {
        $categories = RestaurantMenuCategory::query()->where('is_active', true)->orderBy('display_order')->orderBy('name')->get();

        $items = RestaurantMenuItem::query()
            ->with('category:id,name')
            ->whereIn('restaurant_menu_category_id', $categories->pluck('id'))
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        $updatedAt = collect([$categories->max('updated_at'), RestaurantMenuItem::withTrashed()->max('updated_at')])->filter()->max();

        return [
            'catalog_updated_at' => $updatedAt ? Carbon::parse($updatedAt)->toIso8601String() : null,
            'categories' => MenuCategoryResource::collection($categories)->resolve($request),
            'menu_items' => MenuItemResource::collection($items)->resolve($request),
        ];
    }

    private function options(array $options): array
    {
        return collect($options)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values()->all();
    }
}
