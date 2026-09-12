@extends('layout.master', ['page_title' => $page_title])

@section('content')
    <div class="page-content">
        <div class="container-fluid">

            @include('partials.breadcrumb', [
                'items' => [
                    ['label' => 'Notifications'],
                ],
            ])

            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">

                            <div class="row mb-3 align-items-end">
                                <div class="col-sm-6">
                                    <h5 class="card-title mb-1">
                                        Notifications
                                    </h5>
                                </div>

                                <div class="col-sm-6">
                                    <div class="d-flex justify-content-end gap-2">
                                        <form method="POST" action="{{ route('notifications.mark-all-read') }}">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-secondary">
                                                <i class="mdi mdi-check-all me-1"></i>
                                                Mark All Read
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <form action="{{ route('notifications.index') }}" method="GET">
                                <div class="row g-2 align-items-end">
                                    <div class="col-md-3">
                                        <label class="form-label">
                                            Status
                                        </label>

                                        <select name="status" class="form-control select2">
                                            <option value="">
                                                All
                                            </option>

                                            <option value="unread" @selected(request('status') === 'unread')>
                                                Unread
                                            </option>

                                            <option value="read" @selected(request('status') === 'read')>
                                                Read
                                            </option>
                                        </select>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end gap-2 mt-3">
                                    <a href="{{ route('notifications.index') }}"
                                       class="btn btn-light waves-effect">
                                        Reset
                                    </a>

                                    <button type="submit"
                                            class="btn btn-primary waves-effect waves-light">
                                        <i class="mdi mdi-magnify me-1"></i>
                                        Search
                                    </button>
                                </div>
                            </form>

                        </div>
                    </div>
                </div>
            </div>

            @if ($message = Session::get('message'))
                <div class="alert alert-success alert-dismissible fade show">
                    <button type="button"
                            class="btn-close"
                            data-bs-dismiss="alert"></button>
                    {{ $message }}
                </div>
            @endif

            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">

                            <div class="list-group list-group-flush">
                                @forelse($notifications as $notification)
                                    <a href="{{ $notification->data['url'] ?? 'javascript:void(0)' }}"
                                       class="list-group-item list-group-item-action text-reset js-notification-item {{ is_null($notification->read_at) ? 'bg-light' : '' }}"
                                       data-read-url="{{ route('notifications.read', $notification->id) }}"
                                       data-target-url="{{ $notification->data['url'] ?? '' }}">
                                        <div class="d-flex">
                                            <div class="avatar-xs me-3">
                                                <span class="avatar-title bg-{{ $notification->data['type'] ?? 'primary' }} rounded-circle font-size-16">
                                                    <i class="bx {{ $notification->data['icon'] ?? 'bx-bell' }}"></i>
                                                </span>
                                            </div>

                                            <div class="flex-grow-1">
                                                <div class="d-flex justify-content-between">
                                                    <h6 class="mb-1 {{ is_null($notification->read_at) ? 'fw-bold' : '' }}">
                                                        {{ $notification->data['title'] ?? 'Notification' }}

                                                        @if(is_null($notification->read_at))
                                                            <span class="badge bg-danger rounded-pill ms-1">New</span>
                                                        @endif
                                                    </h6>

                                                    <small class="text-muted">
                                                        {{ $notification->created_at->diffForHumans() }}
                                                    </small>
                                                </div>

                                                <p class="mb-0 text-muted">
                                                    {{ $notification->data['message'] ?? '' }}
                                                </p>
                                            </div>
                                        </div>
                                    </a>
                                @empty
                                    <div class="text-center text-muted py-4">
                                        No notifications found.
                                    </div>
                                @endforelse
                            </div>

                            <div class="d-flex justify-content-end mt-3">
                                {{ $notifications->links('partials.pagination') }}
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
            $('.select2').select2({
                width: '100%',
                allowClear: true
            });

            $('.select2').on('change', function () {
                $(this).closest('form').submit();
            });
        });

        document.addEventListener('click', function (event) {
            var item = event.target.closest('.js-notification-item');

            if (!item) {
                return;
            }

            event.preventDefault();

            var readUrl = item.getAttribute('data-read-url');
            var targetUrl = item.getAttribute('data-target-url');
            var token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            fetch(readUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': token,
                    'Accept': 'application/json',
                },
            }).finally(function () {
                item.classList.remove('bg-light');

                if (targetUrl) {
                    window.location.href = targetUrl;
                }
            });
        });
    </script>
@endsection
