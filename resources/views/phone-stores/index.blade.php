@extends('layout.master', ['page_title' => $page_title])

@section('content')
    <div class="page-content">
        <div class="container-fluid">

            @include('partials.breadcrumb', [
                'items' => [
                    ['label' => 'Phone Data'],
                    ['label' => 'Retailers & Stores'],
                ],
            ])

            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-sm-6">
                                    <h5 class="card-title mb-1">Store Filters</h5>
                                </div>
                                @can('phone_store-create')
                                    <div class="col-sm-6">
                                        <div class="d-flex justify-content-end">
                                            <a href="{{ route('phone-stores.create') }}" class="btn btn-success">
                                                <i class="mdi mdi-plus"></i> Add Store
                                            </a>
                                        </div>
                                    </div>
                                @endcan
                            </div>

                            <form action="{{ route('phone-stores.index') }}" method="GET">
                                <div class="row g-2 align-items-end">
                                    <div class="col-md-4">
                                        <label class="form-label">Keyword</label>
                                        <input type="text" name="keyword" value="{{ request('keyword') }}" class="form-control" placeholder="Search by name">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Type</label>
                                        <select name="type" class="form-control select2">
                                            <option value="">All Types</option>
                                            @foreach(['official' => 'Official', 'authorized' => 'Authorized', 'marketplace' => 'Marketplace', 'other' => 'Other'] as $value => $label)
                                                <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-end gap-2 mt-3">
                                    <a href="{{ route('phone-stores.index') }}" class="btn btn-light waves-effect">Reset</a>
                                    <button type="submit" class="btn btn-primary waves-effect waves-light"><i class="mdi mdi-magnify me-1"></i> Search</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="table-light">
                                    <tr>
                                        <th style="width: 60px;">#</th>
                                        <th>Name</th>
                                        <th>Type</th>
                                        <th>Website</th>
                                        <th>Status</th>
                                        <th class="text-center" style="width: 160px;">Action</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @forelse($stores as $store)
                                        <tr>
                                            <td>{{ $loop->iteration + ($stores->currentPage() - 1) * $stores->perPage() }}</td>
                                            <td><strong>{{ $store->name }}</strong></td>
                                            <td><span class="badge bg-secondary">{{ ucfirst($store->type) }}</span></td>
                                            <td>
                                                @if($store->website_url)
                                                    <a href="{{ $store->website_url }}" target="_blank" rel="noopener">{{ $store->website_url }}</a>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge {{ $store->is_active ? 'bg-success' : 'bg-danger' }}">
                                                    {{ $store->is_active ? 'Active' : 'Inactive' }}
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                @can('phone_store-edit')
                                                    <a href="{{ route('phone-stores.edit', $store->id) }}" class="btn btn-sm btn-warning">Edit</a>
                                                @endcan
                                                @can('phone_store-delete')
                                                    <form action="{{ route('phone-stores.destroy', $store->id) }}" method="POST" class="d-inline delete-form">
                                                        @csrf @method('DELETE')
                                                        <button type="button" class="btn btn-sm btn-danger delete-btn">Delete</button>
                                                    </form>
                                                @endcan
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="6" class="text-center text-muted">No stores found.</td></tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>

                            @if($stores instanceof \Illuminate\Pagination\AbstractPaginator)
                                <div class="d-flex justify-content-end mt-3">{{ $stores->links('partials.pagination') }}</div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
@endsection

@section('JScript')
    <script>
        $(document).ready(function () { $('.select2').select2({width: '100%', allowClear: true}); });
        $(document).on('click', '.delete-btn', function () {
            let form = $(this).closest('form');
            Swal.fire({
                title: 'Are you sure?', text: 'This store will be deleted', icon: 'warning',
                showCancelButton: true, confirmButtonColor: '#f46a6a', cancelButtonColor: '#74788d', confirmButtonText: 'Yes delete it'
            }).then((result) => { if (result.isConfirmed) form.submit(); });
        });
    </script>
@endsection
