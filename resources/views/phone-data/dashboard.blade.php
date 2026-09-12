@extends('layout.master', ['page_title' => $page_title])

@section('content')
    <div class="page-content">
        <div class="container-fluid">

            @include('partials.breadcrumb', [
                'items' => [['label' => 'Phone Data Status']],
            ])

            <div class="row">
                <div class="col-md-3">
                    <div class="card"><div class="card-body">
                        <h6 class="text-muted mb-1">Total Phones</h6>
                        <h3 class="mb-0">{{ number_format($totalPhones) }}</h3>
                    </div></div>
                </div>
                <div class="col-md-3">
                    <div class="card"><div class="card-body">
                        <h6 class="text-muted mb-1">Active</h6>
                        <h3 class="mb-0">{{ number_format($activePhones) }}</h3>
                    </div></div>
                </div>
                <div class="col-md-3">
                    <div class="card"><div class="card-body">
                        <h6 class="text-muted mb-1">Released Last 2 Years</h6>
                        <h3 class="mb-0">{{ number_format($lastTwoYears) }}</h3>
                    </div></div>
                </div>
                <div class="col-md-3">
                    <div class="card"><div class="card-body">
                        <h6 class="text-muted mb-1">Price Updates (7 days)</h6>
                        <h3 class="mb-0">{{ number_format($priceUpdatesLast7Days) }}</h3>
                    </div></div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="card"><div class="card-body">
                        <h6 class="text-muted mb-1">Phones With a Published Image</h6>
                        <h3 class="mb-0">{{ number_format($phonesWithVerifiedImage) }} / {{ number_format($totalPhones) }}</h3>
                    </div></div>
                </div>
                <div class="col-md-6">
                    <div class="card {{ $phonesNeedingImageReview > 0 ? 'border-warning' : '' }}">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Phones With an Unverified Image</h6>
                                <h3 class="mb-0">{{ number_format($phonesNeedingImageReview) }}</h3>
                            </div>
                            @if($phonesNeedingImageReview > 0)
                                <a href="{{ route('data-review.index') }}" class="btn btn-warning">Review Now</a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="card {{ $pendingReview > 0 ? 'border-warning' : '' }}">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Pending Review</h6>
                                <h3 class="mb-0">{{ number_format($pendingReview) }}</h3>
                            </div>
                            @if($pendingReview > 0)
                                <a href="{{ route('data-review.index') }}" class="btn btn-warning">Review Now</a>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card {{ $openConflicts > 0 ? 'border-danger' : '' }}">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Data Conflicts</h6>
                                <h3 class="mb-0">{{ number_format($openConflicts) }}</h3>
                            </div>
                            @if($openConflicts > 0)
                                <a href="{{ route('data-review.index') }}" class="btn btn-danger">Resolve Now</a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="text-muted mb-2">Last Import</h6>
                            @if($lastImport)
                                <p class="mb-1">
                                    <span class="badge {{ $lastImport->status->badgeClass() }}">{{ $lastImport->status->label() }}</span>
                                    {{ $lastImport->source?->name ?? 'All sources' }} ({{ $lastImport->type->label() }})
                                </p>
                                <p class="text-muted mb-0">{{ $lastImport->started_at?->diffForHumans() ?? '-' }}</p>
                                <a href="{{ route('phone-import-runs.show', $lastImport->id) }}" class="btn btn-sm btn-outline-primary mt-2">View Details</a>
                            @else
                                <p class="text-muted mb-0">No imports have run yet.</p>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="text-muted mb-2">Last Nightly Run</h6>
                            @if($lastNightlyRun)
                                <p class="mb-1">
                                    <span class="badge {{ $lastNightlyRun->status->badgeClass() }}">{{ $lastNightlyRun->status->label() }}</span>
                                </p>
                                <p class="text-muted mb-0">{{ $lastNightlyRun->started_at?->diffForHumans() ?? '-' }}</p>
                                <a href="{{ route('phone-import-runs.show', $lastNightlyRun->id) }}" class="btn btn-sm btn-outline-primary mt-2">View Details</a>
                            @else
                                <p class="text-muted mb-0">The nightly scheduler hasn't run yet (scheduled for 12:01 AM Asia/Dhaka).</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-12">
                    <div class="d-flex gap-2">
                        <a href="{{ route('phones.index') }}" class="btn btn-primary">Manage Phones</a>
                        <a href="{{ route('brands.index') }}" class="btn btn-outline-primary">Manage Brands</a>
                        <a href="{{ route('phone-import-runs.index') }}" class="btn btn-outline-primary">Import Runs</a>
                        <a href="{{ route('phone-sources.index') }}" class="btn btn-outline-primary">Data Sources</a>
                    </div>
                </div>
            </div>

        </div>
    </div>
@endsection
