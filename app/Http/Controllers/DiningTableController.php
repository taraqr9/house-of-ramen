<?php

namespace App\Http\Controllers;

use App\Filters\DiningTableIndexFilter;
use App\Http\Requests\DiningTableIndexRequest;
use App\Http\Requests\DiningTableStoreRequest;
use App\Http\Requests\DiningTableUpdateRequest;
use App\Models\DiningTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DiningTableController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(DiningTable::class, 'dining_table');
    }

    public function index(DiningTableIndexRequest $request): View
    {
        $page_title = 'Tables';

        $query = DiningTable::query()->with('activeOrder');

        $tables = DiningTableIndexFilter::applyFilters($query, $request)
            ->ordered()
            ->paginate(30)
            ->appends($request->query());

        $areas = DiningTable::query()->whereNotNull('area')->distinct()->orderBy('area')->pluck('area');

        return view('pos.tables.index', compact('page_title', 'tables', 'areas'));
    }

    public function create(): View
    {
        return view('pos.tables.create', ['page_title' => 'Add Table']);
    }

    public function store(DiningTableStoreRequest $request): RedirectResponse
    {
        DiningTable::create($request->validated());

        return redirect()->route('dining-tables.index')->with('success', 'Table created successfully.');
    }

    public function edit(DiningTable $dining_table): View
    {
        return view('pos.tables.edit', [
            'page_title' => 'Edit Table',
            'table' => $dining_table,
        ]);
    }

    public function update(DiningTableUpdateRequest $request, DiningTable $dining_table): RedirectResponse
    {
        $dining_table->update($request->validated());

        return redirect()->route('dining-tables.index')->with('success', 'Table updated successfully.');
    }

    public function destroy(DiningTable $dining_table): RedirectResponse
    {
        // Past orders keep working either way (soft delete + table_name
        // snapshot), but deleting a table someone is sitting at would
        // strand its running order.
        if ($dining_table->activeOrder()->exists()) {
            return redirect()->route('dining-tables.index')->with('error', 'This table has a running order and cannot be deleted.');
        }

        $dining_table->delete();

        return redirect()->route('dining-tables.index')->with('success', 'Table deleted successfully.');
    }
}
