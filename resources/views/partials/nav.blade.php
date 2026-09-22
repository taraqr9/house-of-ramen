<header id="page-topbar">
    <div class="navbar-header">
        <div class="d-flex">
            <!-- LOGO -->
            <div class="navbar-brand-box">
                <a href="{{ route('dashboard') }}" class="logo logo-dark">
                    <span class="logo-sm">
                        <img src="{{ asset('images/logo.svg') }}" alt="" height="22">
                    </span>
                    <span class="logo-lg">
                        <img src="{{ asset('images/logo-light.svg') }}" alt="" height="40">
                    </span>
                </a>

                <a href="{{ route('dashboard') }}" class="logo logo-light">
                    <span class="logo-sm">
                        <img src="{{ asset('images/logo.svg') }}" alt="" height="22">
                    </span>
                    <span class="logo-lg">
                        <img src="{{ asset('images/logo-light.svg') }}" alt="" height="40">
                    </span>
                </a>
            </div>

            <button type="button" class="btn btn-sm px-3 font-size-16 header-item waves-effect" id="vertical-menu-btn">
                <i class="fa fa-fw fa-bars"></i>
            </button>

            <!-- App Search-->
            <form class="app-search d-none d-lg-block">
                <div class="position-relative">
                    <input type="text" class="form-control" placeholder="Search...">
                    <span class="bx bx-search-alt"></span>
                </div>
            </form>
        </div>

        <div class="d-flex">

            @if(app('impersonate')->isImpersonating())
                <div class="d-inline-block align-self-center me-2">
                    <form method="POST" action="{{ route('users.impersonate.leave') }}" class="m-0">
                        @csrf

                        <button type="submit"
                                class="btn btn-warning btn-sm waves-effect waves-light">
                            <i class="bx bx-log-out-circle me-1"></i>
                            <span class="d-none d-sm-inline">Leave Impersonation</span>
                            <span class="d-inline d-sm-none">Leave</span>
                        </button>
                    </form>
                </div>
            @endif

            <div class="dropdown d-inline-block d-lg-none ms-2">
                <button type="button" class="btn header-item noti-icon waves-effect" id="page-header-search-dropdown"
                        data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="mdi mdi-magnify"></i>
                </button>
                <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end p-0"
                     aria-labelledby="page-header-search-dropdown">

                    <form class="p-3">
                        <div class="form-group m-0">
                            <div class="input-group">
                                <input type="text" class="form-control" placeholder="Search ..."
                                       aria-label="Recipient's username">
                                <div class="input-group-append">
                                    <button class="btn btn-primary" type="submit"><i class="mdi mdi-magnify"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="dropdown d-none d-lg-inline-block ms-1">
                <button type="button" class="btn header-item noti-icon waves-effect" data-bs-toggle="fullscreen">
                    <i class="bx bx-fullscreen"></i>
                </button>
            </div>

            <div class="dropdown d-inline-block">
                <button type="button" class="btn header-item noti-icon waves-effect"
                        id="page-header-notifications-dropdown"
                        data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="bx bx-bell {{ $navUnreadCount > 0 ? 'bx-tada' : '' }}"></i>
                    @if($navUnreadCount > 0)
                        <span class="badge bg-danger rounded-pill">{{ $navUnreadCount > 99 ? '99+' : $navUnreadCount }}</span>
                    @endif
                </button>
                <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end p-0"
                     aria-labelledby="page-header-notifications-dropdown">
                    <div class="p-3">
                        <div class="row align-items-center">
                            <div class="col">
                                <h6 class="m-0">Notifications</h6>
                            </div>
                            <div class="col-auto">
                                @if($navUnreadCount > 0)
                                    <form method="POST" action="{{ route('notifications.mark-all-read') }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-link btn-sm p-0 small">Mark all read</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div data-simplebar style="max-height: 230px;">
                        @forelse($navNotifications as $notification)
                            <a href="{{ $notification->data['url'] ?? 'javascript:void(0)' }}"
                               class="text-reset notification-item js-notification-item {{ is_null($notification->read_at) ? 'notification-unread' : '' }}"
                               data-read-url="{{ route('notifications.read', $notification->id) }}"
                               data-target-url="{{ $notification->data['url'] ?? '' }}">
                                <div class="d-flex">
                                    <div class="avatar-xs me-3">
                                        <span class="avatar-title bg-{{ $notification->data['type'] ?? 'primary' }} rounded-circle font-size-16">
                                            <i class="bx {{ $notification->data['icon'] ?? 'bx-bell' }}"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1 {{ is_null($notification->read_at) ? 'fw-bold' : '' }}">
                                            {{ $notification->data['title'] ?? 'Notification' }}
                                        </h6>
                                        <div class="font-size-12 text-muted">
                                            <p class="mb-1">{{ $notification->data['message'] ?? '' }}</p>
                                            <p class="mb-0"><i class="mdi mdi-clock-outline"></i> {{ $notification->created_at->diffForHumans() }}</p>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        @empty
                            <div class="text-center text-muted p-3">
                                No notifications yet.
                            </div>
                        @endforelse
                    </div>
                    <div class="p-2 border-top d-grid">
                        <a class="btn btn-sm btn-link font-size-14 text-center" href="{{ route('notifications.index') }}">
                            <i class="mdi mdi-arrow-right-circle me-1"></i> View All
                        </a>
                    </div>
                </div>
            </div>

            <div class="dropdown d-inline-block">
                <button type="button" class="btn header-item waves-effect" id="page-header-user-dropdown"
                        data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <img class="rounded-circle header-profile-user"
                         src="{{ auth()->user()->avatar_path ? \Storage::url(auth()->user()->avatar_path) : asset('images/users/avatar-1.jpg') }}"
                         alt="Header Avatar">
                    <span class="d-none d-xl-inline-block ms-1" key="t-henry">{{ auth()->user()->name }}</span>
                    <i class="mdi mdi-chevron-down d-none d-xl-inline-block"></i>
                </button>
                <div class="dropdown-menu dropdown-menu-end">
                    <!-- item-->
                    <a class="dropdown-item" href="{{ route('profile.show') }}"><i
                            class="bx bx-user font-size-16 align-middle me-1"></i> <span key="t-profile">Profile</span></a>

                    <form method="POST" action="{{ route('logout') }}" class="m-0">
                        @csrf

                        <button type="submit" class="dropdown-item text-danger">
                            <i class="bx bx-power-off font-size-16 align-middle me-1 text-danger"></i>
                            <span key="t-logout">Logout</span>
                        </button>
                    </form>
                </div>
            </div>

        </div>
    </div>
</header>

<style>
    .notification-item.notification-unread {
        background-color: #e9ecef;
    }
</style>

<script>
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
            item.classList.remove('notification-unread');

            if (targetUrl) {
                window.location.href = targetUrl;
            }
        });
    });
</script>
