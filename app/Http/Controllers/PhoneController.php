<?php

namespace App\Http\Controllers;

use App\Filters\PhoneIndexFilter;
use App\Http\Requests\PhoneIndexRequest;
use App\Http\Requests\PhoneSaveRequest;
use App\Models\Brand;
use App\Models\Phone;
use App\Models\PhoneSpec;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PhoneController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Phone::class, 'phone');
    }

    protected const SPEC_FIELDS = [
        'processor', 'chipset_manufacturer', 'cpu', 'gpu',
        'display_size', 'display_resolution', 'display_panel_type', 'display_refresh_rate', 'display_protection',
        'main_camera', 'ultrawide_camera', 'telephoto_camera', 'macro_camera', 'front_camera', 'camera_has_ois', 'video_recording',
        'battery_capacity_mah', 'charging_speed_w', 'wireless_charging_w', 'reverse_charging',
        'network_5g', 'wifi', 'bluetooth_version', 'nfc', 'usb_type', 'sim_config',
        'weight_g', 'build_materials', 'ip_rating',
        'os', 'current_os', 'os_update_years', 'security_update_years',
    ];

    public function index(PhoneIndexRequest $request): View
    {
        $query = Phone::with(['brand', 'variants.prices'])->withCount('reviews');

        $phones = PhoneIndexFilter::applyFilters($query, $request)
            ->orderByDesc('updated_at')
            ->paginate(20)
            ->appends($request->query());

        return view('phones.index', [
            'page_title' => 'Phones',
            'phones' => $phones,
            'brands' => Brand::orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('phones.create', [
            'page_title' => 'Add Phone',
            'brands' => Brand::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(PhoneSaveRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $phone = DB::transaction(function () use ($data) {
            $identity = collect($data)->except(self::SPEC_FIELDS)->all();
            $identity['collected_at'] = now();
            $identity['last_verified_at'] = now();

            $phone = Phone::create($identity);

            $specs = collect($data)->only(self::SPEC_FIELDS)->all();
            $specs['phone_id'] = $phone->id;
            $specs['collected_at'] = now();

            PhoneSpec::create($specs);

            return $phone;
        });

        return redirect()->route('phones.edit', $phone->id)->with('success', 'Phone created successfully. You can now upload an image and add variants (RAM/storage/price) below.');
    }

    public function edit(Phone $phone): View
    {
        $phone->load(['spec', 'variants.prices.store', 'variants.availabilities.store', 'images', 'reviews' => fn ($q) => $q->where('status', 'pending'), 'conflicts' => fn ($q) => $q->where('status', 'open')]);

        return view('phones.edit', [
            'page_title' => 'Edit Phone',
            'phone' => $phone,
            'brands' => Brand::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(PhoneSaveRequest $request, Phone $phone): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $phone) {
            $identity = collect($data)->except(self::SPEC_FIELDS)->all();
            $identity['last_verified_at'] = now();

            $phone->update($identity);

            $specs = collect($data)->only(self::SPEC_FIELDS)->all();
            $specs['collected_at'] = now();

            PhoneSpec::query()->updateOrCreate(['phone_id' => $phone->id], $specs);

            $phone->update([
                'identity_confidence' => 100,
                'spec_confidence' => 100,
                'software_confidence' => 100,
                'overall_confidence' => 100,
                'confidence_calculated_at' => now(),
            ]);
        });

        return redirect()->route('phones.edit', $phone->id)->with('success', 'Phone updated successfully.');
    }

    public function destroy(Phone $phone): RedirectResponse
    {
        $phone->delete();

        return redirect()->route('phones.index')->with('success', 'Phone deleted successfully.');
    }
}
