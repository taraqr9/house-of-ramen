<?php

namespace App\Http\Controllers;

use App\Enums\StatusEnum;
use App\Filters\MenuIndexFilter;
use App\Http\Requests\MenuIndexRequest;
use App\Http\Requests\StoreMenuRequest;
use App\Http\Requests\UpdateMenuRequest;
use App\Models\Menu;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class MenuController extends Controller
{
    public function index(MenuIndexRequest $request): View
    {
        $page_title = 'Menus';

        $parentMenus = Menu::whereNull('parent_id')
            ->orderBy('title')
            ->get();

        $query = Menu::query()
            ->leftJoin('menus as parent_menus', 'menus.parent_id', '=', 'parent_menus.id')
            ->select('menus.*');

        $menu_list = MenuIndexFilter::applyFilters($query, $request)
            ->with('parent')
            ->orderByRaw('COALESCE(parent_menus.serial, menus.serial)')
            ->orderByRaw('COALESCE(parent_menus.id, menus.id)')
            ->orderByRaw('menus.parent_id IS NOT NULL')
            ->orderBy('menus.serial')
            ->paginate(20)
            ->appends($request->query());

        return view('menus.index', compact(
            'page_title',
            'menu_list',
            'parentMenus',
        ))->with([
            'statuses' => StatusEnum::options(),
        ]);
    }

    public function create()
    {
        return view('menus.create', [
            'page_title' => 'Create Menu',
            'parents' => Menu::whereNull('parent_id')->get(),
        ]);
    }

    public function store(StoreMenuRequest $request)
    {
        Menu::create($request->validated());

        return redirect()->route('menus.index')
            ->with('success', 'Menu created successfully');
    }

    public function edit(Menu $menu): View
    {
        $page_title = 'Edit Menu';

        $parents = Menu::query()
            ->whereNull('parent_id')
            ->where('id', '!=', $menu->id)
            ->where('is_active', 1)
            ->orderBy('serial')
            ->get();

        return view('menus.edit', compact('page_title', 'menu', 'parents'));
    }

    public function update(UpdateMenuRequest $request, Menu $menu): RedirectResponse
    {
        $validated = $request->validated();

        $validated['is_active'] = $request->boolean('is_active');

        $menu->update($validated);

        return redirect()
            ->route('menus.index')
            ->with('success', 'Menu updated successfully.');
    }

    public function destroy(Menu $menu)
    {
        $menu->delete();

        return redirect()->route('menus.index')
            ->with('success', 'Menu deleted successfully');
    }
}
