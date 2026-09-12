@extends('layout.master', ['page_title' => $page_title])

@section('content')
    <div class="page-content">
        <div class="container-fluid">

            @include('partials.breadcrumb', [
                'items' => [['label' => 'Imports'], ['label' => 'Data Sources']],
            ])

            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Data Sources</h5>
                            <p class="text-muted">
                                Sources are registered in <code>config/phone_sources.php</code>. Reliability,
                                review requirement, and active state can be tuned here without touching code.
                            </p>

                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="table-light">
                                    <tr>
                                        <th>Name</th>
                                        <th>Key</th>
                                        <th>Type</th>
                                        <th>Reliability</th>
                                        <th>Requires Review</th>
                                        <th>Runs</th>
                                        <th>Last Run</th>
                                        <th>Status</th>
                                        <th class="text-center" style="width: 100px;">Action</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @forelse($sources as $source)
                                        <tr>
                                            <td><strong>{{ $source->name }}</strong></td>
                                            <td><code>{{ $source->key }}</code></td>
                                            <td><span class="badge {{ $source->type->badgeClass() }}">{{ $source->type->label() }}</span></td>
                                            <td>{{ $source->reliability_score }}/100</td>
                                            <td>
                                                @if($source->requires_review)
                                                    <span class="badge bg-warning">Yes</span>
                                                @else
                                                    <span class="badge bg-secondary">No</span>
                                                @endif
                                            </td>
                                            <td>{{ $source->import_runs_count }}</td>
                                            <td>{{ $source->last_run_at?->diffForHumans() ?? 'Never' }}</td>
                                            <td>
                                                <span class="badge {{ $source->is_active ? 'bg-success' : 'bg-danger' }}">
                                                    {{ $source->is_active ? 'Active' : 'Disabled' }}
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                @can('phone_source-edit')
                                                    <a href="{{ route('phone-sources.edit', $source->id) }}" class="btn btn-sm btn-warning">Edit</a>
                                                @endcan
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="9" class="text-center text-muted">No sources registered yet - run <code>php artisan phones:import</code> once to sync them.</td></tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
@endsection
