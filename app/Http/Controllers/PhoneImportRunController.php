<?php

namespace App\Http\Controllers;

use App\Models\PhoneImportRun;
use Illuminate\View\View;

class PhoneImportRunController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', PhoneImportRun::class);

        $runs = PhoneImportRun::with('source', 'triggeredBy')
            ->latest('id')
            ->paginate(20);

        return view('phone-import-runs.index', [
            'page_title' => 'Import Runs',
            'runs' => $runs,
        ]);
    }

    public function show(PhoneImportRun $phone_import_run): View
    {
        $this->authorize('view', $phone_import_run);

        $records = $phone_import_run->records()
            ->with('phone')
            ->latest('id')
            ->paginate(30);

        return view('phone-import-runs.show', [
            'page_title' => 'Import Run #'.$phone_import_run->id,
            'run' => $phone_import_run,
            'records' => $records,
        ]);
    }
}
