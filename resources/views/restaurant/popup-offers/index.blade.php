@extends('layout.master', ['page_title' => $page_title])

@section('content')
    <div class="page-content">
        <div class="container-fluid">

            @include('partials.breadcrumb', [
                'items' => [
                    ['label' => 'Restaurant'],
                    ['label' => 'Popup Offers'],
                ],
            ])

            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">

                            <div class="row mb-3">
                                <div class="col-sm-6">
                                    <h5 class="card-title mb-1">Popup Offers</h5>
                                    <p class="text-muted mb-0 small">Shown as a slider popup on the homepage. Only Active offers are shown to visitors.</p>
                                </div>

                                @can('restaurant_popup_offer-create')
                                    <div class="col-sm-6">
                                        <div class="d-flex justify-content-end">
                                            <a href="{{ route('restaurant-popup-offers.create') }}" class="btn btn-success">
                                                <i class="mdi mdi-plus"></i> Add Popup Offer
                                            </a>
                                        </div>
                                    </div>
                                @endcan
                            </div>

                            <form action="{{ route('restaurant-popup-offers.index') }}" method="GET">
                                <div class="row g-2 align-items-end">
                                    <div class="col-md-4">
                                        <label class="form-label">Keyword</label>
                                        <input type="text" name="keyword" value="{{ request('keyword') }}"
                                               class="form-control" placeholder="Search by title">
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
                                    <a href="{{ route('restaurant-popup-offers.index') }}" class="btn btn-light waves-effect">Reset</a>
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
                                        <th style="width: 100px;">Image</th>
                                        <th>Title</th>
                                        <th style="width: 80px;">Order</th>
                                        <th class="text-center" style="width: 100px;">Active</th>
                                        <th class="text-center" style="width: 160px;">Action</th>
                                    </tr>
                                    </thead>

                                    <tbody>
                                    @forelse($popupOffers as $popupOffer)
                                        <tr>
                                            <td>
                                                <img src="{{ \Storage::url($popupOffer->image_path) }}" alt="{{ $popupOffer->title ?? 'Popup offer' }}"
                                                     style="width: 80px; height: 60px; object-fit: cover;" class="rounded">
                                            </td>
                                            <td>{{ $popupOffer->title ?: '—' }}</td>
                                            <td>{{ $popupOffer->display_order }}</td>
                                            <td class="text-center">
                                                @can('restaurant_popup_offer-edit')
                                                    <div class="form-check form-switch d-flex justify-content-center">
                                                        <input type="checkbox" class="form-check-input toggle-flag" role="switch"
                                                               data-url="{{ route('restaurant-popup-offers.toggle-active', $popupOffer->id) }}"
                                                               data-field="is_active"
                                                               @checked($popupOffer->is_active)
                                                               aria-label="Toggle active on homepage">
                                                    </div>
                                                @else
                                                    <span class="badge {{ $popupOffer->is_active ? 'bg-success' : 'bg-danger' }}">{{ $popupOffer->is_active ? 'Active' : 'Inactive' }}</span>
                                                @endcan
                                            </td>
                                            <td class="text-center">
                                                @can('restaurant_popup_offer-edit')
                                                    <a href="{{ route('restaurant-popup-offers.edit', $popupOffer->id) }}" class="btn btn-sm btn-warning">Edit</a>
                                                @endcan

                                                @can('restaurant_popup_offer-delete')
                                                    <form action="{{ route('restaurant-popup-offers.destroy', $popupOffer->id) }}" method="POST" class="d-inline delete-form">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="button" class="btn btn-sm btn-danger delete-btn">Delete</button>
                                                    </form>
                                                @endcan
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center text-muted">No popup offers found.</td>
                                        </tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>

                            <div class="d-flex justify-content-end mt-3">
                                {{ $popupOffers->links('partials.pagination') }}
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
                text: 'This popup offer will be deleted',
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

        // Quick Active switch - flips is_active in place via AJAX so an
        // admin doesn't have to open the full edit form.
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
                    checkbox.prop('checked', !!data[field]);

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
