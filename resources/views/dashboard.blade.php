@extends('layout.master')

@section('content')

    <div class="page-content">
        <div class="container-fluid">

            @include('partials.breadcrumb', [
                'items' => [
                    [
                        'label' => 'Dashboard',
                    ],
                ],
            ])

            @if(! ($hasPhoneCatalogue ?? false))

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-1">Welcome back, {{ auth()->user()->name }}</h5>
                                <p class="text-muted mb-0">You don't have access to the phone catalogue data shown on this dashboard. Contact an administrator if you believe this is incorrect.</p>
                            </div>
                        </div>
                    </div>
                </div>

            @else

                {{-- ============================= 0. Analytics (GA4) summary ============================= --}}
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                                    <h5 class="mb-0">Analytics</h5>
                                    <a href="{{ route('analytics.index') }}" class="btn btn-sm btn-outline-primary">View Analytics &rarr;</a>
                                </div>

                                @if($analyticsSummary['status'] === 'error')
                                    @include('partials.analytics.unavailable')
                                @else
                                    <div class="row text-center g-3">
                                        <div class="col-6 col-lg">
                                            <h5 class="mb-0">{{ number_format($analyticsSummary['users'] ?? 0) }}</h5>
                                            <p class="text-muted mb-0 small">Users (7d)</p>
                                        </div>
                                        <div class="col-6 col-lg">
                                            <h5 class="mb-0">{{ number_format($analyticsSummary['sessions'] ?? 0) }}</h5>
                                            <p class="text-muted mb-0 small">Sessions (7d)</p>
                                        </div>
                                        <div class="col-6 col-lg">
                                            <h5 class="mb-0">{{ number_format($analyticsSummary['page_views'] ?? 0) }}</h5>
                                            <p class="text-muted mb-0 small">Page Views (7d)</p>
                                        </div>
                                        <div class="col-6 col-lg">
                                            <h6 class="mb-0 text-truncate" title="{{ $analyticsSummary['top_budget_range'] ?? '' }}">{{ $analyticsSummary['top_budget_range'] ?? '—' }}</h6>
                                            <p class="text-muted mb-0 small">Top Budget Range</p>
                                        </div>
                                        <div class="col-6 col-lg">
                                            <h6 class="mb-0 text-truncate" title="{{ $analyticsSummary['top_phone_name'] ?? '' }}">{{ $analyticsSummary['top_phone_name'] ?? '—' }}</h6>
                                            <p class="text-muted mb-0 small">Top Viewed Phone</p>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ============================= 1. Catalogue Overview ============================= --}}
                <h5 class="mb-3 mt-1">Catalogue Overview</h5>
                <div class="row">
                    <div class="col-xl-3 col-sm-6">
                        <div class="card mini-stats-wid h-100">
                            <div class="card-body position-relative">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <p class="text-muted fw-medium mb-1">Total Phones</p>
                                        <h4 class="mb-0">{{ number_format($totalPhones) }}</h4>
                                    </div>
                                    <div class="flex-shrink-0">
                                        <div class="avatar-sm rounded-circle bg-primary mini-stat-icon">
                                            <span class="avatar-title rounded-circle bg-primary">
                                                <i class="bx bx-mobile font-size-24"></i>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <a href="{{ route('phones.index') }}" class="stretched-link" aria-label="Manage phones"></a>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-sm-6">
                        <div class="card mini-stats-wid h-100">
                            <div class="card-body position-relative">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <p class="text-muted fw-medium mb-1">Total Brands</p>
                                        <h4 class="mb-0">{{ number_format($totalBrands) }}</h4>
                                    </div>
                                    <div class="flex-shrink-0">
                                        <div class="avatar-sm rounded-circle bg-primary mini-stat-icon">
                                            <span class="avatar-title rounded-circle bg-primary">
                                                <i class="bx bx-purchase-tag-alt font-size-24"></i>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <a href="{{ route('brands.index') }}" class="stretched-link" aria-label="Manage brands"></a>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-sm-6">
                        <div class="card mini-stats-wid h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <p class="text-muted fw-medium mb-1">Total Variants</p>
                                        <h4 class="mb-0">{{ number_format($totalVariants) }}</h4>
                                    </div>
                                    <div class="flex-shrink-0">
                                        <div class="avatar-sm rounded-circle bg-primary mini-stat-icon">
                                            <span class="avatar-title rounded-circle bg-primary">
                                                <i class="bx bx-layer font-size-24"></i>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-sm-6">
                        <div class="card mini-stats-wid h-100 {{ $phonesMissingImages > 0 ? 'border-warning' : '' }}">
                            <div class="card-body position-relative">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <p class="text-muted fw-medium mb-1">Phones Missing Images</p>
                                        <h4 class="mb-0">{{ number_format($phonesMissingImages) }}</h4>
                                    </div>
                                    <div class="flex-shrink-0">
                                        <div class="avatar-sm rounded-circle bg-warning mini-stat-icon">
                                            <span class="avatar-title rounded-circle bg-warning">
                                                <i class="bx bx-image-alt font-size-24"></i>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <a href="{{ route('phones.index') }}" class="stretched-link" aria-label="Manage phones"></a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-xl-3 col-sm-6">
                        <div class="card mini-stats-wid h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <p class="text-muted fw-medium mb-1">Phones With Images</p>
                                        <h4 class="mb-0">{{ number_format($phonesWithAnyImage) }} <span class="font-size-13 text-muted">/ {{ number_format($totalPhones) }}</span></h4>
                                    </div>
                                    <div class="flex-shrink-0">
                                        <div class="avatar-sm rounded-circle bg-primary mini-stat-icon">
                                            <span class="avatar-title rounded-circle bg-primary">
                                                <i class="bx bx-image font-size-24"></i>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-sm-6">
                        <div class="card mini-stats-wid h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <p class="text-muted fw-medium mb-1">Official Price</p>
                                        <h4 class="mb-0">{{ number_format($officialPriceCount) }}</h4>
                                    </div>
                                    <div class="flex-shrink-0">
                                        <div class="avatar-sm rounded-circle bg-primary mini-stat-icon">
                                            <span class="avatar-title rounded-circle bg-primary">
                                                <i class="bx bx-check-circle font-size-24"></i>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-sm-6">
                        <div class="card mini-stats-wid h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <p class="text-muted fw-medium mb-1">Unofficial Price</p>
                                        <h4 class="mb-0">{{ number_format($unofficialPriceCount) }}</h4>
                                    </div>
                                    <div class="flex-shrink-0">
                                        <div class="avatar-sm rounded-circle bg-primary mini-stat-icon">
                                            <span class="avatar-title rounded-circle bg-primary">
                                                <i class="bx bx-store font-size-24"></i>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-sm-6">
                        <div class="card mini-stats-wid h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <p class="text-muted fw-medium mb-1">Both Prices</p>
                                        <h4 class="mb-0">{{ number_format($bothPricesCount) }}</h4>
                                    </div>
                                    <div class="flex-shrink-0">
                                        <div class="avatar-sm rounded-circle bg-primary mini-stat-icon">
                                            <span class="avatar-title rounded-circle bg-primary">
                                                <i class="bx bx-git-compare font-size-24"></i>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ============================= 2. Data Quality ============================= --}}
                <h5 class="mb-3 mt-3">Data Quality</h5>
                <div class="row">
                    <div class="col-xl-4 col-md-6">
                        <div class="card {{ $pendingReviewTotal > 0 ? 'border-warning' : '' }} h-100">
                            <div class="card-body d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-1">Pending Review Items</h6>
                                    <h3 class="mb-0">{{ number_format($pendingReviewTotal) }}</h3>
                                </div>
                                @if($pendingReviewTotal > 0)
                                    <a href="{{ route('data-review.index') }}" class="btn btn-warning btn-sm">Review Now</a>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-4 col-md-6">
                        <div class="card {{ $openConflictsCount > 0 ? 'border-danger' : '' }} h-100">
                            <div class="card-body d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-1">Phone / Spec Conflicts</h6>
                                    <h3 class="mb-0">{{ number_format($openConflictsCount) }}</h3>
                                </div>
                                @if($openConflictsCount > 0)
                                    <a href="{{ route('data-review.index') }}" class="btn btn-danger btn-sm">Resolve Now</a>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-4 col-md-6">
                        <div class="card {{ $duplicateReviewCount > 0 ? 'border-warning' : '' }} h-100">
                            <div class="card-body d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-1">Possible Duplicates</h6>
                                    <h3 class="mb-0">{{ number_format($duplicateReviewCount) }}</h3>
                                </div>
                                @if($duplicateReviewCount > 0)
                                    <a href="{{ route('data-review.index') }}" class="btn btn-warning btn-sm">Review Now</a>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-xl-4 col-md-6">
                        <div class="card h-100">
                            <div class="card-body d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-1">Image Matches Needing Review</h6>
                                    <h3 class="mb-0">{{ number_format($imageReviewCount) }}</h3>
                                </div>
                                @if($imageReviewCount > 0)
                                    <a href="{{ route('data-review.index') }}" class="btn btn-outline-primary btn-sm">Review Now</a>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-4 col-md-6">
                        <div class="card h-100">
                            <div class="card-body d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-1">Missing / Incomplete Data</h6>
                                    <h3 class="mb-0">{{ number_format($missingDataReviewCount) }}</h3>
                                </div>
                                @if($missingDataReviewCount > 0)
                                    <a href="{{ route('data-review.index') }}" class="btn btn-outline-primary btn-sm">Review Now</a>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-4 col-md-6">
                        <div class="card h-100">
                            <div class="card-body d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-1">Low Confidence Records</h6>
                                    <h3 class="mb-0">{{ number_format($lowConfidenceReviewCount) }}</h3>
                                </div>
                                @if($lowConfidenceReviewCount > 0)
                                    <a href="{{ route('data-review.index') }}" class="btn btn-outline-primary btn-sm">Review Now</a>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ============================= 3. Pricing Overview ============================= --}}
                <h5 class="mb-3 mt-3">Pricing Overview</h5>
                <div class="row">
                    <div class="col-lg-7">
                        <div class="card h-100">
                            <div class="card-body">
                                <h6 class="card-title mb-3">Official vs Unofficial Price Coverage</h6>

                                @php
                                    $pricedDenominator = max($totalPhones, 1);
                                    $pctBoth = round($bothPricesCount / $pricedDenominator * 100);
                                    $pctOfficial = round($onlyOfficialCount / $pricedDenominator * 100);
                                    $pctUnofficial = round($onlyUnofficialCount / $pricedDenominator * 100);
                                    $pctNone = max(0, 100 - $pctBoth - $pctOfficial - $pctUnofficial);
                                @endphp

                                <div class="progress" style="height: 22px;">
                                    <div class="progress-bar bg-info" style="width: {{ $pctBoth }}%" title="Both official and unofficial">{{ $pctBoth > 8 ? $pctBoth.'%' : '' }}</div>
                                    <div class="progress-bar bg-primary" style="width: {{ $pctOfficial }}%" title="Official only">{{ $pctOfficial > 8 ? $pctOfficial.'%' : '' }}</div>
                                    <div class="progress-bar bg-warning" style="width: {{ $pctUnofficial }}%" title="Unofficial only">{{ $pctUnofficial > 8 ? $pctUnofficial.'%' : '' }}</div>
                                    <div class="progress-bar bg-light text-dark" style="width: {{ $pctNone }}%" title="No price data">{{ $pctNone > 8 ? $pctNone.'%' : '' }}</div>
                                </div>

                                <div class="row text-center mt-3 g-2">
                                    <div class="col-6 col-md-3">
                                        <h5 class="mb-0">{{ number_format($bothPricesCount) }}</h5>
                                        <p class="text-muted mb-0 small"><i class="bx bxs-circle text-info font-size-10"></i> Both</p>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <h5 class="mb-0">{{ number_format($onlyOfficialCount) }}</h5>
                                        <p class="text-muted mb-0 small"><i class="bx bxs-circle text-primary font-size-10"></i> Official only</p>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <h5 class="mb-0">{{ number_format($onlyUnofficialCount) }}</h5>
                                        <p class="text-muted mb-0 small"><i class="bx bxs-circle text-warning font-size-10"></i> Unofficial only</p>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <h5 class="mb-0">{{ number_format($noPriceCount) }}</h5>
                                        <p class="text-muted mb-0 small"><i class="bx bxs-circle text-muted font-size-10"></i> No price</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-5">
                        <div class="row h-100">
                            <div class="col-6">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <h6 class="text-muted mb-1">Price Observations <span class="font-size-11">(7 days)</span></h6>
                                        <h3 class="mb-0">{{ number_format($priceUpdatesLast7Days) }}</h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <h6 class="text-muted mb-1">Suspicious Observations</h6>
                                        <h3 class="mb-0">{{ number_format($priceOutlierReviewCount) }}</h3>
                                        @if($priceOutlierReviewCount > 0)
                                            <a href="{{ route('data-review.index') }}" class="small">Review now &rarr;</a>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="card mb-0">
                                    <div class="card-body">
                                        <h6 class="card-title mb-3">Recent Price Changes</h6>
                                        @forelse($recentPriceChanges as $change)
                                            <div class="d-flex justify-content-between align-items-center {{ ! $loop->last ? 'mb-2 pb-2 border-bottom' : '' }}">
                                                <div class="text-truncate me-2">
                                                    <span class="text-truncate d-inline-block" style="max-width: 160px;">{{ $change->variant?->phone?->name ?? 'Unknown phone' }}</span>
                                                    <span class="badge {{ $change->price_type->badgeClass() }} ms-1">{{ $change->price_type->label() }}</span>
                                                </div>
                                                <div class="text-end flex-shrink-0">
                                                    <div class="fw-semibold">৳{{ number_format((float) $change->amount) }}</div>
                                                    <div class="text-muted small">{{ $change->changed_at?->diffForHumans() }}</div>
                                                </div>
                                            </div>
                                        @empty
                                            <p class="text-muted mb-0">No price changes recorded yet.</p>
                                        @endforelse
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ============================= 4. Import Status ============================= --}}
                <h5 class="mb-3 mt-3">Import Status</h5>
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                                    <div>
                                        <h6 class="card-title mb-2">Last Import</h6>
                                        @if($lastImport)
                                            <span class="badge {{ $lastImport->status->badgeClass() }} me-1">{{ $lastImport->status->label() }}</span>
                                            <span class="text-muted">{{ $lastImport->type->label() }} &middot; {{ $lastImport->source?->name ?? 'All sources' }}</span>
                                        @else
                                            <p class="text-muted mb-0">No imports have run yet.</p>
                                        @endif
                                    </div>
                                    <a href="{{ route('phone-import-runs.index') }}" class="btn btn-outline-primary btn-sm">All Import Runs</a>
                                </div>

                                @if($lastImport)
                                    <div class="row text-center g-3 mt-1">
                                        <div class="col-6 col-md-3">
                                            <h5 class="mb-0 text-success">{{ number_format($lastImport->total_created) }}</h5>
                                            <p class="text-muted mb-0 small">Imported</p>
                                        </div>
                                        <div class="col-6 col-md-3">
                                            <h5 class="mb-0 text-primary">{{ number_format($lastImport->total_updated) }}</h5>
                                            <p class="text-muted mb-0 small">Updated</p>
                                        </div>
                                        <div class="col-6 col-md-3">
                                            <h5 class="mb-0">{{ number_format($lastImport->total_skipped) }}</h5>
                                            <p class="text-muted mb-0 small">Skipped</p>
                                        </div>
                                        <div class="col-6 col-md-3">
                                            <h5 class="mb-0 {{ $lastImport->total_failed > 0 ? 'text-danger' : '' }}">{{ number_format($lastImport->total_failed) }}</h5>
                                            <p class="text-muted mb-0 small">Failed</p>
                                        </div>
                                    </div>
                                    <p class="text-muted mt-3 mb-2">
                                        Started {{ $lastImport->started_at?->diffForHumans() ?? '—' }}
                                        @if($lastImport->finished_at)
                                            &middot; finished {{ $lastImport->finished_at->diffForHumans() }}
                                        @endif
                                    </p>
                                    <a href="{{ route('phone-import-runs.show', $lastImport->id) }}" class="btn btn-sm btn-outline-primary">View Details</a>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ============================= 5. Image Status ============================= --}}
                <div class="d-flex justify-content-between align-items-center mb-3 mt-3">
                    <h5 class="mb-0">Image Status</h5>
                    <a href="{{ route('data-review.index') }}" class="small">Open Data Review &rarr;</a>
                </div>
                <div class="row">
                    <div class="col-xl-3 col-sm-6">
                        <div class="card h-100">
                            <div class="card-body">
                                <h6 class="text-muted mb-1">Published</h6>
                                <h3 class="mb-0">{{ number_format($phonesWithPublishedImage) }}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-sm-6">
                        <div class="card h-100 {{ $imagesNeedingReview > 0 ? 'border-warning' : '' }}">
                            <div class="card-body d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-1">Needs Review</h6>
                                    <h3 class="mb-0">{{ number_format($imagesNeedingReview) }}</h3>
                                </div>
                                @if($imagesNeedingReview > 0)
                                    <a href="{{ route('data-review.index') }}" class="btn btn-warning btn-sm">Review</a>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-sm-6">
                        <div class="card h-100">
                            <div class="card-body">
                                <h6 class="text-muted mb-1">Missing Candidates</h6>
                                <h3 class="mb-0">{{ number_format($phonesMissingImages) }}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-sm-6">
                        <div class="card h-100">
                            <div class="card-body">
                                <h6 class="text-muted mb-1">Rejected</h6>
                                <h3 class="mb-0">{{ number_format($imagesRejected) }}</h3>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ============================= 6. Catalogue Distribution ============================= --}}
                <h5 class="mb-3 mt-3">Catalogue Distribution</h5>
                <div class="row">
                    <div class="col-lg-5">
                        <div class="card h-100">
                            <div class="card-body">
                                <h6 class="card-title mb-3">By Brand</h6>
                                @php $maxBrandCount = max($topBrands->max('phones_count'), 1); @endphp
                                <div class="table-responsive">
                                    <table class="table align-middle table-nowrap mb-0">
                                        <tbody>
                                        @foreach($topBrands as $brand)
                                            <tr>
                                                <td style="width: 45%">
                                                    <a href="{{ route('phones.index', ['brand_id' => $brand->id]) }}" class="text-body">{{ $brand->name }}</a>
                                                </td>
                                                <td style="width: 15%">
                                                    <span class="fw-semibold">{{ number_format($brand->phones_count) }}</span>
                                                </td>
                                                <td>
                                                    <div class="progress bg-transparent progress-sm">
                                                        <div class="progress-bar bg-primary rounded" style="width: {{ round($brand->phones_count / $maxBrandCount * 100) }}%"></div>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <a href="{{ route('brands.index') }}" class="small d-inline-block mt-2">View all brands &rarr;</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="card h-100">
                            <div class="card-body">
                                <h6 class="card-title mb-3">By Price Range</h6>
                                @php $maxBucketCount = max(collect($priceRangeBuckets)->max('count'), 1); @endphp
                                <div class="table-responsive">
                                    <table class="table align-middle table-nowrap mb-0">
                                        <tbody>
                                        @foreach($priceRangeBuckets as $bucket)
                                            <tr>
                                                <td style="width: 50%">{{ $bucket['label'] }}</td>
                                                <td style="width: 15%">
                                                    <span class="fw-semibold">{{ number_format($bucket['count']) }}</span>
                                                </td>
                                                <td>
                                                    <div class="progress bg-transparent progress-sm">
                                                        <div class="progress-bar bg-primary rounded" style="width: {{ round($bucket['count'] / $maxBucketCount * 100) }}%"></div>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                @if($noPriceCount > 0)
                                    <p class="text-muted small mt-2 mb-0">{{ number_format($noPriceCount) }} phone(s) have no price data yet and aren't included above.</p>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3">
                        <div class="card h-100">
                            <div class="card-body">
                                <h6 class="card-title mb-3">Availability</h6>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Both</span>
                                    <span class="fw-semibold">{{ number_format($bothPricesCount) }}</span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Official only</span>
                                    <span class="fw-semibold">{{ number_format($onlyOfficialCount) }}</span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Unofficial only</span>
                                    <span class="fw-semibold">{{ number_format($onlyUnofficialCount) }}</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">No price data</span>
                                    <span class="fw-semibold">{{ number_format($noPriceCount) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            @endif

        </div>
        <!-- container-fluid -->
    </div>
    <!-- End Page-content -->

@endsection
