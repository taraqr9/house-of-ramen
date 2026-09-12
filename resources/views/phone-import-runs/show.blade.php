@extends('layout.master', ['page_title' => $page_title])

@section('content')
    <div class="page-content">
        <div class="container-fluid">

            @include('partials.breadcrumb', [
                'items' => [['label' => 'Import Runs', 'url' => route('phone-import-runs.index')], ['label' => '#'.$run->id]],
            ])

            <div class="row mb-3">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h5 class="mb-1">
                                        Run #{{ $run->id }}
                                        <span class="badge {{ $run->status->badgeClass() }}">{{ $run->status->label() }}</span>
                                    </h5>
                                    <p class="text-muted mb-0">
                                        Source: {{ $run->source?->name ?? '-' }} &middot; Type: {{ $run->type->label() }}
                                        &middot; Started: {{ $run->started_at?->format('d M Y, h:i A') ?? '-' }}
                                        @if($run->finished_at) &middot; Finished: {{ $run->finished_at->format('d M Y, h:i A') }} @endif
                                    </p>
                                    @if($run->error_message)
                                        <div class="alert alert-danger mt-2 mb-0">{{ $run->error_message }}</div>
                                    @endif
                                </div>
                                <a href="{{ route('phone-import-runs.index') }}" class="btn btn-secondary btn-sm"><i class="mdi mdi-arrow-left me-1"></i> Back</a>
                            </div>

                            <div class="row mt-3 text-center">
                                <div class="col">Discovered<h5>{{ $run->total_discovered }}</h5></div>
                                <div class="col">Created<h5>{{ $run->total_created }}</h5></div>
                                <div class="col">Updated<h5>{{ $run->total_updated }}</h5></div>
                                <div class="col">Failed<h5>{{ $run->total_failed }}</h5></div>
                                <div class="col">Conflicts<h5>{{ $run->total_conflicts }}</h5></div>
                                <div class="col">Flagged<h5>{{ $run->total_flagged }}</h5></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title mb-3">Records</h6>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="table-light">
                                    <tr>
                                        <th>External Ref</th>
                                        <th>Phone</th>
                                        <th>Match Status</th>
                                        <th>Confidence</th>
                                        <th>Error</th>
                                        <th>Processed</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @forelse($records as $record)
                                        <tr>
                                            <td><code>{{ $record->external_ref }}</code></td>
                                            <td>
                                                @if($record->phone)
                                                    <a href="{{ route('phones.edit', $record->phone_id) }}">{{ $record->phone->name }}</a>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td><span class="badge {{ $record->match_status->badgeClass() }}">{{ $record->match_status->label() }}</span></td>
                                            <td>{{ $record->confidence_score ?? '-' }}</td>
                                            <td>{{ $record->error_message ? \Illuminate\Support\Str::limit($record->error_message, 60) : '-' }}</td>
                                            <td>{{ $record->processed_at?->format('d M, h:i A') ?? '-' }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="6" class="text-center text-muted">No records.</td></tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>

                            @if($records instanceof \Illuminate\Pagination\AbstractPaginator)
                                <div class="d-flex justify-content-end mt-3">{{ $records->links('partials.pagination') }}</div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
@endsection
