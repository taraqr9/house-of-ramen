<?php

namespace App\Http\Controllers;

use App\Http\Requests\PhoneSourceUpdateRequest;
use App\Models\PhoneSource;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PhoneSourceController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', PhoneSource::class);

        $sources = PhoneSource::withCount('importRuns')->orderBy('name')->get();

        return view('phone-sources.index', [
            'page_title' => 'Data Sources',
            'sources' => $sources,
        ]);
    }

    public function edit(PhoneSource $phone_source): View
    {
        $this->authorize('update', $phone_source);

        return view('phone-sources.edit', [
            'page_title' => 'Edit Source',
            'source' => $phone_source,
        ]);
    }

    public function update(PhoneSourceUpdateRequest $request, PhoneSource $phone_source): RedirectResponse
    {
        $this->authorize('update', $phone_source);

        $phone_source->update($request->validated());

        return redirect()->route('phone-sources.index')->with('success', 'Source updated successfully.');
    }
}
