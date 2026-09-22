<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>{{ isset($page_title) && $page_title ? $page_title . ' | House of Ramen - Admin & Dashboard' : 'House of Ramen - Admin & Dashboard' }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta content="Premium Multipurpose Admin & Dashboard Template" name="description" />

    <!-- App favicon -->
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('brand/favicon-32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('brand/favicon-16.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('brand/apple-touch-icon-180.png') }}">
    <!-- Bootstrap Css -->
    <link href="{{ asset('css/bootstrap.min.css') }}" id="bootstrap-style" rel="stylesheet" type="text/css" />
    <!-- Icons Css -->
    <link href="{{ asset('css/icons.min.css') }}" rel="stylesheet" type="text/css" />
    <!-- App Css-->
    <link href="{{ asset('css/app.min.css') }}" id="app-style" rel="stylesheet" type="text/css" />
    <link href="{{ asset('css/custom.css') }}" id="app-style" rel="stylesheet" type="text/css" />

    @yield('AuthCSSheet')
</head>

<body>
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

    @yield('AuthContent')

<!-- JAVASCRIPT -->
<script src="{{ asset('libs/jquery/jquery.min.js') }}"></script>
<script src="{{ asset('libs/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('libs/metismenu/metisMenu.min.js') }}"></script>
<script src="{{ asset('libs/simplebar/simplebar.min.js') }}"></script>
<script src="{{ asset('libs/node-waves/waves.min.js') }}"></script>

<!-- App js -->
<script src="{{ asset('js/app.js') }}"></script>

<script>
    setTimeout(function () {
        let alertBox = document.getElementById('topRightAlert');

        if (alertBox) {
            let bsAlert = bootstrap.Alert.getOrCreateInstance(alertBox);
            bsAlert.close();
        }
    }, 3000);
</script>

@yield('AuthJScript')

</body>
</html>
