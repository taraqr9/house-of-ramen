@extends('layout.master', ['page_title' => $page_title])

@section('content')
    <div class="page-content">
        <div class="container-fluid">

            @include('partials.breadcrumb', [
                'items' => [
                    ['label' => 'Restaurant'],
                    ['label' => 'Menu Categories'],
                ],
            ])

            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">

                            <div class="row mb-3">
                                <div class="col-sm-6">
                                    <h5 class="card-title mb-1">Menu Categories</h5>
                                </div>

                                @can('restaurant_menu_category-create')
                                    <div class="col-sm-6">
                                        <div class="d-flex justify-content-end">
                                            <a href="{{ route('restaurant-menu-categories.create') }}" class="btn btn-success">
                                                <i class="mdi mdi-plus"></i> Add Category
                                            </a>
                                        </div>
                                    </div>
                                @endcan
                            </div>

                            <form action="{{ route('restaurant-menu-categories.index') }}" method="GET">
                                <div class="row g-2 align-items-end">
                                    <div class="col-md-4">
                                        <label class="form-label">Keyword</label>
                                        <input type="text" name="keyword" value="{{ request('keyword') }}"
                                               class="form-control" placeholder="Search by name">
                                    </div>

                                    <div class="col-md-2">
                                        <label class="form-label">Status</label>
                                        <select name="is_active" class="form-control select2">
                                            <option value="">All Status</option>
                                            <option value="1" @selected(request('is_active') === '1')>Active</option>
                                            <option value="0" @selected(request('is_active') === '0')>Inactive</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end gap-2 mt-3">
                                    <a href="{{ route('restaurant-menu-categories.index') }}" class="btn btn-light waves-effect">Reset</a>
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
                                <table class="table table-hover align-middle">
                                    <thead class="table-light">
                                    <tr>
                                        <th style="width: 60px;">Order</th>
                                        <th>Name</th>
                                        <th>Items</th>
                                        <th>Status</th>
                                        <th class="text-center" style="width: 160px;">Action</th>
                                    </tr>
                                    </thead>

                                    <tbody>
                                    @forelse($categories as $category)
                                        <tr>
                                            <td>{{ $category->display_order }}</td>
                                            <td><strong>{{ $category->name }}</strong></td>
                                            <td>{{ $category->menu_items_count }}</td>
                                            <td>
                                                <span class="badge {{ $category->is_active ? 'bg-success' : 'bg-danger' }}">
                                                    {{ $category->is_active ? 'Active' : 'Inactive' }}
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                @can('restaurant_menu_category-edit')
                                                    <a href="{{ route('restaurant-menu-categories.edit', $category->id) }}" class="btn btn-sm btn-warning">Edit</a>
                                                @endcan

                                                @can('restaurant_menu_category-delete')
                                                    <form action="{{ route('restaurant-menu-categories.destroy', $category->id) }}" method="POST" class="d-inline delete-form">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="button" class="btn btn-sm btn-danger delete-btn">Delete</button>
                                                    </form>
                                                @endcan
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center text-muted">No menu categories found.</td>
                                        </tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>

                            <div class="d-flex justify-content-end mt-3">
                                {{ $categories->links('partials.pagination') }}
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
                text: 'This menu category will be deleted',
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
