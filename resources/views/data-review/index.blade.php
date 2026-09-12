@extends('layout.master', ['page_title' => $page_title])

@section('content')
    <div class="page-content">
        <div class="container-fluid">

            @include('partials.breadcrumb', [
                'items' => [['label' => 'Data Review']],
            ])

            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title mb-1">Pending Reviews</h5>
                            <p class="text-muted">New/uncertain phones and possible duplicates that were not auto-approved.</p>

                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="table-light">
                                    <tr>
                                        <th>Reason</th>
                                        <th>Phone / Candidate</th>
                                        <th>Matched Against</th>
                                        <th>Similarity / Confidence</th>
                                        <th>Source</th>
                                        <th class="text-center" style="width: 320px;">Action</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @forelse($reviews as $review)
                                        <tr>
                                            <td><span class="badge bg-warning">{{ $review->reason->label() }}</span></td>
                                            <td>
                                                @if($review->phone)
                                                    <a href="{{ route('phones.edit', $review->phone_id) }}">{{ $review->phone->name }}</a>
                                                @else
                                                    {{ $review->importRecord?->normalized_payload['model'] ?? 'New candidate' }}
                                                    <span class="text-muted d-block small">not yet created</span>
                                                @endif
                                            </td>
                                            <td>{{ $review->matchedPhone?->name ?? '-' }}</td>
                                            <td>{{ $review->similarity_score ?? data_get($review->details, 'overall_confidence') ?? '-' }}</td>
                                            <td>{{ $review->importRecord?->source?->name ?? '-' }}</td>
                                            <td class="text-center">
                                                @can('phone_data_review-edit')
                                                    <form action="{{ route('data-review.reviews.resolve', $review->id) }}" method="POST" class="d-inline">
                                                        @csrf
                                                        <input type="hidden" name="action" value="approve">
                                                        <button type="submit" class="btn btn-sm btn-success">
                                                            {{ $review->reason->value === 'possible_duplicate' ? 'Approve as New' : 'Approve' }}
                                                        </button>
                                                    </form>
                                                    @if($review->reason->value === 'possible_duplicate' && $review->matched_phone_id)
                                                        <form action="{{ route('data-review.reviews.resolve', $review->id) }}" method="POST" class="d-inline">
                                                            @csrf
                                                            <input type="hidden" name="action" value="merge">
                                                            <button type="submit" class="btn btn-sm btn-primary">Same as {{ $review->matchedPhone?->name }}</button>
                                                        </form>
                                                    @endif
                                                    <form action="{{ route('data-review.reviews.resolve', $review->id) }}" method="POST" class="d-inline">
                                                        @csrf
                                                        <input type="hidden" name="action" value="reject">
                                                        <button type="submit" class="btn btn-sm btn-danger">Reject</button>
                                                    </form>
                                                    <form action="{{ route('data-review.reviews.resolve', $review->id) }}" method="POST" class="d-inline">
                                                        @csrf
                                                        <input type="hidden" name="action" value="ignore">
                                                        <button type="submit" class="btn btn-sm btn-light">Ignore</button>
                                                    </form>
                                                @endcan
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="6" class="text-center text-muted">Nothing pending review.</td></tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>

                            @if($reviews instanceof \Illuminate\Pagination\AbstractPaginator)
                                <div class="d-flex justify-content-end mt-3">{{ $reviews->links('partials.pagination') }}</div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title mb-1">Open Conflicts</h5>
                            <p class="text-muted">Two sources disagree on a value - pick which one is right.</p>

                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="table-light">
                                    <tr>
                                        <th>Phone</th>
                                        <th>Field</th>
                                        <th>Existing</th>
                                        <th>New</th>
                                        <th class="text-center" style="width: 360px;">Action</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @forelse($conflicts as $conflict)
                                        <tr>
                                            <td><a href="{{ route('phones.edit', $conflict->phone_id) }}">{{ $conflict->phone->name }}</a></td>
                                            <td><code>{{ $conflict->field }}</code></td>
                                            <td>{{ $conflict->existing_value }} <small class="text-muted">({{ $conflict->existingSource?->name ?? 'unknown' }})</small></td>
                                            <td>{{ $conflict->new_value }} <small class="text-muted">({{ $conflict->newSource?->name ?? 'unknown' }})</small></td>
                                            <td class="text-center">
                                                @can('phone_data_conflict-edit')
                                                    <form action="{{ route('data-review.conflicts.resolve', $conflict->id) }}" method="POST" class="d-inline">
                                                        @csrf
                                                        <input type="hidden" name="action" value="accept_new">
                                                        <button type="submit" class="btn btn-sm btn-success">Accept New</button>
                                                    </form>
                                                    <form action="{{ route('data-review.conflicts.resolve', $conflict->id) }}" method="POST" class="d-inline">
                                                        @csrf
                                                        <input type="hidden" name="action" value="keep_existing">
                                                        <button type="submit" class="btn btn-sm btn-secondary">Keep Existing</button>
                                                    </form>
                                                    <form action="{{ route('data-review.conflicts.resolve', $conflict->id) }}" method="POST" class="d-inline">
                                                        @csrf
                                                        <input type="hidden" name="action" value="ignore">
                                                        <button type="submit" class="btn btn-sm btn-light">Ignore</button>
                                                    </form>
                                                    @if($conflict->new_source_id)
                                                        <form action="{{ route('data-review.sources.mark-unreliable', $conflict->new_source_id) }}" method="POST" class="d-inline">
                                                            @csrf
                                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Mark the new value's source unreliable">Mark Source Unreliable</button>
                                                        </form>
                                                    @endif
                                                @endcan
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="text-center text-muted">No open conflicts.</td></tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>

                            @if($conflicts instanceof \Illuminate\Pagination\AbstractPaginator)
                                <div class="d-flex justify-content-end mt-3">{{ $conflicts->links('partials.pagination') }}</div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
@endsection
