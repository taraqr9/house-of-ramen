<?php

namespace App\Http\Controllers;

use App\Http\Requests\PosReportRequest;
use App\Services\Pos\PosReportService;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class PosReportController extends Controller
{
    public function __construct(private readonly PosReportService $reports) {}

    /**
     * Defaults to today ("Sales Today").
     */
    public function index(PosReportRequest $request): View
    {
        $from = $request->filled('date_from') ? Carbon::parse($request->input('date_from')) : today();
        $to = $request->filled('date_to') ? Carbon::parse($request->input('date_to')) : $from->copy();

        return view('pos.reports.index', [
            'page_title' => 'POS Reports',
            'report' => $this->reports->build($from, $to),
        ]);
    }
}
