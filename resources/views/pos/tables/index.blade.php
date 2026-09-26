@extends('layout.master', ['page_title' => $page_title])

@section('content')
    <div class="page-content">
        <div class="container-fluid">

            @include('partials.breadcrumb', [
                'items' => [
                    ['label' => 'POS Operations'],
                    ['label' => 'Tables'],
                ],
            ])

            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">

                            <div class="row mb-3">
                                <div class="col-sm-6">
                                    <h5 class="card-title mb-1">Tables</h5>
                                    <p class="text-muted mb-0 small">Occupied/Available is automatic - a table is occupied while it has a running order.</p>
                                </div>

                                @can('dining_table-create')
                                    <div class="col-sm-6">
                                        <div class="d-flex justify-content-end">
                                            <a href="{{ route('dining-tables.create') }}" class="btn btn-success">
                                                <i class="mdi mdi-plus"></i> Add Table
                                            </a>
                                        </div>
                                    </div>
                                @endcan
                            </div>

                            <form action="{{ route('dining-tables.index') }}" method="GET" data-mobile-filters>
                                <div class="row g-2 align-items-end">
                                    <div class="col-md-3">
                                        <label class="form-label">Area</label>
                                        <select name="area" class="form-control select2">
                                            <option value="">All Areas</option>
                                            @foreach($areas as $area)
                                                <option value="{{ $area }}" @selected(request('area') === $area)>{{ $area }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="col-md-2">
                                        <label class="form-label">Occupancy</label>
                                        <select name="occupancy" class="form-control select2">
                                            <option value="">All</option>
                                            <option value="available" @selected(request('occupancy') === 'available')>Available</option>
                                            <option value="occupied" @selected(request('occupancy') === 'occupied')>Occupied</option>
                                        </select>
                                    </div>

                                    <div class="col-md-2">
                                        <label class="form-label">Status</label>
                                        <select name="is_active" class="form-control select2">
                                            <option value="">All Status</option>
                                            <option value="1" @selected(request('is_active') === '1')>Active</option>
                                            <option value="0" @selected(request('is_active') === '0')>Inactive</option>
                                        </select>
                                    </div>

                                    <div class="col-md-5">
                                        <label class="form-label">Keyword</label>
                                        <input type="text" name="keyword" value="{{ request('keyword') }}"
                                               class="form-control" placeholder="Search by table name, area or remarks">
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end gap-2 mt-3">
                                    <a href="{{ route('dining-tables.index') }}" class="btn btn-light waves-effect">Reset</a>
                                    <button type="submit" class="btn btn-primary waves-effect waves-light">
                                        <i class="mdi mdi-magnify me-1"></i> Search
                                    </button>
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
                                <table class="table table-mobile-cards table-hover align-middle">
                                    <thead class="table-light">
                                    <tr>
                                        <th data-mc="title">Table</th>
                                        <th>Area</th>
                                        <th style="width: 90px;">Seats</th>
                                        <th style="width: 80px;">Order</th>
                                        <th style="width: 180px;">Occupancy</th>
                                        <th class="text-center" style="width: 100px;">Status</th>
                                        <th class="text-center" style="width: 160px;" data-mc="actions">Action</th>
                                    </tr>
                                    </thead>

                                    <tbody>
                                    @forelse($tables as $table)
                                        <tr>
                                            <td class="fw-semibold">{{ $table->name }}</td>
                                            <td>{{ $table->area ?? '—' }}</td>
                                            <td>{{ $table->capacity }}</td>
                                            <td>{{ $table->display_order }}</td>
                                            <td>
                                                @if($table->activeOrder)
                                                    <span class="badge bg-danger">Occupied</span>
                                                    @can('order-view')
                                                        <a href="{{ route('pos-orders.show', $table->activeOrder->id) }}" class="small ms-1">{{ $table->activeOrder->order_number }}</a>
                                                    @endcan
                                                @else
                                                    <span class="badge bg-success">Available</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <span class="badge {{ $table->is_active ? 'bg-success' : 'bg-danger' }}">{{ $table->is_active ? 'Active' : 'Inactive' }}</span>
                                            </td>
                                            <td class="text-center">
                                                @can('dining_table-edit')
                                                    <a href="{{ route('dining-tables.edit', $table->id) }}" class="btn btn-sm btn-warning">Edit</a>
                                                @endcan

                                                @can('dining_table-delete')
                                                    <form action="{{ route('dining-tables.destroy', $table->id) }}" method="POST" class="d-inline delete-form">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="button" class="btn btn-sm btn-danger delete-btn">Delete</button>
                                                    </form>
                                                @endcan
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="text-center text-muted">No tables found.</td>
                                        </tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>

                            <div class="d-flex justify-content-end mt-3">
                                {{ $tables->links('partials.pagination') }}
                            </div>

                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
@endsection

@section('JScript')
    <script>
        $(document).ready(function () {
            $('.select2').select2({width: '100%', allowClear: true});
        });

        $(document).on('click', '.delete-btn', function () {
            let form = $(this).closest('form');

            Swal.fire({
                title: 'Are you sure?',
                text: 'This table will be deleted. Past orders keep their table name.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#f46a6a',
                cancelButtonColor: '#74788d',
                confirmButtonText: 'Yes delete it'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    </script>
@endsection
