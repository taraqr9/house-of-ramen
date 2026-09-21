@extends('layout.master', ['page_title' => $page_title])

@section('content')
    <div class="page-content">
        <div class="container-fluid">

            @include('partials.breadcrumb', [
                'items' => [
                    ['label' => 'Restaurant'],
                    ['label' => 'Reviews'],
                ],
            ])

            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">

                            <div class="row mb-3">
                                <div class="col-sm-6">
                                    <h5 class="card-title mb-1">Customer Reviews</h5>
                                    <p class="text-muted mb-0 small">Real reviews copied from Google - shown on the homepage. Only Active reviews are shown to visitors.</p>
                                </div>

                                @can('restaurant_review-create')
                                    <div class="col-sm-6">
                                        <div class="d-flex justify-content-end">
                                            <a href="{{ route('restaurant-reviews.create') }}" class="btn btn-success">
                                                <i class="mdi mdi-plus"></i> Add Review
                                            </a>
                                        </div>
                                    </div>
                                @endcan
                            </div>

                            <form action="{{ route('restaurant-reviews.index') }}" method="GET">
                                <div class="row g-2 align-items-end">
                                    <div class="col-md-4">
                                        <label class="form-label">Keyword</label>
                                        <input type="text" name="keyword" value="{{ request('keyword') }}"
                                               class="form-control" placeholder="Search by reviewer name">
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
                                    <a href="{{ route('restaurant-reviews.index') }}" class="btn btn-light waves-effect">Reset</a>
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
                                        <th>Reviewer</th>
                                        <th style="width: 100px;">Rating</th>
                                        <th>Review</th>
                                        <th style="width: 120px;">Reviewed</th>
                                        <th style="width: 80px;">Order</th>
                                        <th class="text-center" style="width: 100px;">Active</th>
                                        <th class="text-center" style="width: 160px;">Action</th>
                                    </tr>
                                    </thead>

                                    <tbody>
                                    @forelse($reviews as $review)
                                        <tr>
                                            <td>{{ $review->author_name }}</td>
                                            <td>
                                                <span class="text-warning">{{ str_repeat('★', $review->rating) }}</span><span class="text-muted">{{ str_repeat('★', 5 - $review->rating) }}</span>
                                            </td>
                                            <td>
                                                <span class="d-inline-block text-truncate align-middle" style="max-width: 320px;" title="{{ $review->review_text }}">
                                                    {{ $review->review_text }}
                                                </span>
                                            </td>
                                            <td>{{ $review->reviewed_at?->format('M j, Y') ?? '—' }}</td>
                                            <td>{{ $review->display_order }}</td>
                                            <td class="text-center">
                                                @can('restaurant_review-edit')
                                                    <div class="form-check form-switch d-flex justify-content-center">
                                                        <input type="checkbox" class="form-check-input toggle-flag" role="switch"
                                                               data-url="{{ route('restaurant-reviews.toggle-active', $review->id) }}"
                                                               data-field="is_active"
                                                               @checked($review->is_active)
                                                               aria-label="Toggle active on homepage">
                                                    </div>
                                                @else
                                                    <span class="badge {{ $review->is_active ? 'bg-success' : 'bg-danger' }}">{{ $review->is_active ? 'Active' : 'Inactive' }}</span>
                                                @endcan
                                            </td>
                                            <td class="text-center">
                                                @can('restaurant_review-edit')
                                                    <a href="{{ route('restaurant-reviews.edit', $review->id) }}" class="btn btn-sm btn-warning">Edit</a>
                                                @endcan

                                                @can('restaurant_review-delete')
                                                    <form action="{{ route('restaurant-reviews.destroy', $review->id) }}" method="POST" class="d-inline delete-form">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="button" class="btn btn-sm btn-danger delete-btn">Delete</button>
                                                    </form>
                                                @endcan
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="text-center text-muted">No reviews found.</td>
                                        </tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>

                            <div class="d-flex justify-content-end mt-3">
                                {{ $reviews->links('partials.pagination') }}
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
                text: 'This review will be deleted',
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
