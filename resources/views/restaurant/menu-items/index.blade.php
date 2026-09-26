@extends('layout.master', ['page_title' => $page_title])

@section('content')
    <div class="page-content">
        <div class="container-fluid">

            @include('partials.breadcrumb', [
                'items' => [
                    ['label' => 'Restaurant'],
                    ['label' => 'Menu Items'],
                ],
            ])

            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">

                            <div class="row mb-3">
                                <div class="col-sm-6">
                                    <h5 class="card-title mb-1">Menu Items</h5>
                                </div>

                                @can('restaurant_menu_item-create')
                                    <div class="col-sm-6">
                                        <div class="d-flex justify-content-end">
                                            <a href="{{ route('restaurant-menu-items.create') }}" class="btn btn-success">
                                                <i class="mdi mdi-plus"></i> Add Menu Item
                                            </a>
                                        </div>
                                    </div>
                                @endcan
                            </div>

                            <form action="{{ route('restaurant-menu-items.index') }}" method="GET" data-mobile-filters>
                                <div class="row g-2 align-items-end">
                                    <div class="col-md-3">
                                        <label class="form-label">Keyword</label>
                                        <input type="text" name="keyword" value="{{ request('keyword') }}"
                                               class="form-control" placeholder="Search by name">
                                    </div>

                                    <div class="col-md-3">
                                        <label class="form-label">Category</label>
                                        <select name="restaurant_menu_category_id" class="form-control select2">
                                            <option value="">All Categories</option>
                                            @foreach($categories as $category)
                                                <option value="{{ $category->id }}" @selected(request('restaurant_menu_category_id') == $category->id)>{{ $category->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="col-md-2">
                                        <label class="form-label">Availability</label>
                                        <select name="is_available" class="form-control select2">
                                            <option value="">All</option>
                                            <option value="1" @selected(request('is_available') === '1')>Available</option>
                                            <option value="0" @selected(request('is_available') === '0')>Unavailable</option>
                                        </select>
                                    </div>

                                    <div class="col-md-2">
                                        <label class="form-label">Featured</label>
                                        <select name="is_featured" class="form-control select2">
                                            <option value="">All</option>
                                            <option value="1" @selected(request('is_featured') === '1')>Featured</option>
                                            <option value="0" @selected(request('is_featured') === '0')>Not Featured</option>
                                        </select>
                                    </div>

                                    <div class="col-md-2">
                                        <label class="form-label">New Item</label>
                                        <select name="is_new" class="form-control select2">
                                            <option value="">All</option>
                                            <option value="1" @selected(request('is_new') === '1')>New Item</option>
                                            <option value="0" @selected(request('is_new') === '0')>Not New</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end gap-2 mt-3">
                                    <a href="{{ route('restaurant-menu-items.index') }}" class="btn btn-light waves-effect">Reset</a>
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

                            {{-- Phones: compact cards (image, name, category, price, status
                                 badges, quick toggles, actions). The table below is unchanged
                                 and shown from md up. --}}
                            <div class="d-md-none menu-item-cards">
                                @forelse($items as $item)
                                    <div class="menu-item-card border rounded p-2 mb-2 d-flex gap-2">
                                        @if($item->image_path)
                                            <img src="{{ \Storage::url($item->image_path) }}" alt="{{ $item->name }}" class="rounded flex-shrink-0 menu-item-thumb">
                                        @else
                                            <div class="rounded bg-light d-flex align-items-center justify-content-center text-muted flex-shrink-0 menu-item-thumb">
                                                <i class="mdi mdi-image-off font-size-18"></i>
                                            </div>
                                        @endif

                                        <div class="flex-grow-1 min-w-0">
                                            <div class="d-flex justify-content-between align-items-start gap-2">
                                                <strong class="menu-item-name">{{ $item->name }}</strong>
                                                <span class="fw-bold text-nowrap">&#2547;{{ number_format($item->price, 2) }}</span>
                                            </div>
                                            <div class="small text-muted text-truncate">
                                                {{ $item->category?->name ?? '-' }}@if($item->price_note) · {{ $item->price_note }}@endif
                                            </div>

                                            <div class="d-flex flex-wrap gap-1 mt-1">
                                                <span class="badge {{ $item->is_available ? 'bg-success' : 'bg-danger' }}">{{ $item->is_available ? 'Available' : 'Unavailable' }}</span>
                                                @cannot('restaurant_menu_item-edit')
                                                    @if($item->is_featured)<span class="badge bg-info">Featured</span>@endif
                                                    @if($item->is_new)<span class="badge bg-info">New</span>@endif
                                                @endcannot
                                            </div>

                                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-2">
                                                @can('restaurant_menu_item-edit')
                                                    <div class="d-flex gap-3 small">
                                                        <label class="form-check form-switch mb-0 d-flex align-items-center gap-1">
                                                            <input type="checkbox" class="form-check-input toggle-flag m-0" role="switch"
                                                                   data-url="{{ route('restaurant-menu-items.toggle-featured', $item->id) }}"
                                                                   data-field="is_featured"
                                                                   @checked($item->is_featured)
                                                                   aria-label="Toggle featured on homepage">
                                                            <span>Featured</span>
                                                        </label>
                                                        <label class="form-check form-switch mb-0 d-flex align-items-center gap-1">
                                                            <input type="checkbox" class="form-check-input toggle-flag m-0" role="switch"
                                                                   data-url="{{ route('restaurant-menu-items.toggle-new', $item->id) }}"
                                                                   data-field="is_new"
                                                                   @checked($item->is_new)
                                                                   aria-label="Toggle new item on homepage">
                                                            <span>New</span>
                                                        </label>
                                                    </div>
                                                @endcan

                                                <div class="d-flex gap-2 ms-auto">
                                                    @can('restaurant_menu_item-edit')
                                                        <a href="{{ route('restaurant-menu-items.edit', $item->id) }}" class="btn btn-sm btn-warning">Edit</a>
                                                    @endcan
                                                    @can('restaurant_menu_item-delete')
                                                        <form action="{{ route('restaurant-menu-items.destroy', $item->id) }}" method="POST" class="d-inline delete-form">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="button" class="btn btn-sm btn-danger delete-btn">Delete</button>
                                                        </form>
                                                    @endcan
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <div class="text-center text-muted py-3">No menu items found.</div>
                                @endforelse
                            </div>

                            <div class="table-responsive d-none d-md-block">
                                <table class="table table-hover align-middle">
                                    <thead class="table-light">
                                    <tr>
                                        <th style="width: 70px;">Image</th>
                                        <th>Name</th>
                                        <th>Category</th>
                                        <th>Price</th>
                                        <th class="text-center" style="width: 90px;">Featured</th>
                                        <th class="text-center" style="width: 90px;">New Item</th>
                                        <th>Status</th>
                                        <th class="text-center" style="width: 160px;">Action</th>
                                    </tr>
                                    </thead>

                                    <tbody>
                                    @forelse($items as $item)
                                        <tr>
                                            <td>
                                                @if($item->image_path)
                                                    <img src="{{ \Storage::url($item->image_path) }}" alt="{{ $item->name }}" class="rounded" style="width: 48px; height: 48px; object-fit: cover;">
                                                @else
                                                    <div class="rounded bg-light d-flex align-items-center justify-content-center text-muted" style="width: 48px; height: 48px;">
                                                        <i class="mdi mdi-image-off"></i>
                                                    </div>
                                                @endif
                                            </td>
                                            <td><strong>{{ $item->name }}</strong></td>
                                            <td>{{ $item->category?->name ?? '-' }}</td>
                                            <td>
                                                &#2547;{{ number_format($item->price, 2) }}
                                                @if($item->price_note)
                                                    <div class="text-muted small">{{ $item->price_note }}</div>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                @can('restaurant_menu_item-edit')
                                                    <div class="form-check form-switch d-flex justify-content-center">
                                                        <input type="checkbox" class="form-check-input toggle-flag" role="switch"
                                                               data-url="{{ route('restaurant-menu-items.toggle-featured', $item->id) }}"
                                                               data-field="is_featured"
                                                               @checked($item->is_featured)
                                                               aria-label="Toggle featured on homepage">
                                                    </div>
                                                @else
                                                    <span class="badge {{ $item->is_featured ? 'bg-info' : 'bg-secondary' }}">{{ $item->is_featured ? 'Yes' : 'No' }}</span>
                                                @endcan
                                            </td>
                                            <td class="text-center">
                                                @can('restaurant_menu_item-edit')
                                                    <div class="form-check form-switch d-flex justify-content-center">
                                                        <input type="checkbox" class="form-check-input toggle-flag" role="switch"
                                                               data-url="{{ route('restaurant-menu-items.toggle-new', $item->id) }}"
                                                               data-field="is_new"
                                                               @checked($item->is_new)
                                                               aria-label="Toggle new item on homepage">
                                                    </div>
                                                @else
                                                    <span class="badge {{ $item->is_new ? 'bg-info' : 'bg-secondary' }}">{{ $item->is_new ? 'Yes' : 'No' }}</span>
                                                @endcan
                                            </td>
                                            <td>
                                                <span class="badge {{ $item->is_available ? 'bg-success' : 'bg-danger' }}">
                                                    {{ $item->is_available ? 'Available' : 'Unavailable' }}
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                @can('restaurant_menu_item-edit')
                                                    <a href="{{ route('restaurant-menu-items.edit', $item->id) }}" class="btn btn-sm btn-warning">Edit</a>
                                                @endcan

                                                @can('restaurant_menu_item-delete')
                                                    <form action="{{ route('restaurant-menu-items.destroy', $item->id) }}" method="POST" class="d-inline delete-form">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="button" class="btn btn-sm btn-danger delete-btn">Delete</button>
                                                    </form>
                                                @endcan
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" class="text-center text-muted">No menu items found.</td>
                                        </tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>

                            <div class="d-flex justify-content-end mt-3">
                                {{ $items->links('partials.pagination') }}
                            </div>

                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
@endsection

@section('CSSheet')
    <style>
        .menu-item-thumb { width: 64px; height: 64px; object-fit: cover; }
        .menu-item-name { line-height: 1.25; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; overflow-wrap: anywhere; }
        .menu-item-card .min-w-0 { min-width: 0; }
        .menu-item-card .form-switch { padding-left: 0; min-height: 36px; }
        .menu-item-card .form-switch .form-check-input { width: 2.6rem; height: 1.5rem; margin: 0; float: none; }
    </style>
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
                text: 'This menu item will be deleted',
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

        // Quick Featured / New Item switches - flips the flag in place via
        // AJAX so an admin doesn't have to open the full edit form.
        $(document).on('change', '.toggle-flag', function () {
            let checkbox = $(this);
            let url = checkbox.data('url');
            let field = checkbox.data('field');
            let wasChecked = checkbox.is(':checked');
            let token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            checkbox.prop('disabled', true);

            fetch(url, {
                method: 'PATCH',
                headers: {
                    'X-CSRF-TOKEN': token,
                    'Accept': 'application/json',
                },
            })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error('Request failed');
                    }
                    return response.json();
                })
                .then((data) => {
                    // Keep the phone card and desktop row copies of this switch in sync.
                    $('.toggle-flag[data-url="' + url + '"]').prop('checked', !!data[field]);

                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'success',
                        title: 'Updated',
                        showConfirmButton: false,
                        timer: 1200,
                        timerProgressBar: true,
                    });
                })
                .catch(() => {
                    checkbox.prop('checked', !wasChecked);

                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'error',
                        title: 'Could not update, please try again',
                        showConfirmButton: false,
                        timer: 2000,
                        timerProgressBar: true,
                    });
                })
                .finally(() => {
                    checkbox.prop('disabled', false);
                });
        });
    </script>
@endsection
