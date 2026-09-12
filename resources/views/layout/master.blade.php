<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8"/>
    <title>{{ isset($page_title) && $page_title ? $page_title . ' | House of Ramen - Admin & Dashboard' : 'House of Ramen - Admin & Dashboard' }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta content="Premium Multipurpose Admin & Dashboard Template" name="description"/>
    <meta content="Themesbrand" name="author"/>
    <meta name="csrf-token" content="{{ csrf_token() }}">


    @include('partials.styles')

    @yield('CSSheet')
</head>

<body data-sidebar="dark">

<div id="layout-wrapper">
    @php
        $alertType = null;
        $alertMessage = null;

        if (session('success')) {
            $alertType = 'success';
            $alertMessage = session('success');
        } elseif (session('error')) {
            $alertType = 'danger';
            $alertMessage = session('error');
        } elseif (session('warning')) {
            $alertType = 'warning';
            $alertMessage = session('warning');
        } elseif (session('info')) {
            $alertType = 'info';
            $alertMessage = session('info');
        } elseif (session('message')) {
            $alertType = 'success';
            $alertMessage = session('message');
        } elseif ($errors->any()) {
            $alertType = 'danger';
            $alertMessage = $errors->first();
        }

        $alertIcon = match ($alertType) {
            'primary' => 'mdi-bullseye-arrow',
            'secondary' => 'mdi-grease-pencil',
            'success' => 'mdi-check-all',
            'danger' => 'mdi-block-helper',
            'warning' => 'mdi-alert-outline',
            'info' => 'mdi-alert-circle-outline',
            default => 'mdi-alert-circle-outline',
        };
    @endphp

    @if ($alertType && $alertMessage)
        <div id="topRightAlert"
             class="alert alert-{{ $alertType }} alert-dismissible fade show position-fixed top-0 end-0 m-3 shadow-lg border-0 custom-alert-toast"
             role="alert">

            <div class="d-flex align-items-start gap-2">
                <div class="custom-alert-icon alert-icon-{{ $alertType }}">
                    <i class="mdi {{ $alertIcon }}"></i>
                </div>

                <div class="flex-grow-1 pe-3">
                    <div class="fw-semibold">
                        {{ ucfirst($alertType === 'danger' ? 'error' : $alertType) }}
                    </div>

                    <div class="small">
                        {{ $alertMessage }}
                    </div>
                </div>
            </div>

            <button type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                    aria-label="Close"></button>
        </div>
    @endif

    @include('partials.nav')

    @include('partials.sidebar')

    <div class="main-content">
        @yield('content')

        @include('partials.footer')
    </div>
</div>
@include('partials.scripts')

<script>
    setTimeout(function () {
        let alertBox = document.getElementById('topRightAlert');

        if (alertBox) {
            let bsAlert = bootstrap.Alert.getOrCreateInstance(alertBox);
            bsAlert.close();
        }
    }, 3000);
</script>

@yield('JScript')

</body>
</html>
