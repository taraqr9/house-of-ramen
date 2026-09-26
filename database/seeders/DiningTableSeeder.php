<?php

namespace Database\Seeders;

use App\Models\DiningTable;
use Illuminate\Database\Seeder;

/**
 * Starter floor plan (T1-T10) for a fresh install. Only runs when there are
 * no tables at all (including soft-deleted ones), so it never re-adds a
 * table the restaurant deliberately removed or duplicates renamed ones.
 */
class DiningTableSeeder extends Seeder
{
    public function run(): void
    {
        if (DiningTable::withTrashed()->exists()) {
            return;
        }

        foreach (range(1, 10) as $number) {
            DiningTable::create([
                'name' => 'T'.$number,
                'capacity' => $number <= 6 ? 4 : 2,
                'display_order' => $number,
                'is_active' => true,
            ]);
        }
    }
}
