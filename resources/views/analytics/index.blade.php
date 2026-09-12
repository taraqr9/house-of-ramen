@extends('layout.master')

@section('content')

    <div class="page-content">
        <div class="container-fluid">

            @include('partials.breadcrumb', [
                'items' => [
                    ['label' => 'Dashboard', 'url' => route('dashboard')],
                    ['label' => 'Analytics'],
                ],
            ])

            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                <h5 class="mb-0">Analytics</h5>
                <div class="btn-group btn-group-sm" role="group" aria-label="Analytics date range">
                    @foreach($analyticsRangeOptions as $option)
                        <a
                            href="{{ route('analytics.index', ['range' => $option['key']]) }}"
                            class="btn {{ $analytics['range']->key === $option['key'] ? 'btn-primary' : 'btn-outline-primary' }}"
                        >{{ $option['label'] }}</a>
                    @endforeach
                </div>
            </div>

            {{-- ============================= Overview ============================= --}}
            @php $overview = $analytics['overview']; @endphp
            @if($overview['status'] === 'error')
                <div class="card mb-3"><div class="card-body">@include('partials.analytics.unavailable')</div></div>
            @else
                <div class="row">
                    <div class="col-xl-3 col-sm-6">
                        <div class="card mini-stats-wid h-100">
                            <div class="card-body">
                                <p class="text-muted fw-medium mb-1">Users</p>
                                <h4 class="mb-0">{{ number_format($overview['users']) }}</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-sm-6">
                        <div class="card mini-stats-wid h-100">
                            <div class="card-body">
                                <p class="text-muted fw-medium mb-1">New Users</p>
                                <h4 class="mb-0">{{ number_format($overview['new_users']) }}</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-sm-6">
                        <div class="card mini-stats-wid h-100">
                            <div class="card-body">
                                <p class="text-muted fw-medium mb-1">Sessions</p>
                                <h4 class="mb-0">{{ number_format($overview['sessions']) }}</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-sm-6">
                        <div class="card mini-stats-wid h-100">
                            <div class="card-body">
                                <p class="text-muted fw-medium mb-1">Page Views</p>
                                <h4 class="mb-0">{{ number_format($overview['page_views']) }}</h4>
                                <p class="text-muted small mb-0">Engagement: {{ $overview['engagement_rate'] }}%</p>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            {{-- ============================= Visitors over time ============================= --}}
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title mb-3">Visitors</h6>
                            @php $visitors = $analytics['visitors_over_time']; @endphp
                            @if($visitors['status'] === 'error')
                                @include('partials.analytics.unavailable')
                            @elseif($visitors['status'] === 'empty')
                                @include('partials.analytics.empty')
                            @elseif(count($visitors['rows']) < 2)
                                {{-- A single day of data isn't worth a line chart - show it plainly instead. --}}
                                <div class="row text-center g-3">
                                    @foreach($visitors['rows'] as $row)
                                        <div class="col-6 col-md-3">
                                            <h5 class="mb-0">{{ number_format($row['users']) }}</h5>
                                            <p class="text-muted mb-0 small">Users - {{ $row['label'] }}</p>
                                        </div>
                                        <div class="col-6 col-md-3">
                                            <h5 class="mb-0">{{ number_format($row['sessions']) }}</h5>
                                            <p class="text-muted mb-0 small">Sessions - {{ $row['label'] }}</p>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div id="visitors-chart" data-series="{{ json_encode([
                                    ['name' => 'Users', 'data' => collect($visitors['rows'])->pluck('users')],
                                    ['name' => 'Sessions', 'data' => collect($visitors['rows'])->pluck('sessions')],
                                ]) }}" data-categories="{{ json_encode(collect($visitors['rows'])->pluck('label')) }}"></div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                {{-- Most Viewed Phones --}}
                <div class="col-lg-6">
                    <div class="card h-100">
                        <div class="card-body">
                            <h6 class="card-title mb-3">Most Viewed Phones</h6>
                            @if($analytics['most_viewed_phones']['status'] === 'error')
                                @include('partials.analytics.unavailable')
                            @elseif($analytics['most_viewed_phones']['status'] === 'empty')
                                @include('partials.analytics.empty')
                            @else
                                <div class="table-responsive">
                                    <table class="table align-middle table-nowrap mb-0">
                                        <thead>
                                            <tr class="text-muted small">
                                                <th>Phone</th>
                                                <th class="text-end">Views</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        @foreach($analytics['most_viewed_phones']['rows'] as $row)
                                            <tr>
                                                <td>
                                                    @if($row['admin_url'])
                                                        <a href="{{ $row['admin_url'] }}" class="text-body fw-medium">{{ $row['phone_name'] }}</a>
                                                    @else
                                                        <span class="fw-medium">{{ $row['phone_name'] }}</span>
                                                    @endif
                                                    <br><span class="text-muted small">{{ $row['brand'] }}</span>
                                                </td>
                                                <td class="text-end fw-semibold">{{ number_format($row['views']) }}</td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Budget Ranges --}}
                <div class="col-lg-6">
                    <div class="card h-100">
                        <div class="card-body">
                            <h6 class="card-title mb-3">Most Popular Budget Ranges</h6>
                            @if($analytics['budget_ranges']['status'] === 'error')
                                @include('partials.analytics.unavailable')
                            @elseif($analytics['budget_ranges']['status'] === 'empty')
                                @include('partials.analytics.empty')
                            @else
                                <div class="table-responsive">
                                    <table class="table align-middle table-nowrap mb-0">
                                        <tbody>
                                        @foreach($analytics['budget_ranges']['rows'] as $row)
                                            <tr>
                                                <td style="width: 40%">{{ $row['label'] }}</td>
                                                <td style="width: 15%" class="fw-semibold">{{ $row['percent'] }}%</td>
                                                <td>
                                                    <div class="progress bg-transparent progress-sm">
                                                        <div class="progress-bar bg-primary rounded" style="width: {{ $row['percent'] }}%"></div>
                                                    </div>
                                                </td>
                                                <td class="text-end text-muted small">{{ number_format($row['count']) }}</td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                {{-- Brand Views --}}
                <div class="col-lg-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h6 class="card-title mb-3">Most Viewed Brands</h6>
                            @if($analytics['brand_views']['status'] === 'error')
                                @include('partials.analytics.unavailable')
                            @elseif($analytics['brand_views']['status'] === 'empty')
                                @include('partials.analytics.empty')
                            @else
                                <div class="table-responsive">
                                    <table class="table align-middle table-nowrap mb-0">
                                        <tbody>
                                        @foreach($analytics['brand_views']['rows'] as $row)
                                            <tr>
                                                <td>{{ $row['brand'] }}</td>
                                                <td class="text-end fw-semibold">{{ number_format($row['views']) }}</td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Top Searches --}}
                <div class="col-lg-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h6 class="card-title mb-3">Top Searches</h6>
                            @if($analytics['top_searches']['status'] === 'error')
                                @include('partials.analytics.unavailable')
                            @elseif($analytics['top_searches']['status'] === 'empty')
                                @include('partials.analytics.empty')
                            @else
                                <div class="table-responsive">
                                    <table class="table align-middle table-nowrap mb-0">
                                        <tbody>
                                        @foreach($analytics['top_searches']['rows'] as $row)
                                            <tr>
                                                <td>{{ $row['term'] }}</td>
                                                <td class="text-end fw-semibold">{{ number_format($row['count']) }}</td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Most Compared Phones --}}
                <div class="col-lg-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h6 class="card-title mb-3">Most Compared Phones</h6>
                            @if($analytics['compared_phones']['status'] === 'error')
                                @include('partials.analytics.unavailable')
                            @elseif($analytics['compared_phones']['status'] === 'empty')
                                @include('partials.analytics.empty')
                            @else
                                <div class="table-responsive">
                                    <table class="table align-middle table-nowrap mb-0">
                                        <tbody>
                                        @foreach($analytics['compared_phones']['rows'] as $row)
                                            <tr>
                                                <td>
                                                    {{ $row['phone_name'] }}
                                                    <br><span class="text-muted small">{{ $row['brand'] }}</span>
                                                </td>
                                                <td class="text-end fw-semibold">{{ number_format($row['count']) }}</td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <p class="text-muted small mt-2 mb-0">How often each phone appeared in a comparison - GA4 can't reliably reconstruct which specific phones were compared against each other.</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- ============================= Find My Phone ============================= --}}
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title mb-3">Find My Phone</h6>
                            @if($analytics['find_my_phone']['status'] === 'error')
                                @include('partials.analytics.unavailable')
                            @else
                                @php $fmp = $analytics['find_my_phone']; @endphp
                                <div class="row text-center g-3 mb-4">
                                    <div class="col-6 col-md-2">
                                        <h5 class="mb-0">{{ number_format($fmp['started']) }}</h5>
                                        <p class="text-muted mb-0 small">Started</p>
                                    </div>
                                    <div class="col-6 col-md-2">
                                        <h5 class="mb-0">{{ number_format($fmp['completed']) }}</h5>
                                        <p class="text-muted mb-0 small">Completed</p>
                                    </div>
                                    <div class="col-6 col-md-2">
                                        <h5 class="mb-0">{{ $fmp['completion_rate'] !== null ? $fmp['completion_rate'].'%' : '—' }}</h5>
                                        <p class="text-muted mb-0 small">Completion Rate</p>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <h5 class="mb-0">{{ number_format($fmp['recommendation_views']) }}</h5>
                                        <p class="text-muted mb-0 small">Recommendation Views</p>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <h5 class="mb-0">{{ number_format($fmp['recommendation_clicks']) }}</h5>
                                        <p class="text-muted mb-0 small">Recommendation Clicks</p>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-lg-4">
                                        <h6 class="text-muted small text-uppercase mb-2">Popular Budget Ranges</h6>
                                        @if($fmp['budget_ranges']['status'] === 'error')
                                            @include('partials.analytics.unavailable')
                                        @elseif($fmp['budget_ranges']['status'] === 'empty')
                                            @include('partials.analytics.empty')
                                        @else
                                            <ul class="list-unstyled mb-0">
                                                @foreach($fmp['budget_ranges']['rows'] as $row)
                                                    <li class="d-flex justify-content-between mb-1">
                                                        <span>{{ $row['label'] }}</span>
                                                        <span class="fw-semibold">{{ $row['percent'] }}%</span>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    </div>
                                    <div class="col-lg-4">
                                        <h6 class="text-muted small text-uppercase mb-2">Usage Categories</h6>
                                        @if($fmp['usage_categories']['status'] === 'error')
                                            @include('partials.analytics.unavailable')
                                        @elseif($fmp['usage_categories']['status'] === 'empty')
                                            @include('partials.analytics.empty')
                                        @else
                                            <ul class="list-unstyled mb-0">
                                                @foreach($fmp['usage_categories']['rows'] as $row)
                                                    <li class="d-flex justify-content-between mb-1">
                                                        <span class="text-capitalize">{{ $row['label'] }}</span>
                                                        <span class="fw-semibold">{{ $row['percent'] }}%</span>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    </div>
                                    <div class="col-lg-4">
                                        <h6 class="text-muted small text-uppercase mb-2">Most Selected Brands</h6>
                                        @if($fmp['brands']['status'] === 'error')
                                            @include('partials.analytics.unavailable')
                                        @elseif($fmp['brands']['status'] === 'empty')
                                            @include('partials.analytics.empty')
                                        @else
                                            <ul class="list-unstyled mb-0">
                                                @foreach($fmp['brands']['rows'] as $row)
                                                    <li class="d-flex justify-content-between mb-1">
                                                        <span>{{ $row['label'] }}</span>
                                                        <span class="fw-semibold">{{ number_format($row['count']) }}</span>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

        </div>
        <!-- container-fluid -->
    </div>
    <!-- End Page-content -->

@endsection

@section('JScript')
    <script>
        (function () {
            var el = document.getElementById('visitors-chart');
            if (!el || typeof ApexCharts === 'undefined') return;

            var series = JSON.parse(el.getAttribute('data-series'));
            var categories = JSON.parse(el.getAttribute('data-categories'));

            new ApexCharts(el, {
                chart: { type: 'area', height: 300, toolbar: { show: false }, zoom: { enabled: false } },
                series: series,
                xaxis: { categories: categories },
                dataLabels: { enabled: false },
                stroke: { curve: 'smooth', width: 2 },
                colors: ['#556ee6', '#34c38f'],
                legend: { position: 'top' },
                fill: { type: 'gradient', gradient: { opacityFrom: 0.4, opacityTo: 0.05 } },
            }).render();
        })();
    </script>
@endsection
