<?php

namespace App\Http\Controllers;

use App\Filters\BrandIndexFilter;
use App\Http\Requests\BrandIndexRequest;
use App\Http\Requests\BrandStoreRequest;
use App\Http\Requests\BrandUpdateRequest;
use App\Models\Brand;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BrandController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Brand::class, 'brand');
    }

    public function index(BrandIndexRequest $request): View
    {
        $page_title = 'Brands';

        $query = Brand::withCount('phones');

        $brands = BrandIndexFilter::applyFilters($query, $request)
            ->orderBy('name')
            ->paginate(20)
            ->appends($request->query());

        return view('brands.index', compact('page_title', 'brands'));
    }

    public function create(): View
    {
        return view('brands.create', [
            'page_title' => 'Add Brand',
            'parentBrands' => Brand::whereNull('parent_brand_id')->orderBy('name')->get(),
        ]);
    }

    public function store(BrandStoreRequest $request): RedirectResponse
    {
        Brand::create($request->validated());

        return redirect()->route('brands.index')->with('success', 'Brand created successfully.');
    }

    public function edit(Brand $brand): View
    {
        return view('brands.edit', [
            'page_title' => 'Edit Brand',
            'brand' => $brand,
            'parentBrands' => Brand::whereNull('parent_brand_id')->where('id', '!=', $brand->id)->orderBy('name')->get(),
        ]);
    }

    public function update(BrandUpdateRequest $request, Brand $brand): RedirectResponse
    {
        $brand->update($request->validated());

        return redirect()->route('brands.index')->with('success', 'Brand updated successfully.');
    }

    public function destroy(Brand $brand): RedirectResponse
    {
        if ($brand->phones()->exists()) {
            return redirect()->route('brands.index')->with('error', 'Cannot delete a brand that still has phones.');
        }

        $brand->delete();

        return redirect()->route('brands.index')->with('success', 'Brand deleted successfully.');
    }
}
