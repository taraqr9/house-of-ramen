<?php

namespace App\Http\Controllers;

use App\Filters\PhoneStoreIndexFilter;
use App\Http\Requests\PhoneStoreIndexRequest;
use App\Http\Requests\PhoneStoreSaveRequest;
use App\Models\PhoneStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PhoneStoreController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(PhoneStore::class, 'phone_store');
    }

    public function index(PhoneStoreIndexRequest $request): View
    {
        $query = PhoneStore::query();

        $stores = PhoneStoreIndexFilter::applyFilters($query, $request)
            ->orderBy('name')
            ->paginate(20)
            ->appends($request->query());

        return view('phone-stores.index', [
            'page_title' => 'Retailers & Stores',
            'stores' => $stores,
        ]);
    }

    public function create(): View
    {
        return view('phone-stores.create', ['page_title' => 'Add Store']);
    }

    public function store(PhoneStoreSaveRequest $request): RedirectResponse
    {
        PhoneStore::create($request->validated());

        return redirect()->route('phone-stores.index')->with('success', 'Store created successfully.');
    }

    public function edit(PhoneStore $phone_store): View
    {
        return view('phone-stores.edit', [
            'page_title' => 'Edit Store',
            'store' => $phone_store,
        ]);
    }

    public function update(PhoneStoreSaveRequest $request, PhoneStore $phone_store): RedirectResponse
    {
        $phone_store->update($request->validated());

        return redirect()->route('phone-stores.index')->with('success', 'Store updated successfully.');
    }

    public function destroy(PhoneStore $phone_store): RedirectResponse
    {
        if ($phone_store->prices()->exists() || $phone_store->availabilities()->exists()) {
            return redirect()->route('phone-stores.index')->with('error', 'Cannot delete a store with recorded prices or availability.');
        }

        $phone_store->delete();

        return redirect()->route('phone-stores.index')->with('success', 'Store deleted successfully.');
    }
}
