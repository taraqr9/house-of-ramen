<?php

namespace App\Http\Resources;

use App\Models\Restaurant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Restaurant details a POS client needs (receipt header, charge rates).
 * CMS-only fields (description, social links, SEO) are left out.
 *
 * @mixin Restaurant
 */
class RestaurantSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'address' => $this->address,
            'area' => $this->area,
            'logo_url' => $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null,
            'vat_percent' => (string) $this->vat_percent,
            'service_charge_percent' => (string) $this->service_charge_percent,
            'currency' => 'BDT',
            'currency_symbol' => '৳',
        ];
    }
}
