<?php

use App\Models\PhoneVariant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Real Bangladesh retailers price a variant (RAM/storage/region) the
 * same across every color they stock - color is cosmetic, not part of
 * a variant's identity. Keeping it in the unique key meant a retailer
 * feed that didn't happen to name the exact color a phone was originally
 * seeded with (e.g. "Titanium Black") would silently create a *second*
 * phone_variants row for the same real-world SKU instead of updating
 * the existing one - fragmenting its price history across two rows.
 * See App\Services\PhoneImport\PhoneImportRunner::upsertVariant().
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->mergeDuplicateVariants();

        Schema::table('phone_variants', function (Blueprint $table) {
            // Added before the drop below (not after): phone_id's FK to
            // phones needs a supporting index at all times, and the old
            // composite unique (which also starts with phone_id) is the
            // only one currently providing it - dropping it first would
            // fail with "needed in a foreign key constraint". Given a new
            // name outright (rather than renamed back to the original)
            // since an in-place rename-after-drop isn't portable across
            // the MySQL/SQLite grammars this app runs on (dev vs. tests).
            $table->unique(['phone_id', 'ram_gb', 'storage_gb', 'region'], 'phone_variants_ram_storage_region_unique');
            $table->dropUnique('phone_variants_unique_combo');
        });
    }

    public function down(): void
    {
        Schema::table('phone_variants', function (Blueprint $table) {
            $table->unique(['phone_id', 'ram_gb', 'storage_gb', 'color', 'region'], 'phone_variants_unique_combo');
            $table->dropUnique('phone_variants_ram_storage_region_unique');
        });
    }

    /**
     * Merge any existing (phone_id, ram_gb, storage_gb, region) groups that
     * differ only by color into a single row before the stricter unique
     * index is applied, so no price/history/availability data is lost.
     * Keeps the row with a non-null color (more descriptive) when one
     * exists, otherwise the lowest id; reassigns every child row from the
     * duplicates onto the survivor, then soft-deletes the duplicates.
     */
    protected function mergeDuplicateVariants(): void
    {
        $groups = DB::table('phone_variants')
            ->whereNull('deleted_at')
            ->select('phone_id', 'ram_gb', 'storage_gb', 'region', DB::raw('COUNT(*) as c'))
            ->groupBy('phone_id', 'ram_gb', 'storage_gb', 'region')
            ->having('c', '>', 1)
            ->get();

        foreach ($groups as $group) {
            $variants = PhoneVariant::withTrashed()
                ->where('phone_id', $group->phone_id)
                ->where('ram_gb', $group->ram_gb)
                ->where('storage_gb', $group->storage_gb)
                ->where('region', $group->region)
                ->whereNull('deleted_at')
                ->orderByRaw('color IS NULL, id')
                ->get();

            $survivor = $variants->first();
            $duplicates = $variants->slice(1);

            foreach ($duplicates as $duplicate) {
                DB::table('phone_prices')->where('phone_variant_id', $duplicate->id)->update(['phone_variant_id' => $survivor->id]);
                DB::table('phone_price_history')->where('phone_variant_id', $duplicate->id)->update(['phone_variant_id' => $survivor->id]);
                DB::table('phone_availability')->where('phone_variant_id', $duplicate->id)->update(['phone_variant_id' => $survivor->id]);
                DB::table('phone_market_prices')->where('phone_variant_id', $duplicate->id)->delete();

                // Hard delete, not soft: a soft-deleted row would still
                // physically collide with the survivor under the new
                // unique index below (MySQL has no partial/filtered
                // unique index), and every child row has already been
                // reassigned to the survivor above, so nothing is lost.
                DB::table('phone_variants')->where('id', $duplicate->id)->delete();
            }
        }
    }
};
