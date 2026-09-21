@extends('layout.master', ['page_title' => $page_title])

@section('content')
    <div class="page-content">
        <div class="container-fluid">

            @include('partials.breadcrumb', [
                'items' => [
                    ['label' => 'Restaurant'],
                    ['label' => 'Reservations'],
                ],
            ])

            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">

                            <div class="row mb-3">
                                <div class="col-sm-12">
                                    <h5 class="card-title mb-1">Reservations</h5>
                                    <p class="text-muted mb-0 small">Requests submitted from the homepage reservation form. Call the customer to confirm, then update the status below.</p>
                                </div>
                            </div>

                            <form action="{{ route('restaurant-reservations.index') }}" method="GET">
                                <div class="row g-2 align-items-end">
                                    <div class="col-md-4">
                                        <label class="form-label">Keyword</label>
                                        <input type="text" name="keyword" value="{{ request('keyword') }}"
                                               class="form-control" placeholder="Search by name or phone">
                                    </div>

                                    <div class="col-md-2">
                                        <label class="form-label">Status</label>
                                        <select name="status" class="form-control select2">
                                            <option value="">All Status</option>
                                            @foreach (\App\Enums\ReservationStatusEnum::options() as $value => $label)
                                                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end gap-2 mt-3">
                                    <a href="{{ route('restaurant-reservations.index') }}" class="btn btn-light waves-effect">Reset</a>
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
                                        <th>Name</th>
                                        <th>Phone</th>
                                        <th style="width: 70px;">Party</th>
                                        <th>Date &amp; Time</th>
                                        <th>Notes</th>
                                        <th style="width: 160px;">Status</th>
                                        <th class="text-center" style="width: 100px;">Action</th>
                                    </tr>
                                    </thead>

                                    <tbody>
                                    @forelse($reservations as $reservation)
                                        <tr>
                                            <td>
                                                {{ $reservation->name }}
                                                @if ($reservation->email)
                                                    <br><span class="text-muted small">{{ $reservation->email }}</span>
                                                @endif
                                            </td>
                                            <td><a href="tel:{{ $reservation->phone }}">{{ $reservation->phone }}</a></td>
                                            <td>{{ $reservation->party_size }}</td>
                                            <td>
                                                {{ $reservation->reservation_date->format('M j, Y') }}
                                                <br><span class="text-muted small">{{ \Illuminate\Support\Carbon::parse($reservation->reservation_time)->format('g:i A') }}</span>
                                            </td>
                                            <td>
                                                <span class="d-inline-block text-truncate align-middle" style="max-width: 220px;" title="{{ $reservation->notes }}">
                                                    {{ $reservation->notes ?: '—' }}
                                                </span>
                                            </td>
                                            <td>
                                                @can('restaurant_reservation-edit')
                                                    <select class="form-control select2 status-select" data-url="{{ route('restaurant-reservations.update', $reservation->id) }}">
                                                        @foreach (\App\Enums\ReservationStatusEnum::options() as $value => $label)
                                                            <option value="{{ $value }}" @selected($reservation->status->value === $value)>{{ $label }}</option>
                                                        @endforeach
                                                    </select>
                                                @else
                                                    <span class="badge {{ $reservation->status->badgeClass() }}">{{ $reservation->status->label() }}</span>
                                                @endcan
                                            </td>
                                            <td class="text-center">
                                                @can('restaurant_reservation-delete')
                                                    <form action="{{ route('restaurant-reservations.destroy', $reservation->id) }}" method="POST" class="d-inline delete-form">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="button" class="btn btn-sm btn-danger delete-btn">Delete</button>
                                                    </form>
                                                @endcan
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="text-center text-muted">No reservations found.</td>
                                        </tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>

                            <div class="d-flex justify-content-end mt-3">
                                {{ $reservations->links('partials.pagination') }}
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
            $('.select2').select2({width: '100%', minimumResultsForSearch: -1});
        });

        $(document).on('click', '.delete-btn', function () {
            let form = $(this).closest('form');

            Swal.fire({
                title: 'Are you sure?',
                text: 'This reservation will be deleted',
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

        // Status select - updates in place via AJAX, same pattern as the
        // is_active toggles on other Restaurant sections.
        $(document).on('change', '.status-select', function () {
            let select = $(this);
            let url = select.data('url');
            let previousValue = select.data('previous') ?? select.val();
            let token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            select.prop('disabled', true);

            fetch(url, {
                method: 'PATCH',
                headers: {
                    'X-CSRF-TOKEN': token,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({status: select.val()}),
            })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error('Request failed');
                    }
                    return response.json();
                })
                .then((data) => {
                    select.data('previous', data.status);

                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'success',
                        title: 'Status updated',
                        showConfirmButton: false,
                        timer: 1200,
                        timerProgressBar: true,
                    });
                })
                .catch(() => {
                    select.val(previousValue).trigger('change.select2');

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
                    select.prop('disabled', false);
                });
        });
    </script>
@endsection
