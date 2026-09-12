@extends('layout.master', ['page_title' => $page_title])

@section('content')
    <div class="page-content">
        <div class="container-fluid">

            @include('partials.breadcrumb', [
                'items' => [
                    ['label' => 'Menus']
                ],
            ])

            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">

                            <div class="row mb-3">
                                <div class="col-sm-6">
                                    <h5 class="card-title mb-1">Menu Filters</h5>
                                </div>

                                @can('menu-create')
                                    <div class="col-sm-6">
                                        <div class="d-flex justify-content-end">
                                            <a href="{{ route('menus.create') }}" class="btn btn-success">
                                                <i class="mdi mdi-plus"></i> Add Menu
                                            </a>
                                        </div>
                                    </div>
                                @endcan
                            </div>

                            <form action="{{ route('menus.index') }}" method="GET">
                                <div class="row g-2 align-items-end">

                                    <div class="col-md-2">
                                        <label class="form-label">Parent Menu</label>
                                        <select name="parent_id" class="form-control select2">
                                            <option value="">All Parent Menus</option>

                                            @foreach($parentMenus as $parentMenu)
                                                <option
                                                    value="{{ $parentMenu->id }}" @selected(request('parent_id') === $parentMenu->id)>
                                                    {{ $parentMenu->title }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="col-md-2">
                                        <label class="form-label">Status</label>
                                        <select name="is_active" class="form-control select2">
                                            <option value="">All Status</option>

                                            @foreach($statuses as $value => $label)
                                                <option
                                                    value="{{ $value }}" @selected(request('is_active') === $value)>
                                                    {{ $label }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                </div>

                                <div class="d-flex justify-content-end gap-2 mt-3">
                                    <a href="{{ route('menus.index') }}" class="btn btn-light waves-effect">
                                        Reset
                                    </a>

                                    <button type="submit" class="btn btn-primary waves-effect waves-light">
                                        <i class="mdi mdi-magnify me-1"></i>
                                        Search
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
                                        <th style="width: 60px;">#</th>
                                        <th>Title</th>
                                        <th>Icon</th>
                                        <th>Route</th>
                                        <th>Permission</th>
                                        <th>Parent</th>
                                        <th>Serial</th>
                                        <th>Status</th>
                                        <th class="text-center" style="width: 160px;">Action</th>
                                    </tr>
                                    </thead>

                                    <tbody>

                                    @forelse($menu_list as $menu)
                                        <tr>
                                            <td>
                                                {{ $loop->iteration + ($menu_list->currentPage() - 1) * $menu_list->perPage() }}
                                            </td>

                                            <td>
                                                @if($menu->parent_id)
                                                    <span class="ms-3">↳ {{ $menu->title }}</span>
                                                @else
                                                    <strong>{{ $menu->title }}</strong>
                                                @endif
                                            </td>

                                            <td>
                                                @if($menu->icon)
                                                    <i class="{{ $menu->icon }}"></i>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>

                                            <td>{{ $menu->route ?: '-' }}</td>

                                            <td>
                                                @if($menu->permission)
                                                    <span
                                                        class="badge {{ $menu->parent_id ? 'bg-secondary' : 'bg-info' }}">
                                                        {{ $menu->permission }}
                                                    </span>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>

                                            <td>{{ $menu->parent?->title ?? '-' }}</td>

                                            <td>{{ $menu->serial }}</td>

                                            <td>
                                                <span class="badge {{ $menu->is_active->badgeClass() }}">
                                                    {{ $menu->is_active->label() }}
                                                </span>
                                            </td>

                                            <td class="text-center">
                                                <a href="{{ route('menus.edit', $menu->id) }}"
                                                   class="btn btn-sm btn-warning">
                                                    Edit
                                                </a>

                                                <form action="{{ route('menus.destroy', $menu->id) }}"
                                                      method="POST"
                                                      class="d-inline delete-form">
                                                    @csrf
                                                    @method('DELETE')

                                                    <button type="button" class="btn btn-sm btn-danger delete-btn">
                                                        Delete
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="9" class="text-center text-muted">
                                                No menus found.
                                            </td>
                                        </tr>
                                    @endforelse

                                    </tbody>
                                </table>
                            </div>

                            @if($menu_list instanceof \Illuminate\Pagination\AbstractPaginator)
                                <div class="d-flex justify-content-end mt-3">
                                    {{ $menu_list->links('partials.pagination') }}
                                </div>
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
        $(document).ready(function () {
            $('.select2').select2({
                width: '100%',
                allowClear: true
            });
        });

        $(document).on('click', '.delete-btn', function () {
            let form = $(this).closest('form');

            Swal.fire({
                title: 'Are you sure?',
                text: 'This menu will be deleted',
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
