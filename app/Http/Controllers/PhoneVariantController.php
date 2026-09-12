<?php

namespace App\Http\Controllers;

use App\Enums\PriceTypeEnum;
use App\Http\Requests\PhoneVariantSaveRequest;
use App\Models\Phone;
use App\Models\PhoneAvailability;
use App\Models\PhonePrice;
use App\Models\PhonePriceHistory;
use App\Models\PhoneSource;
use App\Models\PhoneStore;
use App\Models\PhoneVariant;
use App\Services\PhoneImport\Normalization\SpecNormalizer;
use App\Services\PhoneImport\PriceAggregator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PhoneVariantController extends Controller
{
    public function __construct(protected PriceAggregator $prices) {}

    public function create(Phone $phone): View
    {
        $this->authorize('create', PhoneVariant::class);

        return view('phones.variants.create', [
            'page_title' => 'Add Variant',
            'phone' => $phone,
            'stores' => PhoneStore::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(PhoneVariantSaveRequest $request, Phone $phone): RedirectResponse
    {
        $this->authorize('create', PhoneVariant::class);

        $this->saveVariant($request->validated(), $phone);

        return redirect()->route('phones.edit', $phone->id)->with('success', 'Variant added successfully.');
    }

    public function edit(Phone $phone, PhoneVariant $variant): View
    {
        $this->authorize('update', $variant);

        $variant->load('prices', 'availabilities');

        return view('phones.variants.edit', [
            'page_title' => 'Edit Variant',
            'phone' => $phone,
            'variant' => $variant,
            'stores' => PhoneStore::where('is_active', true)->orderBy('name')->get(),
            'officialPrice' => $variant->prices->firstWhere('price_type', 'official_bd'),
            'unofficialPrice' => $variant->prices->firstWhere('price_type', 'unofficial_bd'),
            'availability' => $variant->availabilities->first(),
        ]);
    }

    public function update(PhoneVariantSaveRequest $request, Phone $phone, PhoneVariant $variant): RedirectResponse
    {
        $this->authorize('update', $variant);

        $this->saveVariant($request->validated(), $phone, $variant);

        return redirect()->route('phones.edit', $phone->id)->with('success', 'Variant updated successfully.');
    }

    public function destroy(Phone $phone, PhoneVariant $variant): RedirectResponse
    {
        $this->authorize('delete', $variant);

        $variant->delete();

        return redirect()->route('phones.edit', $phone->id)->with('success', 'Variant deleted successfully.');
    }

    protected function saveVariant(array $data, Phone $phone, ?PhoneVariant $variant = null): PhoneVariant
    {
        return DB::transaction(function () use ($data, $phone, $variant) {
            $source = $this->adminSource();

            $slug = SpecNormalizer::slug(
                $phone->slug,
                (string) ($data['ram_gb'] ?? ''),
                (string) ($data['storage_gb'] ?? ''),
                (string) ($data['color'] ?? ''),
                (string) ($data['region'] ?? '')
            );

            $variantData = collect($data)->except([
                'official_bd_price', 'unofficial_bd_price', 'store_id', 'availability_status',
            ])->all();

            $variantData['phone_id'] = $phone->id;
            $variantData['slug'] = $slug;
            $variantData['source_id'] = $source->id;
            $variantData['collected_at'] = now();

            $variant = $variant
                ? tap($variant)->update($variantData)
                : PhoneVariant::create($variantData);

            $storeId = $data['store_id'] ?? null;

            $this->upsertPrice($variant, $source, 'official_bd', $data['official_bd_price'] ?? null, $storeId);
            $this->upsertPrice($variant, $source, 'unofficial_bd', $data['unofficial_bd_price'] ?? null, $storeId);

            if (! empty($data['availability_status'])) {
                PhoneAvailability::query()->updateOrCreate(
                    ['phone_variant_id' => $variant->id, 'store_id' => $storeId],
                    ['status' => $data['availability_status'], 'source_id' => $source->id, 'confidence' => 100, 'collected_at' => now()]
                );
            }

            return $variant;
        });
    }

    protected function upsertPrice(PhoneVariant $variant, PhoneSource $source, string $priceType, ?float $amount, ?int $storeId): void
    {
        if ($amount === null) {
            return;
        }

        $existing = PhonePrice::query()
            ->where('phone_variant_id', $variant->id)
            ->where('store_id', $storeId)
            ->where('price_type', $priceType)
            ->first();

        if (! $existing || $existing->amount !== number_format($amount, 2, '.', '')) {
            PhonePriceHistory::create([
                'phone_variant_id' => $variant->id,
                'store_id' => $storeId,
                'price_type' => $priceType,
                'amount' => $amount,
                'previous_amount' => $existing?->amount,
                'source_id' => $source->id,
                'changed_at' => now(),
            ]);
        }

        PhonePrice::query()->updateOrCreate(
            ['phone_variant_id' => $variant->id, 'store_id' => $storeId, 'price_type' => $priceType],
            ['amount' => $amount, 'source_id' => $source->id, 'confidence' => 100, 'collected_at' => now(), 'last_verified_at' => now(), 'is_active' => true]
        );

        // Admin entry is a trusted, direct correction - it skips the
        // ingest-time outlier gate (App\Services\PhoneImport\PriceAggregator::isSane())
        // that automated sources go through, but the current market price
        // must still be kept in sync with what was just saved.
        $this->prices->recalculate($variant, PriceTypeEnum::from($priceType));
    }

    protected function adminSource(): PhoneSource
    {
        return PhoneSource::query()->firstOrCreate(
            ['key' => 'admin_manual'],
            ['name' => 'Admin (Manual Entry)', 'type' => 'manual', 'reliability_score' => 100, 'is_active' => true]
        );
    }
}
