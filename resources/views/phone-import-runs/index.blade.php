@extends('layout.master', ['page_title' => $page_title])

@section('content')
    <div class="page-content">
        <div class="container-fluid">

            @include('partials.breadcrumb', [
                'items' => [['label' => 'Imports'], ['label' => 'Runs']],
            ])

            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Import Runs</h5>

                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Source</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th>Discovered</th>
                                        <th>Created</th>
                                        <th>Updated</th>
                                        <th>Failed</th>
                                        <th>Conflicts</th>
                                        <th>Flagged</th>
                                        <th>Started</th>
                                        <th class="text-center" style="width: 90px;">Action</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @forelse($runs as $run)
                                        <tr>
                                            <td>{{ $run->id }}</td>
                                            <td>{{ $run->source?->name ?? '-' }}</td>
                                            <td>{{ $run->type->label() }}</td>
                                            <td><span class="badge {{ $run->status->badgeClass() }}">{{ $run->status->label() }}</span></td>
                                            <td>{{ $run->total_discovered }}</td>
                                            <td>{{ $run->total_created }}</td>
                                            <td>{{ $run->total_updated }}</td>
                                            <td>{{ $run->total_failed }}</td>
                                            <td>{{ $run->total_conflicts }}</td>
                                            <td>{{ $run->total_flagged }}</td>
                                            <td>{{ $run->started_at?->format('d M Y, h:i A') ?? '-' }}</td>
                                            <td class="text-center">
                                                <a href="{{ route('phone-import-runs.show', $run->id) }}" class="btn btn-sm btn-info">View</a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="12" class="text-center text-muted">No import runs yet.</td></tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>

                            @if($runs instanceof \Illuminate\Pagination\AbstractPaginator)
                                <div class="d-flex justify-content-end mt-3">{{ $runs->links('partials.pagination') }}</div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
@endsection
